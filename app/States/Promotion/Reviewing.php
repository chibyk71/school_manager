<?php

namespace App\States\Promotion;

class Reviewing extends PromotionBatchStatus
{
    public static string $name = 'reviewing';

    public function label(): string
    {
        return 'Under Review';
    }
}
