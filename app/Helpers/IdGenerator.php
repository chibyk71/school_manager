<?php

namespace App\Helpers;

use App\Models\IdSequence;
use App\Models\School;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * IdGenerator – school-configurable IDs with DB-backed sequences (Phase 5).
 *
 * Production correctness for persisted identifiers (admission_number,
 * registration_number, student_id, etc.) requires the id_sequences table.
 * Cache is only a best-effort mirror after a successful DB allocation and is
 * never the sole correctness path for Phase 5 identifier types.
 */
class IdGenerator
{
    public const DB_REQUIRED_TYPES = [
        'admission_number',
        'registration_number',
        'student_id',
    ];

    public static function generate(
        string $type,
        ?School $school = null,
        ?int $year = null,
        ?string $scopeKey = null
    ): string {
        $settings = getMergedSettings('website.id_formats', $school) ?? [];
        $config = $settings[$type] ?? self::defaultConfig($type);
        $year = $year ?? (int) now()->year;
        $prefix = self::getPrefix($type, $school);
        $schoolCode = self::getSchoolCode($school);
        $scopeKey = $scopeKey ?? '';
        $counter = self::getNextCounter($type, $school, $year, $scopeKey);

        $replacements = [
            '{PREFIX}' => $prefix,
            '{SCHOOL}' => $schoolCode,
            '{YEAR}' => (string) $year,
            '{SEQUENCE}' => str_pad((string) $counter, (int) $config['sequence_length'], '0', STR_PAD_LEFT),
        ];

        $id = strtr($config['pattern'], $replacements);
        $sep = preg_quote($config['separator'] ?? '-', '/');
        $id = preg_replace("/$sep{2,}/", $sep, $id);
        $id = trim($id, $sep . ' ');

        if (empty($id) || strlen($id) > 64) {
            Log::error('Generated ID is invalid or too long', [
                'type' => $type,
                'school_id' => $school?->id,
                'pattern' => $config['pattern'],
                'result' => $id,
            ]);
            throw new \Exception("Failed to generate valid ID for type: {$type}");
        }

        return $id;
    }

    public static function getNextCounter(
        string $type,
        ?School $school = null,
        ?int $year = null,
        ?string $scopeKey = null
    ): int {
        $year = $year ?? (int) now()->year;
        $scopeKey = $scopeKey ?? '';
        $schoolId = $school?->id;
        $requiresDb = in_array($type, self::DB_REQUIRED_TYPES, true);

        if (!self::sequencesTableReady()) {
            if ($requiresDb) {
                throw new \RuntimeException(
                    "id_sequences table is required for type '{$type}'. "
                    . 'Run Phase 5 migrations before generating admission/registration numbers.'
                );
            }
            return self::nextFromCache($type, $schoolId, $scopeKey, $year);
        }

        return self::nextFromDatabase($type, $schoolId, $scopeKey, $year);
    }

    public static function resetCounter(
        string $type,
        ?School $school = null,
        ?int $year = null,
        ?string $scopeKey = null
    ): void {
        $year = $year ?? 0;
        $scopeKey = $scopeKey ?? '';
        $schoolId = $school?->id;

        if (self::sequencesTableReady()) {
            IdSequence::query()
                ->where('type', $type)
                ->where('school_id', $schoolId)
                ->where('scope_key', $scopeKey)
                ->where('year', $year)
                ->delete();
        }

        Cache::forget(self::cacheKey($type, $schoolId, $scopeKey, $year));
    }

    /**
 * Database-safe sequence allocation.
 * First-row creation uses insertOrIgnore (does not abort the transaction on unique conflict).
 * The row is then locked with FOR UPDATE and incremented.
 */
    private static function nextFromDatabase(
        string $type,
        ?string $schoolId,
        string $scopeKey,
        int $year
    ): int {
        return DB::transaction(function () use ($type, $schoolId, $scopeKey, $year) {
            DB::table('id_sequences')->insertOrIgnore([
                'type' => $type,
                'school_id' => $schoolId,
                'scope_key' => $scopeKey,
                'year' => $year,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = IdSequence::query()
                ->where('type', $type)
                ->where('school_id', $schoolId)
                ->where('scope_key', $scopeKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $row->last_value = (int) $row->last_value + 1;
            $row->save();

            try {
                Cache::put(
                    self::cacheKey($type, $schoolId, $scopeKey, $year),
                    $row->last_value,
                    now()->addYears(10)
                );
            } catch (\Throwable) {
            }

            return (int) $row->last_value;
        });
    }

    private static function nextFromCache(
        string $type,
        ?string $schoolId,
        string $scopeKey,
        int $year
    ): int {
        $cacheKey = self::cacheKey($type, $schoolId, $scopeKey, $year);

        return Cache::lock($cacheKey . ':lock', 10)->block(10, function () use ($cacheKey) {
            $counter = (int) Cache::get($cacheKey, 0) + 1;
            Cache::put($cacheKey, $counter, now()->addYears(10));

            return $counter;
        });
    }

    private static function sequencesTableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = Schema::hasTable('id_sequences');
            } catch (\Throwable) {
                $ready = false;
            }
        }

        return $ready;
    }

    private static function cacheKey(string $type, ?string $schoolId, string $scopeKey, int $year): string
    {
        return 'id_counter:' . $type . ':' . ($schoolId ?? 'global') . ':' . $scopeKey . ':' . $year;
    }

    private static function getPrefix(string $type, ?School $school): string
    {
        $prefixSettings = getMergedSettings('website.prefixes', $school) ?? [];
        $key = str_replace(['_id', '_number'], '', $type);

        return $prefixSettings[$key]
            ?? $prefixSettings[$type]
            ?? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $type) ?: 'ID', 0, 3));
    }

    private static function getSchoolCode(?School $school): string
    {
        $school ??= function_exists('GetSchoolModel') ? GetSchoolModel() : null;
        if (!$school) {
            return 'SCH';
        }

        return strtoupper($school->code ?? substr((string) $school->name, 0, 3) ?: 'SCH');
    }

    private static function defaultConfig(string $type): array
    {
        return match ($type) {
            'admission_number', 'student_id' => [
                'pattern' => '{PREFIX}/{YEAR}/{SEQUENCE}',
                'sequence_length' => 5,
                'separator' => '/',
            ],
            'registration_number' => [
                'pattern' => '{SEQUENCE}',
                'sequence_length' => 2,
                'separator' => '-',
            ],
            default => [
                'pattern' => '{PREFIX}-{SEQUENCE}',
                'sequence_length' => 6,
                'separator' => '-',
            ],
        };
    }
}
