<?php

namespace Tests\Unit\Entitlements;

use App\Entitlements\Definitions\FileManagerFeatures;
use App\Support\Entitlements\DefaultEntitlementProfile;
use App\Support\Entitlements\EntitlementRegistry;
use App\Support\Entitlements\EntitlementResolver;
use App\Support\Entitlements\EntitlementType;
use App\Support\Entitlements\Exceptions\InvalidEntitlementDefinitionException;
use App\Support\Entitlements\Exceptions\InvalidFeatureDefinitionException;
use App\Support\Entitlements\Exceptions\UnknownEntitlementException;
use App\Support\Entitlements\Exceptions\UnknownFeatureException;
use App\Support\Entitlements\FeatureDefinition;
use App\Support\Entitlements\EntitlementDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Registry registration, validation, and resolution contracts (no DB).
 */
final class EntitlementRegistryTest extends TestCase
{
    public function test_registers_file_manager_feature_and_discovers_it(): void
    {
        $registry = $this->productionRegistry();

        $this->assertTrue($registry->hasFeature(FileManagerFeatures::FEATURE_KEY));
        $this->assertTrue($registry->hasEntitlement(FileManagerFeatures::STORAGE_BYTES_KEY));

        $feature = $registry->getFeature(FileManagerFeatures::FEATURE_KEY);
        $this->assertSame('file_manager', $feature->module);
        $this->assertSame('File Manager Storage', $feature->name);
        $this->assertArrayHasKey(FileManagerFeatures::STORAGE_BYTES_KEY, $feature->entitlements);

        $entitlement = $registry->getEntitlement(FileManagerFeatures::STORAGE_BYTES_KEY);
        $this->assertSame(EntitlementType::StorageBytes, $entitlement->type);
        $this->assertSame(DefaultEntitlementProfile::FILE_MANAGER_STORAGE_BYTES, $entitlement->default);
    }

    public function test_rejects_duplicate_feature_keys(): void
    {
        $registry = new EntitlementRegistry();
        FileManagerFeatures::register($registry);

        $this->expectException(InvalidFeatureDefinitionException::class);
        FileManagerFeatures::register($registry);
    }

    public function test_rejects_conflicting_entitlement_keys(): void
    {
        $registry = new EntitlementRegistry();
        FileManagerFeatures::register($registry);

        $this->expectException(InvalidEntitlementDefinitionException::class);
        $registry->register(new FeatureDefinition(
            key: 'other.module',
            module: 'other',
            name: 'Other',
            description: 'Conflict test',
            entitlements: [
                FileManagerFeatures::STORAGE_BYTES_KEY => new EntitlementDefinition(
                    key: FileManagerFeatures::STORAGE_BYTES_KEY,
                    type: EntitlementType::StorageBytes,
                    default: 100,
                    label: 'Conflict',
                    description: 'Should fail',
                ),
            ],
        ));
    }

    public function test_rejects_empty_feature_metadata(): void
    {
        $registry = new EntitlementRegistry();

        $this->expectException(InvalidFeatureDefinitionException::class);
        $registry->register(new FeatureDefinition(
            key: 'bad.feature',
            module: '',
            name: 'Bad',
            description: 'Missing module',
        ));
    }

    public function test_rejects_invalid_default_type(): void
    {
        $registry = new EntitlementRegistry();

        $this->expectException(InvalidEntitlementDefinitionException::class);
        $registry->register(new FeatureDefinition(
            key: 'bad.storage',
            module: 'test',
            name: 'Bad Storage',
            description: 'Invalid default',
            entitlements: [
                'bad.storage_bytes' => new EntitlementDefinition(
                    key: 'bad.storage_bytes',
                    type: EntitlementType::StorageBytes,
                    default: 'not-a-number',
                    label: 'Bad',
                    description: 'Invalid',
                ),
            ],
        ));
    }

    public function test_rejects_registration_after_seal(): void
    {
        $registry = $this->productionRegistry();

        $this->expectException(InvalidFeatureDefinitionException::class);
        $registry->feature('after.seal', 'test', function ($f): void {
            $f->name('After')->description('Should fail');
        });
    }

    public function test_resolver_returns_exact_1_tib_default(): void
    {
        $registry = $this->productionRegistry();
        $resolver = new EntitlementResolver(
            $registry,
            [],
            new DefaultEntitlementProfile()
        );

        $value = $resolver->limit(FileManagerFeatures::STORAGE_BYTES_KEY);
        $this->assertSame(1_099_511_627_776, $value);
        $this->assertSame(DefaultEntitlementProfile::FILE_MANAGER_STORAGE_BYTES, $value);
    }

    public function test_resolver_rejects_unknown_entitlement(): void
    {
        $registry = $this->productionRegistry();
        $resolver = new EntitlementResolver($registry, [], new DefaultEntitlementProfile());

        $this->expectException(UnknownEntitlementException::class);
        $resolver->value('schools.max_count');
    }

    public function test_resolver_rejects_unknown_feature(): void
    {
        $registry = $this->productionRegistry();
        $resolver = new EntitlementResolver($registry, [], new DefaultEntitlementProfile());

        $this->expectException(UnknownFeatureException::class);
        $resolver->assertFeatureRegistered('student.bulk_enroll');
    }

    public function test_test_overrides_do_not_mutate_production_default(): void
    {
        $registry = $this->productionRegistry();
        $testProfile = new DefaultEntitlementProfile([
            FileManagerFeatures::STORAGE_BYTES_KEY => 1024,
        ]);
        $resolver = new EntitlementResolver($registry, [], $testProfile);

        $this->assertSame(1024, $resolver->limit(FileManagerFeatures::STORAGE_BYTES_KEY));

        $prod = new EntitlementResolver($registry, [], new DefaultEntitlementProfile());
        $this->assertSame(1_099_511_627_776, $prod->limit(FileManagerFeatures::STORAGE_BYTES_KEY));
    }

    public function test_repeated_bootstrap_is_deterministic(): void
    {
        $a = $this->productionRegistry();
        $b = $this->productionRegistry();

        $this->assertSame(
            array_keys($a->allFeatures()),
            array_keys($b->allFeatures())
        );
        $this->assertSame(
            array_keys($a->allEntitlements()),
            array_keys($b->allEntitlements())
        );
    }

    private function productionRegistry(): EntitlementRegistry
    {
        $registry = new EntitlementRegistry();
        FileManagerFeatures::register($registry);
        $registry->seal();

        return $registry;
    }
}
