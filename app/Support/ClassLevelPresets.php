<?php

/**
 * ClassLevelPresets
 *
 * Single source of truth for all preset class level definitions used during
 * onboarding and bulk generation.
 *
 * Structure:
 * ─────────────────────────────────────────────────────────────────────────────
 * The preset tree has three levels of depth:
 *
 *   Curriculum (e.g. "Nigerian Curriculum")
 *     └── Group (e.g. "Primary School")
 *           └── Variant (e.g. "Primary 1–6" or "Primary 1–5")
 *                 └── levels[] (the actual ClassLevel rows to create)
 *
 * This maps directly to the cascade select UI in BulkGenerateModal.vue:
 *   - Top level  → CascadeSelect root options
 *   - Group      → First cascade panel
 *   - Variant    → Second cascade panel (leaf — selectable)
 *
 * Admin can select at two granularities:
 *   1. A whole group  (e.g. "Primary School" → auto-selects the standard variant)
 *   2. A specific variant (e.g. "Primary 1–5" for 5-year primary schools)
 *
 * Key decisions:
 * ─────────────────────────────────────────────────────────────────────────────
 * - Each level entry has `name` (what gets stored in DB) and `display_name`
 *   (the formal label, nullable on the model). The seeder data you already
 *   have maps directly — we just reorganise it into variants.
 *
 * - Sequences are NOT stored in the preset — they are assigned at generation
 *   time by ClassLevelService::bulkGenerate() based on existing levels in the
 *   section. This keeps presets simple and generation flexible.
 *
 * - Preset keys use dot notation: 'nigerian.primary_school.p1_6'
 *   The service resolves these via resolve($key).
 *
 * - resolve() returns null for unknown keys — the service converts that to a
 *   ValidationException with a user-friendly message.
 *
 * - toTree() returns the full nested structure for the frontend cascade select.
 *   The frontend only needs this shape — it never needs to know about variants
 *   internally, only the key to send back to the server.
 *
 * Fits into the module:
 * ─────────────────────────────────────────────────────────────────────────────
 * - ClassLevelService::bulkGenerate() calls resolve($key) to get the level list
 * - BulkGenerateModal.vue calls /class-levels/presets (or receives via Inertia
 *   page props) to get toTree() for the cascade select
 * - No DB dependency — pure static data, zero queries
 */

namespace App\Support;

