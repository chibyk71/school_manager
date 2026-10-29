<?php

namespace App\Settings\Definitions;

use App\Support\Settings\SettingsRegistry;

/**
 * School-scoped operational preferences (School Management Phase 2).
 *
 * These keys replace the legacy LocalizationController / website.localization
 * workflow. Values are resolved exclusively via SettingsScope::school($id);
 * there is no implicit global inheritance.
 *
 * Defaults follow application config where applicable (timezone/locale) and
 * region-appropriate historical product defaults for date/currency (NG context).
 * Changing a school preference does not mutate APP_TIMEZONE or rewrite
 * historical transaction currencies.
 *
 * Consumer notes (Phase 2):
 * - timezone / date_format / time_format / locale / week_start: display and
 *   scheduling consumers will adopt these keys; no prior OI-registered
 *   consumers existed (legacy localization was unregistered and broken).
 * - default_currency: advisory default for workflows that need a school
 *   currency hint. Payment and salary records retain their own currency fields.
 */
final class SchoolOperationalSettings
{
    public const GROUP = 'school_operational';

    public const TIMEZONE = 'school_operational.timezone';

    public const DATE_FORMAT = 'school_operational.date_format';

    public const TIME_FORMAT = 'school_operational.time_format';

    public const WEEK_START = 'school_operational.week_start';

    public const LOCALE = 'school_operational.locale';

    public const DEFAULT_CURRENCY = 'school_operational.default_currency';

    /** @var list<string> */
    public const SUPPORTED_DATE_FORMATS = [
        'Y-m-d',
        'd/m/Y',
        'm/d/Y',
        'd-m-Y',
        'm-d-Y',
        'd.m.Y',
    ];

    /** @var list<string> */
    public const SUPPORTED_TIME_FORMATS = [
        'H:i',
        'h:i A',
        'h:i a',
    ];

    /** @var list<string> */
    public const SUPPORTED_WEEK_STARTS = [
        'monday',
        'sunday',
        'saturday',
    ];

    /** @var list<string> Application locales with translation support or product intent. */
    public const SUPPORTED_LOCALES = [
        'en',
        'en_US',
        'en_GB',
        'fr',
        'es',
        'ha',
        'yo',
        'ig',
    ];

    /** @var list<string> ISO 4217 codes aligned with existing Finance/HRM validation. */
    public const SUPPORTED_CURRENCIES = [
        'NGN',
        'USD',
        'GBP',
        'EUR',
        'GHS',
        'KES',
        'ZAR',
    ];

    public static function register(SettingsRegistry $registry): void
    {
        $registry->group(
            self::GROUP,
            'School Operational Preferences',
            function ($group): void {
                $group->define(self::TIMEZONE)
                    ->type('string')
                    ->default((string) config('app.timezone', 'UTC'))
                    ->label('Timezone')
                    ->description(
                        'IANA timezone for school-local display of dates and times. '
                        .'Does not change the application server timezone.'
                    )
                    ->intendedScope('school');

                $group->define(self::DATE_FORMAT)
                    ->type('string')
                    ->default('d/m/Y')
                    ->label('Date display format')
                    ->description(
                        'How dates are shown in the UI. Canonical storage remains ISO (Y-m-d).'
                    )
                    ->intendedScope('school');

                $group->define(self::TIME_FORMAT)
                    ->type('string')
                    ->default('H:i')
                    ->label('Time display format')
                    ->description(
                        'How times are shown in the UI. Canonical storage remains 24-hour where applicable.'
                    )
                    ->intendedScope('school');

                $group->define(self::WEEK_START)
                    ->type('string')
                    ->default('monday')
                    ->label('Week starts on')
                    ->description('First day of the week for calendars and schedules.')
                    ->intendedScope('school');

                $group->define(self::LOCALE)
                    ->type('string')
                    ->default((string) config('app.locale', 'en'))
                    ->label('Locale / language')
                    ->description('Preferred locale for school-facing UI strings and formatting.')
                    ->intendedScope('school');

                $group->define(self::DEFAULT_CURRENCY)
                    ->type('string')
                    ->default('NGN')
                    ->label('Default currency')
                    ->description(
                        'Default currency code for workflows that need a school-level hint. '
                        .'Does not rewrite existing payment or salary record currencies.'
                    )
                    ->intendedScope('school');
            }
        );
    }

    /**
     * Server-side validation beyond the registry string type check.
     *
     * @return array<string, list<string>> Laravel-style validation rules keyed by short name.
     */
    public static function validationRules(): array
    {
        return [
            'timezone' => ['required', 'string', 'timezone:all'],
            'date_format' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_DATE_FORMATS)],
            'time_format' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_TIME_FORMATS)],
            'week_start' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_WEEK_STARTS)],
            'locale' => ['required', 'string', 'in:'.implode(',', self::SUPPORTED_LOCALES)],
            'default_currency' => ['required', 'string', 'size:3', 'in:'.implode(',', self::SUPPORTED_CURRENCIES)],
        ];
    }

    /**
     * Map short form field names to full registry keys.
     *
     * @return array<string, string>
     */
    public static function fieldKeyMap(): array
    {
        return [
            'timezone' => self::TIMEZONE,
            'date_format' => self::DATE_FORMAT,
            'time_format' => self::TIME_FORMAT,
            'week_start' => self::WEEK_START,
            'locale' => self::LOCALE,
            'default_currency' => self::DEFAULT_CURRENCY,
        ];
    }
}
