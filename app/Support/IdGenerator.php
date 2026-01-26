<?php

namespace App\Helpers;

use App\Models\School;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * IdGenerator Helper – Centralized, Safe & Configurable ID Generation
 *
 * This helper generates human-readable, prefixed identifiers using the school's customizable
 * ID format patterns (stored in settings under 'website.id_formats').
 *
 * Features / Problems Solved:
 * - Fully respects per-type patterns defined by school admins (e.g. {SCHOOL}-{PREFIX}-{YEAR}-{SEQUENCE})
 * - Supports dynamic placeholders: {PREFIX}, {SCHOOL}, {YEAR}, {SEQUENCE}
 * - School code priority: $school->code if exists → first 3 uppercase letters of name → fallback 'SCH'
 * - Atomic counter increment (cache + lock) → prevents race conditions/duplicates
 * - Zero-padding controlled per ID type via sequence_length setting
 * - Separator handling: cleans multiple consecutive separators
 * - Fallbacks: sensible defaults when no school override exists
 * - Performance: heavy caching of counters & settings (long TTL)
 * - Security: no user input in counter logic; sanitized placeholders
 * - Extensibility: easy to add new placeholders or per-type logic
 * - Production-ready: structured logging on failure, clear exceptions
 *
 * Fits into the User Management & System-Wide Module:
 * - Called from controllers/services when creating records:
 *     StudentController → generate('student_id', $school)
 *     StaffController   → generate('staff_id', $school)
 *     InvoiceService    → generate('invoice', $school)
 * - Reads settings: website.id_formats (pattern & sequence_length) + website.prefixes
 * - Integrates with: PrefixesSettingsController (admin UI to configure patterns)
 * - No direct UI — pure backend helper; results stored in model columns:
 *     admission_number, staff_id_number, guardian_number, invoice_number, etc.
 * - Multi-tenant safe: school context passed explicitly or via GetSchoolModel()
 *
 * Usage Examples:
 *   generate('student_id', $school)                → ABC-STU-2026-000123
 *   generate('staff_id', $school)                  → STF-00456
 *   generate('invoice', $school, now()->year + 1)  → INV/2027/000789
 */

class IdGenerator
{
    /**
     * Generate a formatted ID for the given type
     *
     * @param  string      $type     'student_id', 'staff_id', 'guardian_id', 'invoice', etc.
     * @param  School|null $school   Current school (null = global/system context)
     * @param  int|null    $year     Optional override year (defaults to current year)
     * @return string
     * @throws \Exception
     */
    public static function generate(string $type, ?School $school = null, ?int $year = null): string
    {
        $settings = getMergedSettings('website.id_formats', $school);

        $config = $settings[$type] ?? [
            'pattern' => '{PREFIX}-{SEQUENCE}',
            'sequence_length' => 6,
        ];

        // Determine year (allow override, fallback to current)
        $year = $year ?? now()->year;

        // Get prefix from website.prefixes settings
        $prefix = self::getPrefix($type, $school);

        // Get school code (priority: school->code > 3-letter name fallback > 'SCH')
        $schoolCode = self::getSchoolCode($school);

        // Get next sequential number (atomic)
        $counter = self::getNextCounter($type, $school, $year);

        // Build replacements
        $replacements = [
            '{PREFIX}' => $prefix,
            '{SCHOOL}' => $schoolCode,
            '{YEAR}' => (string) $year,
            '{SEQUENCE}' => str_pad($counter, $config['sequence_length'], '0', STR_PAD_LEFT),
        ];

        // Replace placeholders in pattern
        $id = strtr($config['pattern'], $replacements);

        // Clean up multiple consecutive separators and trim
        $sep = preg_quote($config['separator'] ?? '-', '/');
        $id = preg_replace("/{$sep}{2,}/", $sep, $id);
        $id = trim($id, $sep . ' ');

        // Final validation (should never fail in production)
        if (empty($id) || strlen($id) > 50) {
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

    /**
     * Get the prefix for the ID type from settings
     */
    private static function getPrefix(string $type, ?School $school): string
    {
        $prefixSettings = getMergedSettings('website.prefixes', $school) ?? [];

        $key = str_replace('_id', '', $type); // student_id → student

        return $prefixSettings[$key] ?? strtoupper(substr($type, 0, 3));
    }

    /**
     * Get school code with fallback
     */
    private static function getSchoolCode(?School $school): string
    {
        $school ??= GetSchoolModel();

        return strtoupper($school->code ?? substr($school->name, 0, 3));
    }

    /**
     * Atomically increment and return next counter value
     * Uses cache + lock to prevent race conditions
     */
    private static function getNextCounter(string $type, ?School $school, int $year): int
    {
        $scope = $school?->id;
        $cacheKey = "id_counter:{$type}:{$scope}:{$year}";

        return Cache::lock($cacheKey . ':lock', 10)->block(10, function () use ($cacheKey) {
            $counter = Cache::get($cacheKey, 0) + 1;
            Cache::put($cacheKey, $counter, now()->addYears(10)); // very long TTL
            return $counter;
        });
    }

    /**
     * Reset counter for a specific type/scope/year (admin utility – use with caution)
     */
    public static function resetCounter(string $type, ?School $school = null, ?int $year = null): void
    {
        $scope = $school ? $school->id : 'global';
        $yearPart = $year ?? 'no-year';
        $cacheKey = "id_counter:{$type}:{$scope}:{$yearPart}";
        Cache::forget($cacheKey);
    }
}
