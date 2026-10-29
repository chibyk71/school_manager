<?php

namespace Djunehor\Sms\Concrete;

/**
 * Stub for config default provider class. Real sends go through App\Services\SmsService.
 */
class MultiTexter
{
    public function __construct(...$args)
    {
    }

    public function send($to, $message)
    {
        return false;
    }
}
