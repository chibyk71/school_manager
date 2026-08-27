<?php

/**
 * Application HTTP routes (feature/promotion-module).
 *
 * Preferred: web.restored.php (full master route map minus legacy promotion
 * reject/bulk-override) + promotion.php.
 *
 * Until web.restored.php (+ parts) is present on the branch, fall back to
 * auth.php so the app still boots; promotion routes always load.
 *
 * To install the full map locally:
 *   cp path/to/web.restored.php routes/web.restored.php
 * or build from master and strip the old promotions block, then:
 *   require __DIR__ . '/promotion.php';
 */

$restored = __DIR__ . '/web.restored.php';
$part1 = __DIR__ . '/web.restored.part1.php';
$part2 = __DIR__ . '/web.restored.part2.php';

if (is_readable($restored) && is_readable($part1) && is_readable($part2)
    && filesize($part1) > 1000 && filesize($part2) > 1000) {
    require $restored;
} else {
    require __DIR__ . '/auth.php';
}

require __DIR__ . '/promotion.php';
