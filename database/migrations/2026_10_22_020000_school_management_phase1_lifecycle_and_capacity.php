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
 * - Soft-deleted schools: deleted_at is preserved (not restored). state is always
 *   set to archived for lifecycle consistency, regardless of legacy is_active.
 *   is_active is then reconciled from state (Archived → is_active=false).
 * - Live schools (deleted_at null): always mapped to Draft. is_active=true alone
 *   does NOT map to Active. Active requires proven readiness (name, code, email,
 *   contact_name, primary address). School Type is optional and is not a readiness
 *   gate. contact_name is new on this migration,
 *   so no legacy row can be proven Ready/Active; all live rows become Draft with
 *   diagnostic notes in data.migration_notes.
 * - is_active is derived FROM state after backfill (Active/Suspended → true;
 *   Draft/Ready/Archived → false), never the reverse.
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

            // Soft-deleted: preserve deletion; always Archived (not Draft), independent of is_active.
            if ($row->deleted_at !== null) {
                $state = Archived::$name;
                $notes[] = 'Backfill: soft-deleted school mapped to archived (deleted_at preserved; not restored).';
                $this->updateSchoolState($row->id, $state, $data, $notes);

                continue;
            }

            // contact_name is new — no legacy value. Primary contact readiness cannot pass.
            $notes[] = 'Backfill: contact_name was not present on legacy schema; primary-contact readiness cannot be proven.';

            // School Type is optional identity metadata, not a readiness gate.
            $hasCoreIdentity = filled($row->name)
                && filled($row->code)
                && filled($row->email);

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
                $notes[] = 'Backfill: incomplete core identity fields (name/code/email; School Type optional).';
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

        // Reconcile legacy is_active FROM lifecycle state only (not the reverse).
        // Active/Suspended are operationally present; Draft/Ready/Archived are not "live".
        $isActive = in_array($state, [Active::$name, Suspended::$name], true);

        DB::table('schools')->where('id', $id)->update([
            'state' => $state,
            'is_active' => $isActive,
            'data' => json_encode($data),
            'updated_at' => now(),
        ]);
    }
};
