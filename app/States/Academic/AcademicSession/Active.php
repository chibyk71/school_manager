<?php

namespace App\States\Academic\AcademicSession;

use App\States\Academic\AcademicSessionState;

class Active extends AcademicSessionState
{
    public static string $name = 'active';

    public function label(): string
    {
        return 'Active';
    }
}
