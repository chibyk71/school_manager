<?php

namespace App\States\Promotion;

class Executing extends PromotionBatchStatus
{
    public static string $name = 'executing';

    public function label(): string
    {
        return 'Executing';
    }
}
