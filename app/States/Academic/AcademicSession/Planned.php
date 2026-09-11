<?php

namespace App\States\Academic\AcademicSession;

use App\States\Academic\AcademicSessionState;

class Planned extends AcademicSessionState
{
    public static string $name = 'planned';

    public function label(): string
    {
        return 'Planned';
    }
}
