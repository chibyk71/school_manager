#!/usr/bin/env php
<?php
/**
 * Restore LifecycleOperationalService from the compressed Phase 7 payload.
 *
 * Run from repo root:
 *   php scripts/restore-lifecycle-ops-service.php
 *
 * Then commit the restored app/Services/Student/LifecycleOperationalService.php
 */
$root = dirname(__DIR__);
$b64Path = $root.'/storage/app/phase7_LifecycleOperationalService.auth-dates.php.gz.b64';
$target = $root.'/app/Services/Student/LifecycleOperationalService.php';
if (! is_file($b64Path)) {
    fwrite(STDERR, "Missing payload: {$b64Path}\n");
    exit(1);
}
$decoded = base64_decode(trim(file_get_contents($b64Path)), true);
if ($decoded === false) {
    fwrite(STDERR, "Invalid base64 payload\n");
    exit(1);
}
$php = gzdecode($decoded);
if ($php === false || ! str_contains($php, 'class LifecycleOperationalService')) {
    fwrite(STDERR, "Failed to decompress valid service\n");
    exit(1);
}
file_put_contents($target, $php);
echo "Restored {$target} (".strlen($php)." bytes)\n";
