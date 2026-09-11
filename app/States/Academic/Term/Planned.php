<?php

namespace App\States\Academic\Term;

use App\States\Academic\TermState;

class Planned extends TermState
{
    public static string $name = 'planned';

    public function label(): string
    {
        return 'Planned';
    }
}
