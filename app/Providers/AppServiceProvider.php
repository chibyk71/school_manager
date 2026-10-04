<?php

namespace App\Providers;

use App\Contracts\Authorization\Authorization;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use STS\Docent\Facades\Docent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            Authorization::class,
            \App\Services\Permission\AuthorizationService::class
        );

        $this->app->bind(
            \App\Contracts\Academic\AcademicSessionOperationalDataBoundary::class,
            \App\Services\Academic\RegistryAcademicSessionOperationalData::class
        );

        $this->app->bind(
            \App\Contracts\Academic\TermOperationalDataBoundary::class,
            \App\Services\Academic\RegistryTermOperationalData::class
        );

        $this->app->singleton(
            \App\Services\Academic\AcademicPeriodUsageRegistry::class
        );
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSent::class,
            [\App\Listeners\Student\LogLifecycleNotificationDelivery::class, 'handleSent']
        );
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationFailed::class,
            [\App\Listeners\Student\LogLifecycleNotificationDelivery::class, 'handleFailed']
        );

        Gate::policy(
            \App\Models\Promotion\PromotionBatch::class,
            \App\Policies\Promotion\PromotionPolicy::class
        );

        Gate::policy(
            \App\Models\Student\Enrollment::class,
            \App\Policies\Student\EnrollmentPolicy::class
        );

        Gate::policy(
            \App\Models\Student\Student::class,
            \App\Policies\Student\StudentPolicy::class
        );
        Gate::policy(
            \App\Models\Academic\Student::class,
            \App\Policies\Student\StudentPolicy::class
        );

        Gate::policy(
            \App\Models\DynamicEnum::class,
            \App\Policies\DynamicEnumPolicy::class
        );

        // Bridge Docent's Gate-backed page authorization to the application
        // Authorization contract. Docent asks Gate::forUser($user)->allows($ability);
        // we resolve permission identities through AuthorizationService so
        // tenant/school roles, direct grants, shadowing, and disabled roles
        // remain authoritative. Return null for non-permission abilities so
        // Laravel continues to policies / defined gates.
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (! $user instanceof User) {
                return null;
            }

            // Only intercept permission-style ability names (catalogue identities).
            // Policies use model class abilities; leave those alone.
            if (! str_contains($ability, '.')) {
                return null;
            }

            /** @var Authorization $authorization */
            $authorization = app(Authorization::class);

            return $authorization->allows($user, $ability);
        });

        // Dynamic ability surface for Docent validation / :::can / authorize:
        // Prefer live permissions.name rows; fall back to the developer-owned
        // catalogue PHP parts so docent:check works before seed.
        if (class_exists(Docent::class)) {
            Docent::abilities(function (): array {
                try {
                    $names = Permission::query()
                        ->orderBy('name')
                        ->pluck('name')
                        ->all();
                    if ($names !== []) {
                        return $names;
                    }
                } catch (\Throwable) {
                    // Table may not exist yet.
                }

                $fromCatalogue = [];
                foreach (glob(database_path('seeders/Settings/permission_catalogue_part*.php')) ?: [] as $file) {
                    $rows = require $file;
                    if (! is_array($rows)) {
                        continue;
                    }
                    foreach ($rows as $row) {
                        if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
                            $fromCatalogue[] = $row['name'];
                        }
                    }
                }

                $fromCatalogue = array_values(array_unique($fromCatalogue));
                sort($fromCatalogue);

                return $fromCatalogue;
            });
        }

        Vite::prefetch(concurrency: 3);
    }
}
