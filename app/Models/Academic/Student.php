<?php

namespace App\Models\Academic;

/**
 * Compatibility shim for pre-lifecycle references.
 *
 * Canonical student capacity model is App\Models\Student\Student (Phase 1+).
 * Many older modules still type-hint App\Models\Academic\Student.
 * This subclass shares the same table and behaviour so those callers keep working
 * without a global rename in this phase.
 *
 * Prefer App\Models\Student\Student in all new lifecycle code.
 */
class Student extends \App\Models\Student\Student
{
    // Intentionally empty — identity and table come from the parent.
}
