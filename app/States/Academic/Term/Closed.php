<?php

namespace App\States\Academic\Term;

use App\States\Academic\TermState;

class Closed extends TermState
{
    public static string $name = 'closed';

    public function label(): string
    {
        return 'Closed';
    }
}
