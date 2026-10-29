<?php

namespace App\Providers;

use App\Contracts\Academic\AcademicSessionOperationalDataBoundary;
use App\Contracts\Academic\TermOperationalDataBoundary;
use App\Contracts\Authorization\Authorization;
use App\Entitlements\Definitions\FileManagerFeatures;
use App\Entitlements\Definitions\SchoolManagementFeatures;
use App\Listeners\Student\LogLifecycleNotificationDelivery;
use App\Models\DynamicEnum;
use App\Models\FileManager\File;
use App\Models\FileManager\Folder;
use App\Models\Permission;
use App\Models\Promotion\PromotionBatch;
use App\Models\StorageProvider;
use App\Models\Student\Enrollment;
use App\Models\Student\Student;
use App\Models\User;
use App\Policies\DynamicEnumPolicy;
use App\Policies\FileManager\FilePolicy;
use App\Policies\FileManager\FolderPolicy;
use App\Policies\Promotion\PromotionPolicy;
use App\Policies\StorageProviderPolicy;
use App\Policies\Student\EnrollmentPolicy;
use App\Policies\Student\StudentPolicy;
use App\Services\Academic\AcademicPeriodUsageRegistry;
use App\Services\Academic\RegistryAcademicSessionOperationalData;
use App\Services\Academic\RegistryTermOperationalData;
use App\Services\Permission\AuthorizationService;
use App\Settings\Definitions\AuthenticationSettings;
use App\Settings\Definitions\FileManagerSettings;
use App\Settings\Definitions\SchoolOperationalSettings;
use App\Support\Entitlements\CapacityEvaluator;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\EntitlementRegistry;
use App\Support\Entitlements\EntitlementResolver;
use App\Support\Entitlements\FeatureAccess;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use STS\Docent\Facades\Docent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRegistry::class, function () {
            $registry = new SettingsRegistry;
            AuthenticationSettings::register($registry);
            FileManagerSettings::register($registry);
            SchoolOperationalSettings::register($registry);
            $registry->seal();

            return $registry;
        });

        $this->app->singleton(SettingsResolver::class, function ($app) {
            return new SettingsResolver(
                $app->make(SettingsRegistry::class)
            );
        });

        // Feature & Entitlement Registry (Phase 1)
        $this->app->singleton(EntitlementRegistry::class, function () {
            $registry = new EntitlementRegistry;
            FileManagerFeatures::register($registry);
            SchoolManagementFeatures::register($registry);
            $registry->seal();

            return $registry;
        });

        $this->app->singleton(DefaultEntitlementProfile::class, function () {
            return new DefaultEntitlementProfile;
        });

        $this->app->singleton(EntitlementResolver::class, function ($app) {
            return new EntitlementResolver(
                $app->make(EntitlementRegistry::class),
                [], // future SaaS providers bind here, highest priority first
                $app->make(DefaultEntitlementProfile::class),
            );
        });

        $this->app->singleton(FeatureAccess::class, function ($app) {
            return new FeatureAccess(
                $app->make(EntitlementResolver::class)
            );
        });

        $this->app->singleton(CapacityEvaluator::class, function ($app) {
            return new CapacityEvaluator(
                $app->make(EntitlementResolver::class)
            );
        });

        $this->app->bind(
            Authorization::class,
            AuthorizationService::class
        );

        $this->app->bind(
            AcademicSessionOperationalDataBoundary::class,
            RegistryAcademicSessionOperationalData::class
        );

        $this->app->bind(
            TermOperationalDataBoundary::class,
            RegistryTermOperationalData::class
        );

        $this->app->singleton(
            AcademicPeriodUsageRegistry::class
        );
    }

    public function boot(): void
    {
        Event::listen(
            NotificationSent::class,
            [LogLifecycleNotificationDelivery::class, 'handleSent']
        );
        Event::listen(
            NotificationFailed::class,
            [LogLifecycleNotificationDelivery::class, 'handleFailed']
        );

        Gate::policy(
            PromotionBatch::class,
            PromotionPolicy::class
        );

        Gate::policy(
            Enrollment::class,
            EnrollmentPolicy::class
        );

        Gate::policy(
            Student::class,
            StudentPolicy::class
        );
        Gate::policy(
            \App\Models\Academic\Student::class,
            StudentPolicy::class
        );

        Gate::policy(
            DynamicEnum::class,
            DynamicEnumPolicy::class
        );

        Gate::policy(
            StorageProvider::class,
            StorageProviderPolicy::class
        );

        Gate::policy(
            Folder::class,
            FolderPolicy::class
        );
        Gate::policy(
            File::class,
            FilePolicy::class
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