class ClassLevelPresets
{
    /**
     * Full preset tree.
     *
     * Structure: curriculum → group_key → variant_key → { label, levels[] }
     * Each level: { name, display_name, description? }
     *
     * 'default' variant is used when admin selects the whole group
     * rather than drilling into a specific variant.
     */
    private static array $presets = [

        // ── Nigerian Curriculum ───────────────────────────────────────────────
        'nigerian' => [
            'label' => 'Nigerian Curriculum',

            'groups' => [

                'preschool' => [
                    'label' => 'Pre-School',
                    'default' => 'full',
                    'variants' => [
                        'full' => [
                            'label' => 'Creche, Nursery 1 & 2',
                            'levels' => [
                                ['name' => 'Creche', 'display_name' => 'Creche', 'description' => 'Early childhood care for infants and toddlers.'],
                                ['name' => 'Nursery 1', 'display_name' => 'Nursery 1', 'description' => 'First stage of preschool education.'],
                                ['name' => 'Nursery 2', 'display_name' => 'Nursery 2', 'description' => 'Second stage of preschool education.'],
                            ],
                        ],
                        'nursery_only' => [
                            'label' => 'Nursery 1 & 2 only',
                            'levels' => [
                                ['name' => 'Nursery 1', 'display_name' => 'Nursery 1', 'description' => 'First stage of preschool education.'],
                                ['name' => 'Nursery 2', 'display_name' => 'Nursery 2', 'description' => 'Second stage of preschool education.'],
                            ],
                        ],
                    ],
                ],

                'kindergarten' => [
                    'label' => 'Kindergarten',
                    'default' => 'full',
                    'variants' => [
                        'full' => [
                            'label' => 'KG 1 & KG 2',
                            'levels' => [
                                ['name' => 'Kindergarten 1', 'display_name' => 'Kindergarten 1', 'description' => 'Preparatory education before primary school.'],
                                ['name' => 'Kindergarten 2', 'display_name' => 'Kindergarten 2', 'description' => 'Final stage before primary education.'],
                            ],
                        ],
                        'kg1_only' => [
                            'label' => 'KG 1 only',
                            'levels' => [
                                ['name' => 'Kindergarten 1', 'display_name' => 'Kindergarten 1', 'description' => 'Preparatory education before primary school.'],
                            ],
                        ],
                    ],
                ],

                'primary_school' => [
                    'label' => 'Primary School',
                    'default' => 'p1_6',
                    'variants' => [
                        'p1_6' => [
                            'label' => 'Primary 1–6 (Standard)',
                            'levels' => [
                                ['name' => 'Primary 1', 'display_name' => 'Primary 1', 'description' => 'First year of primary education.'],
                                ['name' => 'Primary 2', 'display_name' => 'Primary 2', 'description' => 'Second year of primary education.'],
                                ['name' => 'Primary 3', 'display_name' => 'Primary 3', 'description' => 'Third year of primary education.'],
                                ['name' => 'Primary 4', 'display_name' => 'Primary 4', 'description' => 'Fourth year of primary education.'],
                                ['name' => 'Primary 5', 'display_name' => 'Primary 5', 'description' => 'Fifth year of primary education.'],
                                ['name' => 'Primary 6', 'display_name' => 'Primary 6', 'description' => 'Final year of primary education.'],
                            ],
                        ],
                        'p1_5' => [
                            'label' => 'Primary 1–5 (5-year variant)',
                            'levels' => [
                                ['name' => 'Primary 1', 'display_name' => 'Primary 1', 'description' => 'First year of primary education.'],
                                ['name' => 'Primary 2', 'display_name' => 'Primary 2', 'description' => 'Second year of primary education.'],
                                ['name' => 'Primary 3', 'display_name' => 'Primary 3', 'description' => 'Third year of primary education.'],
                                ['name' => 'Primary 4', 'display_name' => 'Primary 4', 'description' => 'Fourth year of primary education.'],
                                ['name' => 'Primary 5', 'display_name' => 'Primary 5', 'description' => 'Fifth year of primary education.'],
                            ],
                        ],
                        'p1_4' => [
                            'label' => 'Primary 1–4 (4-year variant)',
                            'levels' => [
                                ['name' => 'Primary 1', 'display_name' => 'Primary 1', 'description' => 'First year of primary education.'],
                                ['name' => 'Primary 2', 'display_name' => 'Primary 2', 'description' => 'Second year of primary education.'],
                                ['name' => 'Primary 3', 'display_name' => 'Primary 3', 'description' => 'Third year of primary education.'],
                                ['name' => 'Primary 4', 'display_name' => 'Primary 4', 'description' => 'Fourth year of primary education.'],
                            ],
                        ],
                    ],
                ],

                'junior_secondary_school' => [
                    'label' => 'Junior Secondary School',
                    'default' => 'jss1_3',
                    'variants' => [
                        'jss1_3' => [
                            'label' => 'JSS 1–3 (Standard)',
                            'levels' => [
                                ['name' => 'JSS 1', 'display_name' => 'JSS 1', 'description' => 'First year of junior secondary education.'],
                                ['name' => 'JSS 2', 'display_name' => 'JSS 2', 'description' => 'Second year of junior secondary education.'],
                                ['name' => 'JSS 3', 'display_name' => 'JSS 3', 'description' => 'Final year of junior secondary education.'],
                            ],
                        ],
                        'jss1_2' => [
                            'label' => 'JSS 1–2 (2-year variant)',
                            'levels' => [
                                ['name' => 'JSS 1', 'display_name' => 'JSS 1', 'description' => 'First year of junior secondary education.'],
                                ['name' => 'JSS 2', 'display_name' => 'JSS 2', 'description' => 'Second year of junior secondary education.'],
                            ],
                        ],
                    ],
                ],

                'senior_secondary_school' => [
                    'label' => 'Senior Secondary School',
                    'default' => 'sss1_3',
                    'variants' => [
                        'sss1_3' => [
                            'label' => 'SSS 1–3 (Standard)',
                            'levels' => [
                                ['name' => 'SSS 1', 'display_name' => 'SSS 1', 'description' => 'First year of senior secondary education.'],
                                ['name' => 'SSS 2', 'display_name' => 'SSS 2', 'description' => 'Second year of senior secondary education.'],
                                ['name' => 'SSS 3', 'display_name' => 'SSS 3', 'description' => 'Final year of senior secondary education.'],
                            ],
                        ],
                        'sss1_2' => [
                            'label' => 'SSS 1–2 (2-year variant)',
                            'levels' => [
                                ['name' => 'SSS 1', 'display_name' => 'SSS 1', 'description' => 'First year of senior secondary education.'],
                                ['name' => 'SSS 2', 'display_name' => 'SSS 2', 'description' => 'Second year of senior secondary education.'],
                            ],
                        ],
                    ],
                ],

                'adult_education' => [
                    'label' => 'Adult Education',
                    'default' => 'full',
                    'variants' => [
                        'full' => [
                            'label' => 'Basic → Advanced (Full)',
                            'levels' => [
                                ['name' => 'Basic Literacy', 'display_name' => 'Basic Literacy', 'description' => 'Literacy program for adults.'],
                                ['name' => 'Post-Literacy', 'display_name' => 'Post-Literacy', 'description' => 'Advanced reading and writing for adults.'],
                                ['name' => 'Continuing Education', 'display_name' => 'Continuing Education', 'description' => 'Further education for adult learners.'],
                                ['name' => 'Advanced Education', 'display_name' => 'Advanced Education', 'description' => 'Higher-level courses for adult students.'],
                            ],
                        ],
                        'basic_only' => [
                            'label' => 'Basic & Post-Literacy only',
                            'levels' => [
                                ['name' => 'Basic Literacy', 'display_name' => 'Basic Literacy', 'description' => 'Literacy program for adults.'],
                                ['name' => 'Post-Literacy', 'display_name' => 'Post-Literacy', 'description' => 'Advanced reading and writing for adults.'],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        // ── British Curriculum ────────────────────────────────────────────────
        'british' => [
            'label' => 'British Curriculum',

            'groups' => [

                'primary' => [
                    'label' => 'Primary (Key Stages 1 & 2)',
                    'default' => 'year1_6',
                    'variants' => [
                        'year1_6' => [
                            'label' => 'Year 1–6 (Standard)',
                            'levels' => [
                                ['name' => 'Year 1', 'display_name' => 'Year 1'],
                                ['name' => 'Year 2', 'display_name' => 'Year 2'],
                                ['name' => 'Year 3', 'display_name' => 'Year 3'],
                                ['name' => 'Year 4', 'display_name' => 'Year 4'],
                                ['name' => 'Year 5', 'display_name' => 'Year 5'],
                                ['name' => 'Year 6', 'display_name' => 'Year 6'],
                            ],
                        ],
                    ],
                ],

                'secondary' => [
                    'label' => 'Secondary (Key Stages 3, 4 & 5)',
                    'default' => 'year7_13',
                    'variants' => [
                        'year7_13' => [
                            'label' => 'Year 7–13 (Full Secondary)',
                            'levels' => [
                                ['name' => 'Year 7', 'display_name' => 'Year 7'],
                                ['name' => 'Year 8', 'display_name' => 'Year 8'],
                                ['name' => 'Year 9', 'display_name' => 'Year 9'],
                                ['name' => 'Year 10', 'display_name' => 'Year 10'],
                                ['name' => 'Year 11', 'display_name' => 'Year 11'],
                                ['name' => 'Year 12', 'display_name' => 'Year 12'],
                                ['name' => 'Year 13', 'display_name' => 'Year 13'],
                            ],
                        ],
                        'year7_11' => [
                            'label' => 'Year 7–11 (GCSE only)',
                            'levels' => [
                                ['name' => 'Year 7', 'display_name' => 'Year 7'],
                                ['name' => 'Year 8', 'display_name' => 'Year 8'],
                                ['name' => 'Year 9', 'display_name' => 'Year 9'],
                                ['name' => 'Year 10', 'display_name' => 'Year 10'],
                                ['name' => 'Year 11', 'display_name' => 'Year 11'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Resolve a preset variant by dot-notation key.
     *
     * Key format: '{curriculum}.{group}.{variant}'
     * Examples:
     *   'nigerian.primary_school.p1_6'
     *   'nigerian.junior_secondary_school.jss1_3'
     *   'british.secondary.year7_11'
     *
     * When admin selects a whole group (not a specific variant), the controller
     * appends the group's default variant key automatically before calling this.
     *
     * Returns null if the key cannot be resolved — the service converts this
     * to a ValidationException.
     *
     * @param  string $key  Dot-notation preset key
     * @return array{label: string, levels: array}|null
     */
    public static function resolve(string $key): ?array
    {
        [$curriculum, $group, $variant] = array_pad(explode('.', $key, 3), 3, null);

        if (!$curriculum || !$group || !$variant) {
            return null;
        }

        $variantData = static::$presets[$curriculum]['groups'][$group]['variants'][$variant] ?? null;

        if (!$variantData) {
            return null;
        }

        return [
            'label' => $variantData['label'],
            'levels' => $variantData['levels'],
        ];
    }

    /**
     * Resolve a group's default variant key.
     *
     * Called by the controller when the admin selects a whole group.
     * Returns the full dot-notation key for the default variant.
     *
     * Example:
     *   resolveDefaultKey('nigerian', 'primary_school')
     *   → 'nigerian.primary_school.p1_6'
     *
     * @param  string $curriculum  e.g. 'nigerian'
     * @param  string $group       e.g. 'primary_school'
     * @return string|null
     */
    public static function resolveDefaultKey(string $curriculum, string $group): ?string
    {
        $default = static::$presets[$curriculum]['groups'][$group]['default'] ?? null;

        if (!$default) {
            return null;
        }

        return "{$curriculum}.{$group}.{$default}";
    }

    /**
     * Return the full preset tree for the frontend cascade select.
     *
     * Shape consumed by BulkGenerateModal.vue PrimeVue CascadeSelect:
     * [
     *   {
     *     label: 'Nigerian Curriculum',
     *     key: 'nigerian',
     *     children: [
     *       {
     *         label: 'Primary School',
     *         key: 'nigerian.primary_school',
     *         children: [
     *           {
     *             label: 'Primary 1–6 (Standard)',
     *             key: 'nigerian.primary_school.p1_6',
     *             preview: ['Primary 1', 'Primary 2', ...],  ← shown in preview panel
     *             count: 6,
     *           },
     *           ...
     *         ]
     *       },
     *       ...
     *     ]
     *   },
     *   ...
     * ]
     *
     * @return array
     */
    public static function toTree(): array
    {
        $tree = [];

        foreach (static::$presets as $curriculumKey => $curriculum) {
            $curriculumNode = [
                'label' => $curriculum['label'],
                'key' => $curriculumKey,
                'children' => [],
            ];

            foreach ($curriculum['groups'] as $groupKey => $group) {
                $groupNode = [
                    'label' => $group['label'],
                    'key' => "{$curriculumKey}.{$groupKey}",
                    'defaultKey' => "{$curriculumKey}.{$groupKey}.{$group['default']}",
                    'children' => [],
                ];

                foreach ($group['variants'] as $variantKey => $variant) {
                    $fullKey = "{$curriculumKey}.{$groupKey}.{$variantKey}";

                    $groupNode['children'][] = [
                        'label' => $variant['label'],
                        'key' => $fullKey,
                        'preview' => array_column($variant['levels'], 'name'),
                        'count' => count($variant['levels']),
                    ];
                }

                $curriculumNode['children'][] = $groupNode;
            }

            $tree[] = $curriculumNode;
        }

        return $tree;
    }

    /**
     * Return all valid preset keys as a flat array.
     * Used by the controller to validate the incoming preset key.
     *
     * @return array<string>
     */
    public static function allKeys(): array
    {
        $keys = [];

        foreach (static::$presets as $curriculumKey => $curriculum) {
            foreach ($curriculum['groups'] as $groupKey => $group) {
                foreach ($group['variants'] as $variantKey => $_) {
                    $keys[] = "{$curriculumKey}.{$groupKey}.{$variantKey}";
                }
            }
        }

        return $keys;
    }
}
