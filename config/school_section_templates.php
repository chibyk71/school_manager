<?php

// config/school_section_templates.php

/**
 * Predefined School Section Templates
 *
 * ── Purpose ─────────────────────────────────────────────────────────────
 * This is the single source of truth for all predefined academic division
 * templates available to schools during setup and in the Settings > Sections
 * page. It is a PHP config file — nothing in this array is committed to the
 * database directly. Templates only become real SchoolSection records when
 * a school explicitly selects them.
 *
 * ── How It Is Used ──────────────────────────────────────────────────────
 * 1. SchoolSectionController::templates()
 *    Returns available templates (filtered to exclude already-used names)
 *    via GET /settings/sections/templates endpoint.
 *
 * 2. SchoolSectionService::createFromTemplates()
 *    Spreads array entries directly into SchoolSection::create() calls.
 *    Field names here MUST match SchoolSection $fillable columns exactly.
 *
 * 3. StoreFromTemplatesRequest validation
 *    Rule::in(array_column(config('school_section_templates'), 'name'))
 *    Ensures submitted template names are legitimate — prevents arbitrary
 *    name injection from the frontend.
 *
 * 4. getAvailableTemplates() private helper in controller
 *    Filters this list against existing school section names to show only
 *    templates the school has not yet added.
 *
 * ── What This Is NOT ────────────────────────────────────────────────────
 * - Not a database seeder — nothing is written to DB from here directly
 * - Not shared via Inertia shared props — loaded only when endpoint is hit
 * - Not the frontend data source — TypeScript file holds type definitions only
 * - Not a replacement for SchoolSection records — schools own their DB rows
 *
 * ── Field Reference ─────────────────────────────────────────────────────
 * All fields must match SchoolSection $fillable exactly:
 *   name         → canonical slug, unique per school, snake_case
 *   display_name → human-readable UI label
 *   short_code   → required abbreviation for reports/badges/dropdowns
 *   description  → optional tooltip/preview text
 *   sort_order   → display order (lower = first); consistent with
 *                  CustomField, Grade, DynamicEnum models in this codebase
 *
 * When a school creates a section from a template, source='template' is
 * injected by SchoolSectionService — it is not stored here.
 * If the school later edits that section, SchoolSectionObserver changes
 * source to 'custom' automatically.
 *
 * ── Context ─────────────────────────────────────────────────────────────
 * Designed for Nigerian K-12 school management. Covers the full spectrum
 * from early childhood through post-secondary streams. Adult education is
 * intentionally excluded — that use case warrants a separate tenant type.
 *
 * ── Adding New Templates ─────────────────────────────────────────────────
 * 1. Add the entry to this array with a unique `name` value
 * 2. Assign a sort_order that fits its position in the academic hierarchy
 * 3. Existing schools are unaffected — they only see new templates if they
 *    haven't already created a section with the same `name`
 * 4. No migration needed — this is config, not schema
 *
 * @see App\Services\SchoolSectionService::createFromTemplates()
 * @see App\Http\Controllers\Settings\SchoolSectionController::templates()
 * @see App\Http\Requests\StoreFromTemplatesRequest
 */

return [

    [
        'name' => 'pre_nursery',
        'display_name' => 'Pre-Nursery',
        'short_code' => 'PN',
        'description' => 'Very early childhood education, typically ages 2–3. '
            . 'Common in private Nigerian schools as a distinct administrative unit '
            . 'with separate fee structures and timetables.',
        'sort_order' => 1,
    ],

    [
        'name' => 'nursery',
        'display_name' => 'Nursery',
        'short_code' => 'NUR',
        'description' => 'Early childhood education, typically ages 3–5 (Nursery 1–3). '
            . 'Covers foundational literacy, numeracy, and social development.',
        'sort_order' => 2,
    ],

    [
        'name' => 'primary',
        'display_name' => 'Primary',
        'short_code' => 'PRI',
        'description' => 'Primary/elementary education, typically Primary 1–6. '
            . 'Culminates in the Primary School Leaving Certificate (PSLC) examination.',
        'sort_order' => 3,
    ],

    [
        'name' => 'junior_secondary',
        'display_name' => 'Junior Secondary School',
        'short_code' => 'JSS',
        'description' => 'Junior secondary education, JSS 1–3. '
            . 'Culminates in the Basic Education Certificate Examination (BECE) '
            . 'or Junior WAEC.',
        'sort_order' => 4,
    ],

    [
        'name' => 'senior_secondary',
        'display_name' => 'Senior Secondary School',
        'short_code' => 'SSS',
        'description' => 'Senior secondary education, SS 1–3. Covers Science, '
            . 'Commercial, and Arts streams. Culminates in WAEC/NECO examinations.',
        'sort_order' => 5,
    ],

    [
        'name' => 'technical_college',
        'display_name' => 'Technical / Vocational College',
        'short_code' => 'TC',
        'description' => 'Technical and vocational training programmes, typically at '
            . 'senior secondary level. Focuses on trade skills, craft, and '
            . 'applied technology qualifications.',
        'sort_order' => 6,
    ],

    [
        'name' => 'international',
        'display_name' => 'International / British Education',
        'short_code' => 'IBE',
        'description' => 'International curriculum stream (Cambridge IGCSE, British curriculum, '
            . 'or IB-style). Common in private schools in Lagos, Abuja, and '
            . 'other major cities serving expatriate and premium local markets.',
        'sort_order' => 7,
    ],

    [
        'name' => 'sixth_form',
        'display_name' => 'Sixth Form / A-Level',
        'short_code' => 'SF',
        'description' => 'Post-WAEC advanced level programme (A-Levels, Cambridge AS/A2, '
            . 'or equivalent). Typically two years, preparing students for '
            . 'direct university admission.',
        'sort_order' => 8,
    ],

];
