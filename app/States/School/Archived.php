<?php

namespace App\States\School;

class Archived extends SchoolState
{
    public static string $name = 'archived';

    public function label(): string
    {
        return 'Archived';
    }
}
