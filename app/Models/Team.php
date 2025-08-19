<?php

namespace App\Models;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Laratrust\Models\Team as LaratrustTeam;

class Team extends LaratrustTeam
{
    use Filterable, Sortable;
    public $guarded = [];
}
