<?php

declare(strict_types=1);

namespace App\Traits;

use App\Scopes\ParentModelScope;


trait BelongsToPrimaryModel
{
    abstract public function getRelationshipToPrimaryModel(): string;

    public static function bootBelongsToPrimaryModel()
    {
        static::addGlobalScope(new ParentModelScope);
    }
}
