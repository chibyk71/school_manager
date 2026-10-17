<?php

namespace App\Http\Resources\FileManager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FileManager\Folder */
class FolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ownership' => $this->ownership instanceof \BackedEnum ? $this->ownership->value : $this->ownership,
            'school_id' => $this->school_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'module_code' => $this->module_code,
            'default_storage_provider_id' => $this->default_storage_provider_id,
            'capabilities' => $this->capabilityList(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
