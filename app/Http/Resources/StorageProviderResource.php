<?php

namespace App\Http\Resources;

use App\Enums\StorageProviderDriver;
use App\Models\StorageProvider;
use App\Services\Storage\StorageProviderCapabilityReporter;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe serialization of StorageProvider — secrets are never exposed.
 *
 * @mixin StorageProvider
 */
class StorageProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reporter = new StorageProviderCapabilityReporter;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver instanceof BackedEnum ? $this->driver->value : $this->driver,
            'driver_label' => $this->driver instanceof StorageProviderDriver
                ? $this->driver->label()
                : (string) $this->driver,
            'ownership' => $this->ownership instanceof BackedEnum ? $this->ownership->value : $this->ownership,
            'school_id' => $this->school_id,
            'config' => $this->config ?? [],
            'secrets_configured' => $this->hasSecretsConfigured(),
            'enabled' => (bool) $this->enabled,
            'capabilities' => $reporter->report(
                $this->driver instanceof StorageProviderDriver
                    ? $this->driver
                    : StorageProviderDriver::from((string) $this->driver),
                $this->config ?? []
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
