<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Ninja\DeviceTracker\Modules\Location\Contracts\LocationProvider;
use Ninja\DeviceTracker\Modules\Location\DTO\Location;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDeviceTrackerForHttpTests();
    }

    /**
     * HTTP Feature tests use Symfony's test client User-Agent ("Symfony").
     * diego-ninja/laravel-devices rejects unknown/bot UAs when
     * allow_unknown_devices / allow_bot_devices are false, which surfaces as
     * session errors ("Unknown device detected with user agent: Symfony")
     * and breaks login + post-login assertions.
     *
     * Production config stays strict; only the test process widens the
     * whitelist and stubs GeoIP so CI/agent environments without MaxMind DB
     * can create device sessions.
     */
    protected function configureDeviceTrackerForHttpTests(): void
    {
        if (! is_array(config('devices'))) {
            return;
        }

        config([
            'devices.user_agent_whitelist' => array_values(array_unique(array_merge(
                (array) config('devices.user_agent_whitelist', []),
                ['Symfony']
            ))),
            // Device package optional Google2FA bridge is unused; Laragear owns MFA.
            'devices.google_2fa_enabled' => false,
        ]);

        if (! interface_exists(LocationProvider::class) || ! class_exists(Location::class)) {
            return;
        }

        $this->app->instance(
            LocationProvider::class,
            new class implements LocationProvider
            {
                public function country(): ?string
                {
                    return null;
                }

                public function region(): ?string
                {
                    return null;
                }

                public function city(): ?string
                {
                    return null;
                }

                public function postal(): ?string
                {
                    return null;
                }

                public function latitude(): ?string
                {
                    return null;
                }

                public function longitude(): ?string
                {
                    return null;
                }

                public function timezone(): ?string
                {
                    return null;
                }

                public function locate(string $ip): Location
                {
                    return Location::fromArray(['ip' => $ip]);
                }
            }
        );
    }
}
