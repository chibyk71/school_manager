<?php

namespace App\States\Academic\AcademicSession;

use App\States\Academic\AcademicSessionState;

class Closed extends AcademicSessionState
{
    public static string $name = 'closed';

    public function label(): string
    {
        return 'Closed';
    }
}
