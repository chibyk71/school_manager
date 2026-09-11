<?php

namespace App\States\Academic\Term;

use App\States\Academic\TermState;

class Active extends TermState
{
    public static string $name = 'active';

    public function label(): string
    {
        return 'Active';
    }
}
