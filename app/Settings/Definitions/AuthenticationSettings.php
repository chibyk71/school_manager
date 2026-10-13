<?php

namespace App\Settings\Definitions;

use App\Support\Settings\SettingsRegistry;

/**
 * Registers the Authentication pilot setting definitions (Phase 1).
 *
 * Defaults live in code only; they are not seeded into the database.
 */
final class AuthenticationSettings
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->group(
            'authentication',
            'Authentication',
            function ($group): void {
                $group->define('authentication.reset_password_token_life')
                    ->type('integer')
                    ->default(60)
                    ->label('Password reset OTP lifetime (minutes)')
                    ->description('How long a password-reset OTP remains valid.')
                    ->intendedScope('school');

                $group->define('authentication.allow_password_reset')
                    ->type('boolean')
                    ->default(true)
                    ->label('Allow password reset')
                    ->intendedScope('school');
            }
        );
    }
}
