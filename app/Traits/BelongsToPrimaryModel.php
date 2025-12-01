<?php

declare(strict_types=1);

namespace App\Traits;
use Illuminate\Support\Facades\Log;

/**
 * Trait to associate models with a primary model (e.g., parent school or ministry).
 *
 * Applies a global scope to filter models based on their relationship to the primary model.
 * Models using this trait must implement `getRelationshipToPrimaryModel`.
 */
trait BelongsToPrimaryModel
{
    /**
     * Boot the trait by adding the ParentModelScope.
     *
     * @return void
     * @throws \Exception If relationship to primary model is invalid.
     */
    public static function bootBelongsToPrimaryModel(): void
    {
        try {
            $relationship = (new static())->getRelationshipToPrimaryModel();
            if (!method_exists(static::class, $relationship)) {
                throw new \Exception("Invalid relationship '{$relationship}' defined in " . static::class);
            }
            static::addGlobalScope(new \App\Models\Scopes\ParentModelScope());
        } catch (\Exception $e) {
            Log::error('Failed to boot BelongsToPrimaryModel: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the name of the relationship to the primary model.
     *
     * @return string The relationship method name (e.g., 'school').
     */
    abstract public function getRelationshipToPrimaryModel(): string;
}
