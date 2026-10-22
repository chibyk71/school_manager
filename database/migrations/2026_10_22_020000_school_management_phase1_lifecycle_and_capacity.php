<?php

use App\States\School\Active;
use App\States\School\Archived;
use App\States\School\Draft;
use App\States\School\Ready;
use App\States\School\Suspended;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * School Management Phase 1 — lifecycle state, primary contact name,
 * optional description/motto, and tenant-scoped capacity lock.
 *
 * Backfill strategy (defensive):
 * - Soft-deleted schools keep deleted_at; state set to archived for consistency
 *   when previously inactive, otherwise draft (does not restore).
 * - is_active = true alone does NOT map to Active. Active requires evidence of
 *   a complete readiness profile (name, code, email, type, contact_name,
 *   primary address). Without contact_name (new field) no legacy row can be
 *   proven Ready/Active; they are placed in Draft with a diagnostic note in
 *   data.migration_notes.
 * - Ambiguous rows are reported via data.migration_notes, never silently
 *   promoted to Active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('state')->default(Draft::$name)->after('is_active')->index();
            $table->string('contact_name')->nullable()->after('phone_two');
            $table->string('description')->nullable()->after('type');
            $table->string('motto')->nullable()->after('description');
        });

        // Tenant-scoped capacity lock (single row). Serializes capacity-affecting mutations.
        Schema::create('school_capacity_locks', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key')->default('tenant')->unique();
            $table->timestamps();
        });

        // Ensure the lock row exists for the tenant database.
        DB::table('school_capacity_locks')->insert([
            'scope_key' => 'tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backfillLifecycleState();
    }

    public function down(): void
    {
        Schema::dropIfExists('school_capacity_locks');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['state', 'contact_name', 'description', 'motto']);
        });
    }

    /**
     * Defensive backfill. Never assigns Active solely from is_active.
     */
    private function backfillLifecycleState(): void
    {
        $rows = DB::table('schools')->select([
            'id',
            'name',
            'code',
            'email',
            'type',
            'phone_one',
            'phone_two',
            'is_active',
            'deleted_at',
            'data',
        ])->get();

        foreach ($rows as $row) {
            $data = is_string($row->data) ? (json_decode($row->data, true) ?: []) : (array) ($row->data ?? []);
            $notes = [];

            // Soft-deleted: preserve deletion; classify as archived for lifecycle consistency.
            if ($row->deleted_at !== null) {
                $state = Archived::$name;
                $notes[] = 'Backfill: soft-deleted school mapped to archived (not restored).';
                $this->updateSchoolState($row->id, $state, $data, $notes);

                continue;
            }

            // contact_name is new — no legacy value. Primary contact readiness cannot pass.
            $notes[] = 'Backfill: contact_name was not present on legacy schema; primary-contact readiness cannot be proven.';

            $hasCoreIdentity = filled($row->name)
                && filled($row->code)
                && filled($row->email)
                && filled($row->type);

            $hasPhoneOrEmail = filled($row->email) || filled($row->phone_one) || filled($row->phone_two);

            // Without contact_name, readiness fails → never Active/Ready from legacy alone.
            if ($row->is_active) {
                $notes[] = 'Backfill: is_active=true is insufficient evidence for Active under Phase 1 lifecycle; placed in draft pending readiness completion (contact_name + primary address required).';
                $state = Draft::$name;
            } else {
                $notes[] = 'Backfill: is_active=false mapped to draft (not suspended/archived without explicit lifecycle evidence).';
                $state = Draft::$name;
            }

            if (! $hasCoreIdentity) {
                $notes[] = 'Backfill: incomplete core identity (name/code/email/type).';
            }
            if (! $hasPhoneOrEmail) {
                $notes[] = 'Backfill: no phone or email channel recorded.';
            }

            $this->updateSchoolState($row->id, $state, $data, $notes);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $notes
     */
    private function updateSchoolState(string $id, string $state, array $data, array $notes): void
    {
        $data['migration_notes'] = array_values(array_unique(array_merge(
            (array) ($data['migration_notes'] ?? []),
            $notes
        )));

        DB::table('schools')->where('id', $id)->update([
            'state' => $state,
            'data' => json_encode($data),
            'updated_at' => now(),
        ]);
    }
};
