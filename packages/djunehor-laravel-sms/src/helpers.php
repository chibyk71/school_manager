<?php

// Upstream package helpers (stub). App uses App\Services\SmsService for sending.
if (! function_exists('send_sms')) {
    function send_sms($to, $message, $from = null)
    {
        return false;
    }
}
