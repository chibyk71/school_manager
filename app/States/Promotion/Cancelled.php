<?php

namespace App\States\Promotion;

class Cancelled extends PromotionBatchStatus
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'Cancelled';
    }
}
