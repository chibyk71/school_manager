<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Laratrust\Models\Role as RoleModel;

class Role extends RoleModel
{
    use Filterable, Sortable;
    public $guarded = [];
}
