<?php

/*
|--------------------------------------------------------------------------
| School Manager — OI Settings (Phase 1 infrastructure)
|--------------------------------------------------------------------------
|
| Sole forward-looking Settings persistence after removal of
| ruangdeveloper/laravel-settings. Supported application scopes: user, school
| (see App\Support\Settings\SettingsScope). Do not use package global fallback
| as tenant → school → user inheritance. Module settings, authorization, and
| UI belong to later phases.
|
*/


use OiLab\OiLaravelSettings\Data\MailContent;
use OiLab\OiLaravelSettings\Models\Setting;

return [

    /*
    |--------------------------------------------------------------------------
    | Settings table
    |--------------------------------------------------------------------------
    |
    | The database table backing the settings store. It is created by the
    | package migration and can be overridden here if it clashes with an
    | existing table in the host application.
    |
    */

    'table' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist settings. Point this at a subclass to
    | add relations, scopes or behaviour without forking the package. It is
    | always resolved through the OiLaravelSettings resolver, never hardcoded.
    |
    */

    'models' => [
        'setting' => Setting::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoping
    |--------------------------------------------------------------------------
    |
    | Settings are resolved per scope with a fallback to the global layer
    | (scope = null). A scope is any string|int identifier (a shop id, a team
    | id, a locale…). Resolution order for a read is:
    |
    |     current scope  ->  global (null)  ->  provided default
    |
    | "scope_resolver" returns the current scope and may be:
    |   - null                         → always global
    |   - a Closure returning string|int|null
    |   - an invokable class-string (resolved from the container)
    |
    | Prefer registering the resolver at runtime with
    | Settings::resolveScopeUsing(fn () => …) inside a service provider when you
    | rely on config caching, since closures cannot be cached.
    |
    */

    'scope_resolver' => null,

    'default_scope' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Resolved scope maps are cached and busted on every write. Set "store" to
    | null to use the application default store and "ttl" to null to cache
    | forever (seconds otherwise).
    |
    */

    'cache' => [
        'enabled' => true,
        'store' => null,
        'prefix' => 'oi-settings',
        'ttl' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Type registry
    |--------------------------------------------------------------------------
    |
    | Maps a setting "type" to the way its value is cast when read from and
    | written to the database. A type is either:
    |
    |   - a primitive keyword: string | integer | float | boolean | json
    |   - a Spatie\LaravelData\Data class (a typed value object) stored as JSON
    |
    | Add your own value objects here to make them first-class setting types.
    |
    */

    'default_type' => 'string',

    'types' => [
        'string' => 'string',
        'integer' => 'integer',
        'float' => 'float',
        'boolean' => 'boolean',
        'json' => 'json',
        'mail' => MailContent::class,
    ],

];
