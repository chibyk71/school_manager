<?php

namespace App\Http\Resources\FileManager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe File metadata. Never includes storage_key or provider secrets.
 *
 * @mixin \App\Models\FileManager\File
 */
class FileResource extends JsonResource
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
            'folder_id' => $this->folder_id,
            'storage_provider_id' => $this->storage_provider_id,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'managed_by' => $this->managed_by instanceof \BackedEnum ? $this->managed_by->value : $this->managed_by,
            'module_code' => $this->module_code,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
