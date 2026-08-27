<?php

namespace App\States\Promotion;

class Draft extends PromotionBatchStatus
{
    public static string $name = 'draft';

    public function label(): string
    {
        return 'Draft';
    }
}
