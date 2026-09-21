<?php

namespace App\Services\DynamicEnum;

/**
 * Outcomes of Dynamic Enum selection validation (Phase 3).
 *
 * Distinct from business-field nullability: null is never mapped to a status here.
 */
enum DynamicEnumValidationStatus: string
{
    case Valid = 'valid';
    case DefinitionNotConfigured = 'definition_not_configured';
    case InvalidOption = 'invalid_option';
    case InactiveOption = 'inactive_option';
}
