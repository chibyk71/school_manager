<?php

return [
    'default' => \Djunehor\Sms\Concrete\MultiTexter::class,
    'sender' => env('SMS_SENDER', 'MyApp'),
    // This now returns school-specific config at runtime!
    // 'providers' => \App\Services\SmsConfigResolver::resolve()
];
