<?php

namespace App\States\Academic\AcademicSession;

use App\States\Academic\AcademicSessionState;

class Paused extends AcademicSessionState
{
    public static string $name = 'paused';

    public function label(): string
    {
        return 'Paused';
    }
}
