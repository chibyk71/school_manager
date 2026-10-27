<?php

/**
 * Fail CI if the concurrent capacity test was missing, skipped, incomplete, or failed.
 *
 * Usage: php scripts/assert-school-capacity-concurrency-junit.php path/to/junit.xml
 */

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php assert-school-capacity-concurrency-junit.php <junit.xml>\n");
    exit(2);
}

$path = $argv[1];
if (! is_file($path)) {
    fwrite(STDERR, "JUnit file not found: {$path}\n");
    exit(2);
}

$xml = @simplexml_load_file($path);
if ($xml === false) {
    fwrite(STDERR, "Unable to parse JUnit XML: {$path}\n");
    exit(2);
}

$target = 'test_concurrent_create_draft_cannot_exceed_limit';
$found = null;

$cases = $xml->xpath('//testcase') ?: [];
foreach ($cases as $case) {
    $name = (string) ($case['name'] ?? '');
    if ($name === $target || str_ends_with($name, $target)) {
        $found = $case;
        break;
    }
}

if ($found === null) {
    fwrite(STDERR, "Required test not discovered: {$target}\n");
    exit(1);
}

if (isset($found->skipped)) {
    fwrite(STDERR, "Required concurrency test was SKIPPED — Gate B not verified.\n");
    fwrite(STDERR, (string) $found->skipped."\n");
    exit(1);
}

if (isset($found->error)) {
    fwrite(STDERR, "Required concurrency test had ERROR.\n");
    fwrite(STDERR, (string) $found->error."\n");
    exit(1);
}

if (isset($found->failure)) {
    fwrite(STDERR, "Required concurrency test FAILED.\n");
    fwrite(STDERR, (string) $found->failure."\n");
    exit(1);
}

echo "OK: {$target} executed and passed (not skipped).\n";
exit(0);
