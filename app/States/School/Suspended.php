<?php

namespace App\States\School;

class Suspended extends SchoolState
{
    public static string $name = 'suspended';

    public function label(): string
    {
        return 'Suspended';
    }
}
