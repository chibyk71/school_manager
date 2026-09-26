<?php

/**
 * Dynamic Enum Phase 2R — application definitions with tenant baseline options.
 *
 * Seeds global definitions (school_id = null) with normalized option rows.
 * Option school_id = null marks tenant baseline (sparse school overlays come later).
 */

namespace Database\Seeders;

use App\Models\DynamicEnum;
use App\Models\DynamicEnumOption;
use Illuminate\Database\Seeder;

class DynamicEnumSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'key' => 'profile.title',
                'label' => 'Title',
                'description' => 'Prefix that appears before a person\'s name (Mr, Mrs, Dr, …).',
                'options' => [
                    ['value' => 'Mr', 'label' => 'Mr'],
                    ['value' => 'Mrs', 'label' => 'Mrs'],
                    ['value' => 'Miss', 'label' => 'Miss'],
                    ['value' => 'Ms', 'label' => 'Ms'],
                    ['value' => 'Dr', 'label' => 'Dr'],
                    ['value' => 'Prof', 'label' => 'Prof'],
                    ['value' => 'Rev', 'label' => 'Rev'],
                    ['value' => 'Engr', 'label' => 'Engr'],
                ],
            ],
            [
                'key' => 'profile.gender',
                'label' => 'Gender',
                'description' => 'Gender identity options for staff, students and guardians.',
                'options' => [
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'other', 'label' => 'Other'],
                    ['value' => 'prefer_not', 'label' => 'Prefer not to say'],
                ],
            ],
            [
                'key' => 'profile.type',
                'label' => 'Profile Type',
                'description' => 'The role a profile represents inside the school.',
                'options' => [
                    ['value' => 'staff', 'label' => 'Staff / Teacher'],
                    ['value' => 'student', 'label' => 'Student'],
                    ['value' => 'guardian', 'label' => 'Parent / Guardian'],
                ],
            ],
            [
                'key' => 'address.type',
                'label' => 'Address Type',
                'description' => 'Classification of the address (residential, school campus, office, postal, temporary, billing).',
                'options' => [
                    ['value' => 'residential', 'label' => 'Residential'],
                    ['value' => 'school_campus', 'label' => 'School Campus'],
                    ['value' => 'office', 'label' => 'Office'],
                    ['value' => 'postal', 'label' => 'Postal'],
                    ['value' => 'temporary', 'label' => 'Temporary'],
                    ['value' => 'billing', 'label' => 'Billing'],
                ],
            ],
            [
                'key' => 'academic.subject_type',
                'label' => 'Subject Type',
                'description' => 'Classifies whether a subject is mandatory or optional for students.',
                'options' => [
                    ['value' => 'core', 'label' => 'Core', 'color' => 'bg-blue-100 text-blue-800'],
                    ['value' => 'elective', 'label' => 'Elective', 'color' => 'bg-purple-100 text-purple-800'],
                    ['value' => 'compulsory_elective', 'label' => 'Compulsory Elective', 'color' => 'bg-amber-100 text-amber-800'],
                    ['value' => 'extra_curricular', 'label' => 'Extra-Curricular', 'color' => 'bg-green-100 text-green-800'],
                ],
            ],
            [
                'key' => 'academic.subject_category',
                'label' => 'Subject Category',
                'description' => 'Groups subjects by academic discipline. Aligned with Nigerian curriculum structure.',
                'options' => [
                    ['value' => 'sciences', 'label' => 'Sciences', 'color' => 'bg-cyan-100 text-cyan-800'],
                    ['value' => 'arts_humanities', 'label' => 'Arts & Humanities', 'color' => 'bg-rose-100 text-rose-800'],
                    ['value' => 'commerce', 'label' => 'Commerce', 'color' => 'bg-yellow-100 text-yellow-800'],
                    ['value' => 'technical', 'label' => 'Technical & Vocational', 'color' => 'bg-orange-100 text-orange-800'],
                    ['value' => 'languages', 'label' => 'Languages', 'color' => 'bg-indigo-100 text-indigo-800'],
                    ['value' => 'social_sciences', 'label' => 'Social Sciences', 'color' => 'bg-teal-100 text-teal-800'],
                    ['value' => 'mathematics', 'label' => 'Mathematics', 'color' => 'bg-blue-100 text-blue-800'],
                    ['value' => 'religious_studies', 'label' => 'Religious Studies', 'color' => 'bg-violet-100 text-violet-800'],
                    ['value' => 'general', 'label' => 'General', 'color' => 'bg-gray-100 text-gray-700'],
                ],
            ],
            [
                'key' => 'guardian.relationship',
                'label' => 'Guardian Relationship',
                'description' => 'Relationship of a guardian to a student (stored on guardian_student pivot).',
                'options' => [
                    // Align with guardian_student migration domain vocabulary
                    ['value' => 'father', 'label' => 'Father'],
                    ['value' => 'mother', 'label' => 'Mother'],
                    ['value' => 'guardian', 'label' => 'Guardian'],
                    ['value' => 'grandparent', 'label' => 'Grandparent'],
                    ['value' => 'step_father', 'label' => 'Stepfather'],
                    ['value' => 'step_mother', 'label' => 'Stepmother'],
                    ['value' => 'foster_parent', 'label' => 'Foster Parent'],
                    ['value' => 'uncle', 'label' => 'Uncle'],
                    ['value' => 'aunt', 'label' => 'Aunt'],
                    ['value' => 'sibling', 'label' => 'Sibling'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $options = $definition['options'];
            unset($definition['options']);

            $enum = DynamicEnum::query()->firstOrCreate(
                [
                    'school_id' => null,
                    'key' => $definition['key'],
                ],
                [
                    'label' => $definition['label'],
                    'description' => $definition['description'] ?? null,
                ]
            );

            foreach ($options as $index => $option) {
                $canonicalValue = \App\Services\DynamicEnum\DynamicEnumValue::canonicalize($option['value']);
                DynamicEnumOption::query()->firstOrCreate(
                    [
                        'dynamic_enum_id' => $enum->id,
                        'school_id' => null,
                        'value' => $canonicalValue,
                    ],
                    [
                        'label' => $option['label'],
                        'sort_order' => $index,
                        'is_active' => true,
                        'is_required' => false,
                        'color' => $option['color'] ?? null,
                        'icon' => $option['icon'] ?? null,
                    ]
                );
            }
        }
    }
}
