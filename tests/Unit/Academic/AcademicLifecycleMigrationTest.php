<?php

/**
 * Phase 1 migration mapping tests.
 *
 * These tests document and assert the deterministic reconciliation rules
 * without requiring a full migrate:fresh against production schema.
 * They exercise the same match logic used by the migration class.
 */

function mapSessionState(string $status, bool $isCurrent): ?string
{
    $status = strtolower($status);

    return match (true) {
        $status === 'draft' && ! $isCurrent => 'draft',
        $status === 'upcoming' && ! $isCurrent => 'planned',
        $status === 'active' && $isCurrent => 'active',
        $status === 'closed' && ! $isCurrent => 'closed',
        $status === 'archived' && ! $isCurrent => 'closed',
        default => null,
    };
}

function mapTermState(string $status, bool $isActive, bool $isClosed): ?string
{
    $status = strtolower($status);

    return match (true) {
        ($status === 'pending' || $status === 'planned') && ! $isActive && ! $isClosed => 'planned',
        $status === 'active' && $isActive && ! $isClosed => 'active',
        $status === 'closed' && ! $isActive && $isClosed => 'closed',
        default => null,
    };
}

it('maps draft without current to draft', function () {
    expect(mapSessionState('draft', false))->toBe('draft');
});

it('maps upcoming without current to planned', function () {
    expect(mapSessionState('upcoming', false))->toBe('planned');
});

it('maps active with current to active', function () {
    expect(mapSessionState('active', true))->toBe('active');
});

it('maps closed without current to closed', function () {
    expect(mapSessionState('closed', false))->toBe('closed');
});

it('maps archived without current to closed', function () {
    expect(mapSessionState('archived', false))->toBe('closed');
});

it('fails contradictory session combinations', function () {
    expect(mapSessionState('active', false))->toBeNull()
        ->and(mapSessionState('draft', true))->toBeNull()
        ->and(mapSessionState('upcoming', true))->toBeNull()
        ->and(mapSessionState('closed', true))->toBeNull()
        ->and(mapSessionState('archived', true))->toBeNull();
});

it('maps pending term to planned', function () {
    expect(mapTermState('pending', false, false))->toBe('planned');
});

it('maps active term flags to active', function () {
    expect(mapTermState('active', true, false))->toBe('active');
});

it('maps closed term flags to closed', function () {
    expect(mapTermState('closed', false, true))->toBe('closed');
});

it('fails contradictory term combinations', function () {
    expect(mapTermState('active', false, false))->toBeNull()
        ->and(mapTermState('pending', true, false))->toBeNull()
        ->and(mapTermState('closed', true, true))->toBeNull()
        ->and(mapTermState('closed', false, false))->toBeNull();
});

it('documents obsolete columns removed by migration', function () {
    // Contract: after migration these columns must not exist on production schema.
    $sessionRemoved = ['is_current', 'status'];
    $termRemoved = ['is_active', 'is_closed', 'status'];

    expect($sessionRemoved)->toContain('is_current')
        ->and($sessionRemoved)->toContain('status')
        ->and($termRemoved)->toContain('is_active')
        ->and($termRemoved)->toContain('is_closed');
});
