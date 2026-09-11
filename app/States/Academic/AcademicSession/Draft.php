<?php

namespace App\States\Academic\AcademicSession;

use App\States\Academic\AcademicSessionState;

class Draft extends AcademicSessionState
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draft';
    }
}
