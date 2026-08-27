<?php

namespace App\Events;

use App\Models\Staff;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffHired
{
    use Dispatchable, SerializesModels;

    public $staff;
    public $loginCreated;

    public function __construct(Staff $staff, bool $loginCreated)
    {
        $this->staff = $staff;
        $this->loginCreated = $loginCreated;
    }
}
