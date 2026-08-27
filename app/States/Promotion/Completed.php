<?php

namespace App\States\Promotion;

class Completed extends PromotionBatchStatus
{
    public static string $name = 'completed';

    public function label(): string
    {
        return 'Completed';
    }
}
