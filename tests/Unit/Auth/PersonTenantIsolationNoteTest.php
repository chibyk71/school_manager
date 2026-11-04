<?php

namespace Tests\Unit\Auth;

use Tests\TestCase;

/**
 * Architecture note (Phase 1):
 *
 * School Manager uses a shared-database multi-school model. Isolation boundaries are:
 * - Application/database instance = tenant boundary for Person (Profile)
 * - school_id on role records (Staff, Student, Guardian) = school workspace boundary
 *
 * Person (Profile) deliberately has neither school_id nor tenant_id.
 * Cross-school Person reuse is intentional (same person can be staff in School A
 * and guardian in School B). Authentication resolves Authentication Accounts globally
 * within the application database, not per-school.
 *
 * There is no database-per-tenant resolver in this repository. SchoolContext selects
 * a workspace after authentication; it is not a tenant isolation mechanism for Person.
 *
 * If a future multi-database tenancy model is introduced, Person creation, lookup,
 * authentication resolution, Profile listing, and password recovery must be bound to
 * the active tenant connection — not school_id.
 */
class PersonTenantIsolationNoteTest extends TestCase
{
    public function test_person_schema_has_no_school_or_tenant_column_by_design(): void
    {
        $profile = new \App\Models\Profile();
        $cols = $profile->getFillable();
        $this->assertNotContains('school_id', $cols);
        $this->assertNotContains('tenant_id', $cols);
    }
}
