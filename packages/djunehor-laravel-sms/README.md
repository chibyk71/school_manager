# djunehor/laravel-sms (path replacement)

Upstream `https://github.com/djunehor/laravel-sms` returns HTTP 404. Packagist still
lists the package but GitHub zipballs cannot be downloaded, which breaks `composer install`.

This path package satisfies Composer for version 2.2.0 with a minimal stub so the
application can install and boot. SMS delivery is owned by `App\Services\SmsService`.
