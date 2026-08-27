<?php

namespace App\States\Promotion;

class Pending extends PromotionBatchStatus
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending Review';
    }
}
