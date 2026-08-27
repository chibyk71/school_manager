<?php

namespace App\States\Promotion;

class Approved extends PromotionBatchStatus
{
    public static string $name = 'approved';

    public function label(): string
    {
        return 'Approved';
    }
}
