<?php

namespace App\States\School;

class Ready extends SchoolState
{
    public static string $name = 'ready';

    public function label(): string
    {
        return 'Ready';
    }
}
