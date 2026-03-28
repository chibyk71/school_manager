<?php

namespace App\Support;

/**
 * ClassSectionNamePresets
 *
 * Single source of truth for all arm naming presets available during
 * class section bulk generation.
 *
 * ── What This Is ──────────────────────────────────────────────────────────────
 * A pure static data class. No DB queries, no dependencies. Used by:
 *
 *   1. ClassSectionService::bulkGenerate()
 *      Receives already-resolved arm labels from the controller.
 *      The controller calls ClassSectionNamePresets::resolve() to turn
 *      (namingStyle, count) → ['A', 'B', 'C'].
 *
 *   2. BulkGenerateModal.vue (via the presets API endpoint)
 *      Gets toFrontendArray() to render the naming style selector and
 *      preview the arm names before confirming.
 *
 * ── Available Presets ─────────────────────────────────────────────────────────
 * - alphabetic  A, B, C, D, E, F, G, H, I, J  (10 max — more than enough for any school)
 * - numeric     1, 2, 3, 4, 5, 6, 7, 8, 9, 10
 * - precious    Diamond, Gold, Ruby, Sapphire, Emerald, Pearl, Silver, Topaz
 * - virtues     Excellence, Integrity, Diligence, Honour, Grace, Wisdom, Courage, Faith
 * - colours     Red, Blue, Green, Yellow, Purple, Orange, White, Black
 * - custom      (admin types their own — not resolved from this class)
 *
 * ── How Count Works ──────────────────────────────────────────────────────────
 * Each preset has a max list. resolve($style, $count) slices the first $count
 * items. If $count > preset length, throws ValidationException.
 *
 * For 'custom', the controller passes the typed arm names directly to
 * ClassSectionService::bulkGenerate() without calling this class.
 *
 * ── Usage Example ─────────────────────────────────────────────────────────────
 *
 *   // In BulkGenerateClassSectionRequest / controller:
 *   $arms = ClassSectionNamePresets::resolve('alphabetic', 3); // → ['A', 'B', 'C']
 *   $arms = ClassSectionNamePresets::resolve('precious', 2);   // → ['Diamond', 'Gold']
 *
 *   // Frontend (via API):
 *   $presets = ClassSectionNamePresets::toFrontendArray();
 */
class ClassSectionNamePresets
{
    /**
     * All available preset definitions.
     *
     * Key   = preset identifier (used in API requests and frontend select)
     * Value = [
     *   'label'       Human-readable name shown in the UI
     *   'description' Short description for the tooltip/helper text
     *   'arms'        The ordered list of arm labels (max items available)
     * ]
     */
    private const PRESETS = [
        'alphabetic' => [
            'label' => 'Alphabetic',
            'description' => 'Classic A, B, C labelling — the most common in Nigerian schools',
            'arms' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'],
        ],
        'numeric' => [
            'label' => 'Numeric',
            'description' => 'Number-based arms — e.g., JSS 1 (1), JSS 1 (2)',
            'arms' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
        ],
        'precious' => [
            'label' => 'Precious Stones',
            'description' => 'Named after gemstones — Diamond, Gold, Ruby, etc.',
            'arms' => ['Diamond', 'Gold', 'Ruby', 'Sapphire', 'Emerald', 'Pearl', 'Silver', 'Topaz'],
        ],
        'virtues' => [
            'label' => 'Virtues',
            'description' => 'Character-based names — Excellence, Integrity, Diligence, etc.',
            'arms' => ['Excellence', 'Integrity', 'Diligence', 'Honour', 'Grace', 'Wisdom', 'Courage', 'Faith'],
        ],
        'colours' => [
            'label' => 'Colours',
            'description' => 'Colour-based arms — Red, Blue, Green, Yellow, etc.',
            'arms' => ['Red', 'Blue', 'Green', 'Yellow', 'Purple', 'Orange', 'White', 'Black'],
        ],
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a preset to an array of arm labels.
     *
     * Returns the first $count arms from the preset list.
     * Throws ValidationException if:
     *   - The preset key is unknown
     *   - The requested count exceeds the available arms in the preset
     *
     * @param  string  $preset  Preset key: 'alphabetic', 'precious', etc.
     * @param  int     $count   Number of arms to generate (1–10)
     * @return array<string>   e.g. ['A', 'B', 'C']
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function resolve(string $preset, int $count): array
    {
        if (!isset(self::PRESETS[$preset])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'naming_style' => "Unknown naming style \"{$preset}\". " .
                    'Available: ' . implode(', ', self::allKeys()),
            ]);
        }

        $arms = self::PRESETS[$preset]['arms'];

        if ($count > count($arms)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'arm_count' => "The \"{$preset}\" preset only supports up to " .
                    count($arms) . " arms. Requested: {$count}.",
            ]);
        }

        if ($count < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'arm_count' => 'At least 1 arm must be requested.',
            ]);
        }

        return array_slice($arms, 0, $count);
    }

    /**
     * Get all valid preset keys.
     * Used for validation in BulkGenerateClassSectionRequest.
     *
     * @return array<string>  ['alphabetic', 'numeric', 'precious', ...]
     */
    public static function allKeys(): array
    {
        return array_keys(self::PRESETS);
    }

    /**
     * Get the maximum arm count available for a given preset.
     * Used for frontend validation (max value on the count input).
     *
     * @param  string  $preset
     * @return int|null  null if preset not found
     */
    public static function maxCount(string $preset): ?int
    {
        if (!isset(self::PRESETS[$preset])) {
            return null;
        }

        return count(self::PRESETS[$preset]['arms']);
    }

    /**
     * Export all presets to a frontend-consumable array.
     *
     * Shape consumed by BulkGenerateModal.vue:
     * [
     *   'alphabetic' => [
     *     'label'       => 'Alphabetic',
     *     'description' => '...',
     *     'arms'        => ['A', 'B', 'C', ...],
     *     'max_count'   => 10,
     *   ],
     *   ...
     * ]
     *
     * The 'arms' array is sent so the modal can show a live preview of
     * what names will be generated as the admin adjusts the count slider.
     *
     * @return array<string, array>
     */
    public static function toFrontendArray(): array
    {
        $result = [];

        foreach (self::PRESETS as $key => $preset) {
            $result[$key] = [
                'label' => $preset['label'],
                'description' => $preset['description'],
                'arms' => $preset['arms'],
                'max_count' => count($preset['arms']),
            ];
        }

        return $result;
    }

    /**
     * Check whether a given key is a valid preset.
     *
     * @param  string  $key
     * @return bool
     */
    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::PRESETS);
    }
}
