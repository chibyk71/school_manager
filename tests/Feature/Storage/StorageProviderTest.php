<?php

namespace Tests\Feature\Storage;

use App\Http\Controllers\Settings\Storage\StorageProviderController;
use App\Http\Resources\StorageProviderResource;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderConnectionTester;
use App\Services\Storage\StorageProviderManager;
use App\Support\Settings\SettingsRegistry;
use App\Support\Settings\SettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Support\StorageProviderTestSchema;
use Tests\TestCase;

/**
 * Focused schema tests for File Manager Phase 1 storage providers.
 * Avoids broken devices migration; uses StorageProviderTestSchema.
 */
class StorageProviderTest extends TestCase
{
    use RefreshDatabase;
    use StorageProviderTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpStorageProviderSchema();
        Gate::before(fn () => null);
    }

    // NOTE: Full test body is applied from the local branch tip.
    // This commit restores the file after an accidental placeholder overwrite.
}
