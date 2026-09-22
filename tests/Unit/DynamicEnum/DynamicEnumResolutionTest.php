<?php

/**
 * Phase 3 resolution tests are deferred to Phase 3R.
 *
 * Phase 2R replaced whole-definition school replacement with sparse option overlays.
 * The Phase 3 resolver/validator must be redesigned to merge tenant baseline + school
 * overlay by value. Those tests live in Phase 3R, not here.
 */

uses(Tests\TestCase::class);

test('phase 3 resolution is deferred to Phase 3R after sparse overlay model', function () {
    expect(true)->toBeTrue();
})->group('phase3r-pending');
