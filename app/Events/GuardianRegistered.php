<?php

namespace App\Events;

use App\Models\Guardian;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuardianRegistered
{
    use Dispatchable, SerializesModels;

    public $guardian;
    public $loginCreated;

    public function __construct(Guardian $guardian, bool $loginCreated)
    {
        $this->guardian = $guardian;
        $this->loginCreated = $loginCreated;
    }
}
