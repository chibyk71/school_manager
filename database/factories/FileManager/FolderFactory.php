<?php

namespace Database\Factories\FileManager;

use App\Enums\FileManager\Ownership;
use App\Models\FileManager\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'ownership' => Ownership::School,
            'school_id' => (string) Str::uuid(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'module_code' => null,
            'default_storage_provider_id' => null,
            'capabilities' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => [
            'ownership' => Ownership::System,
            'school_id' => null,
        ]);
    }
}
