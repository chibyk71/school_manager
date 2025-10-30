<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laratrust\Models\Role as RoleModel;

class Role extends RoleModel
{
    use Filterable, Sortable, HasUuids;
    public $guarded = [];
}
