<?php

namespace Tests\Unit\Auth;

use App\Models\Profile;
use Tests\TestCase;

/**
 * Tenant deployment invariant (confirmed):
 *
 * One application and database instance per Tenant, with multiple Schools sharing
 * that Tenant's database.
 *
 * Therefore Profile (Person) and User (Authentication Account) remain Tenant-owned
 * through the deployment boundary. No tenant_id is added to Profile, User, or School.
 * No school_id is added to Profile.
 *
 * Cross-Tenant isolation is a deployment/infrastructure invariant, not row-level
 * application filtering. This test protects the intended schema assumptions only;
 * it does not claim to prove that another Tenant's database is unreachable.
 */
class PersonTenantIsolationNoteTest extends TestCase
{
    public function test_person_schema_has_no_school_or_tenant_column_by_design(): void
    {
        $profile = new Profile();
        $cols = $profile->getFillable();
        $this->assertNotContains('school_id', $cols);
        $this->assertNotContains('tenant_id', $cols);
    }

    public function test_authentication_account_fillable_has_no_school_or_tenant_column(): void
    {
        $user = new \App\Models\User();
        $cols = $user->getFillable();
        $this->assertNotContains('school_id', $cols);
        $this->assertNotContains('tenant_id', $cols);
        $this->assertNotContains('email', $cols);
    }
}
