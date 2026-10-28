<?php

namespace App\States\School;

class Active extends SchoolState
{
    public static string $name = 'active';

    public function label(): string
    {
        return 'Active';
    }
}
