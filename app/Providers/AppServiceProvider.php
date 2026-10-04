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

        // School Manager permission identities (permissions.name ∪ catalogue).
        // Resolved once per process and shared by the Gate bridge and Docent.
        // The catalogue is the developer-owned source of truth; live DB rows are
        // merged so seeded or runtime-inserted permissions are included without
        // shrinking the surface when the table is only partially populated.
        $permissionAbilities = null;
        $resolvePermissionAbilities = function () use (&$permissionAbilities): array {
            if ($permissionAbilities !== null) {
                return $permissionAbilities;
            }

            $names = [];

            foreach (glob(database_path('seeders/Settings/permission_catalogue_part*.php')) ?: [] as $file) {
                $rows = require $file;
                if (! is_array($rows)) {
                    continue;
                }
                foreach ($rows as $row) {
                    if (is_array($row) && isset($row['name']) && is_string($row['name'])) {
                        $names[] = $row['name'];
                    }
                }
            }

            try {
                $fromDb = Permission::query()
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();
                if ($fromDb !== []) {
                    $names = array_merge($names, $fromDb);
                }
            } catch (\Throwable) {
                // Table may not exist yet (migrations / focused test schema).
            }

            $names = array_values(array_unique($names));
            sort($names);

            return $permissionAbilities = $names;
        };

        // Bridge Docent's Gate-backed page authorization to the application
        // Authorization contract — but only for known School Manager permission
        // identities. Docent asks Gate::forUser($user)->allows($ability); we
        // resolve those through AuthorizationService so tenant/school roles,
        // direct grants, shadowing, and disabled roles remain authoritative.
        // Any other ability (including dotted non-permission Gate names) returns
        // null so Laravel continues to policies / defined gates unchanged.
        Gate::before(function ($user, string $ability, array $arguments = []) use ($resolvePermissionAbilities) {
            if (! $user instanceof User) {
                return null;
            }

            $permissions = $resolvePermissionAbilities();
            if ($permissions === [] || ! in_array($ability, $permissions, true)) {
                return null;
            }

            /** @var Authorization $authorization */
            $authorization = app(Authorization::class);

            return $authorization->allows($user, $ability);
        });

        // Dynamic ability surface for Docent validation / :::can / authorize:
        // same authoritative permission identity list as the Gate bridge.
        if (class_exists(Docent::class)) {
            Docent::abilities($resolvePermissionAbilities);
        }

        Vite::prefetch(concurrency: 3);
    }
}
