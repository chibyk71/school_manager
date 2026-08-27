<?php

/**
 * Temporary entry on feature/promotion-module after PLACEHOLDER overwrite.
 *
 * Promotion routes live in routes/promotion.php.
 * For a full app routes file (all modules), restore from master then keep promotion.php:
 *
 *   git show origin/master:routes/web.php > routes/web.php
 *   # ensure: require __DIR__ . '/promotion.php';
 *   # remove any old promotions reject/bulk-override routes from the master copy
 */

require __DIR__ . '/auth.php';
require __DIR__ . '/promotion.php';
