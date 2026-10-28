<?php

namespace App\States\School;

class Draft extends SchoolState
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draft';
    }
}
