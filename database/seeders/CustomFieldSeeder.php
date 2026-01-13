<?php

namespace Database\Seeders;

use App\Models\CustomField;
use App\Support\CustomFieldType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * CustomFieldSeeder
 *
 * Seeds the initial/default custom fields that should exist in every tenant database.
 *
 * These are the "sensible defaults" for common resources (Student, Staff, Guardian, etc.).
 * All seeded fields have school_id = NULL → they are global/tenant-wide defaults.
 *
 * Schools and tenant admins can later override or add to them.
 *
 * How to use:
 *   - Run during tenant creation / initial setup
 *   - php artisan db:seed --class=CustomFieldSeeder
 *
 * Structure:
 *   - $defaultFieldsByResource = [ 'friendly_model_name' => [fields...] ]
 *   - Each field follows the exact format shown in the comment below
 */
class CustomFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This array is where you define all default fields per resource
        // Keys = friendly model name (resolved via ModelResolver)
        // Values = array of field definitions
        $defaultFieldsByResource = [
            'student' => [
                [
                    'name' => 'admission_number',
                    'label' => 'Admission Number',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['required', 'string', 'max:50', 'unique:students,admission_number'],
                    'placeholder' => 'e.g. SCH/2025/001',
                    'sort' => 10,
                    'required' => true,
                ],
                [
                    'name' => 'surname',
                    'label' => 'Surname',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['required', 'string', 'max:100'],
                    'sort' => 20,
                    'required' => true,
                ],
                [
                    'name' => 'first_name',
                    'label' => 'First Name',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['required', 'string', 'max:100'],
                    'sort' => 30,
                    'required' => true,
                ],
                [
                    'name' => 'other_names',
                    'label' => 'Other Names',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['nullable', 'string', 'max:100'],
                    'sort' => 40,
                ],
                [
                    'name' => 'gender',
                    'label' => 'Gender',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => ['Male', 'Female'],
                    'rules' => ['required', 'in:Male,Female'],
                    'sort' => 50,
                    'required' => true,
                ],
                [
                    'name' => 'date_of_birth',
                    'label' => 'Date of Birth',
                    'field_type' => CustomFieldType::DATE->value,
                    'rules' => ['required', 'date', 'before:today'],
                    'sort' => 60,
                    'required' => true,
                ],
                [
                    'name' => 'state_of_origin',
                    'label' => 'State of Origin',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => [
                        'Abia',
                        'Adamawa',
                        'Akwa Ibom',
                        'Anambra',
                        'Bauchi',
                        'Bayelsa',
                        'Benue',
                        'Borno',
                        'Cross River',
                        'Delta',
                        'Ebonyi',
                        'Edo',
                        'Ekiti',
                        'Enugu',
                        'Gombe',
                        'Imo',
                        'Jigawa',
                        'Kaduna',
                        'Kano',
                        'Katsina',
                        'Kebbi',
                        'Kogi',
                        'Kwara',
                        'Lagos',
                        'Nasarawa',
                        'Niger',
                        'Ogun',
                        'Ondo',
                        'Osun',
                        'Oyo',
                        'Plateau',
                        'Rivers',
                        'Sokoto',
                        'Taraba',
                        'Yobe',
                        'Zamfara',
                        'FCT'
                    ],
                    'rules' => ['required', 'string'],
                    'sort' => 70,
                    'required' => true,
                ],
                [
                    'name' => 'local_government_area',
                    'label' => 'Local Government Area',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['required', 'string', 'max:100'],
                    'sort' => 80,
                    'required' => true,
                ],
                [
                    'name' => 'religion',
                    'label' => 'Religion',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => ['Christianity', 'Islam', 'Traditional', 'Others'],
                    'rules' => ['nullable', 'string'],
                    'sort' => 90,
                ],
                [
                    'name' => 'denomination',
                    'label' => 'Denomination / Sect',
                    'field_type' => CustomFieldType::TEXT->value,
                    'placeholder' => 'e.g. Catholic, Deeper Life, Sunni, etc.',
                    'rules' => ['nullable', 'string', 'max:100'],
                    'sort' => 100,
                ],
                [
                    'name' => 'passport_photo',
                    'label' => 'Passport Photograph',
                    'field_type' => CustomFieldType::IMAGE->value,
                    'max_file_size_kb' => 2048,
                    'allowed_extensions' => ['jpg', 'jpeg', 'png'],
                    'file_type' => 'single',
                    'rules' => ['nullable', 'image', 'max:2048'],
                    'sort' => 110,
                ],
                [
                    'name' => 'class_on_admission',
                    'label' => 'Class on Admission',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => [
                        'Creche',
                        'Nursery 1',
                        'Nursery 2',
                        'Nursery 3',
                        'KG 1',
                        'KG 2',
                        'Year 1',
                        'Year 2',
                        'Year 3',
                        'Year 4',
                        'Year 5',
                        'Year 6',
                        'JSS 1',
                        'JSS 2',
                        'JSS 3',
                        'SSS 1',
                        'SSS 2',
                        'SSS 3'
                    ],
                    'rules' => ['required', 'string'],
                    'sort' => 120,
                    'required' => true,
                ],
                [
                    'name' => 'current_class',
                    'label' => 'Current Class',
                    'field_type' => CustomFieldType::TEXT->value,
                    'placeholder' => 'e.g. JSS 2A, SSS 1 Science',
                    'rules' => ['required', 'string', 'max:50'],
                    'sort' => 130,
                    'required' => true,
                ],
                [
                    'name' => 'house',
                    'label' => 'House',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => ['Red', 'Blue', 'Green', 'Yellow', 'None'],
                    'rules' => ['nullable', 'string'],
                    'sort' => 140,
                ],
                [
                    'name' => 'blood_group',
                    'label' => 'Blood Group',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'],
                    'rules' => ['nullable', 'string'],
                    'sort' => 150,
                ],
                [
                    'name' => 'genotype',
                    'label' => 'Genotype',
                    'field_type' => CustomFieldType::SELECT->value,
                    'options' => ['AA', 'AS', 'SS', 'AC', 'SC', 'Unknown'],
                    'rules' => ['nullable', 'string'],
                    'sort' => 160,
                ],
                [
                    'name' => 'medical_condition',
                    'label' => 'Any Medical Condition?',
                    'field_type' => CustomFieldType::TEXTAREA->value,
                    'placeholder' => 'e.g. Asthma, Epilepsy, Sickle Cell, None',
                    'rules' => ['nullable', 'string', 'max:500'],
                    'sort' => 170,
                ],
                [
                    'name' => 'next_of_kin_name',
                    'label' => 'Next of Kin Name',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['required', 'string', 'max:150'],
                    'sort' => 180,
                    'required' => true,
                ],
                [
                    'name' => 'next_of_kin_relationship',
                    'label' => 'Relationship to Next of Kin',
                    'field_type' => CustomFieldType::TEXT->value,
                    'rules' => ['required', 'string', 'max:50'],
                    'sort' => 190,
                    'required' => true,
                ],
                [
                    'name' => 'next_of_kin_phone',
                    'label' => 'Next of Kin Phone',
                    'field_type' => CustomFieldType::TEL->value,
                    'rules' => ['required', 'string', 'max:20', 'regex:/^([0-9]{10,15})$/'],
                    'sort' => 200,
                    'required' => true,
                ],
                [
                    'name' => 'next_of_kin_address',
                    'label' => 'Next of Kin Address',
                    'field_type' => CustomFieldType::TEXTAREA->value,
                    'rules' => ['nullable', 'string', 'max:500'],
                    'sort' => 210,
                ]
            ],
            // 'staff' => [
            //     [...],
            // ],
            // 'guardian' => [
            //     [...],
            // ],
            // Add more resources as needed
        ];

        // ------------------------------------------------------------------------
        // FORMAT THAT EACH FIELD ARRAY MUST FOLLOW
        // ------------------------------------------------------------------------
        // Every entry should be an associative array with these keys:
        //
        // [
        //     'name'              => string,          // snake_case unique key, e.g. 'date_of_birth'
        //     'label'             => string,          // Display name, e.g. 'Date of Birth'
        //     'field_type'        => string,          // Must be one of CustomFieldType::all(), e.g. 'date', 'text', 'select'
        //     'placeholder'       => string|null,     // Optional placeholder text
        //     'rules'             => array|null,      // Laravel rules, e.g. ['required', 'date']
        //     'options'           => array|null,      // For select/radio/checkbox/multiselect, e.g. ['Male', 'Female']
        //     'default_value'     => mixed|null,      // Default value if any
        //     'description'       => string|null,     // Longer tooltip/help text
        //     'hint'              => string|null,     // Short text under field
        //     'sort'              => integer,         // Display order, e.g. 10, 20, 30
        //     'category'          => string|null,     // Optional grouping, e.g. 'personal_info', 'medical'
        //     'preset_key'        => string|null,     // Optional: identifier for future grouping/filtering
        //     'required'          => boolean,         // Shortcut — will be added to rules if true
        //     // File/image specific (if field_type = 'file' or 'image')
        //     'max_file_size_kb'  => integer|null,
        //     'allowed_extensions'=> array|null,      // e.g. ['jpg', 'png', 'pdf']
        //     'file_type'         => string|null,     // 'single' or 'multiple'
        //     // Future/advanced (optional)
        //     'conditional_rules' => array|null,      // Visibility logic (json)
        //     'extra_attributes'  => array|null,      // Any custom metadata
        // ]
        //
        // Required minimum keys: name, label, field_type, sort
        // ------------------------------------------------------------------------

        DB::transaction(function () use ($defaultFieldsByResource) {
            foreach ($defaultFieldsByResource as $resourceAlias => $fields) {
                // Resolve friendly alias → FQCN (e.g. 'student' → App\Models\Student)
                $modelType = \App\Helpers\ModelResolver::getOrFail($resourceAlias);

                foreach ($fields as $fieldData) {
                    // Ensure required minimums
                    if (empty($fieldData['name']) || empty($fieldData['label']) || empty($fieldData['field_type'])) {
                        continue; // skip invalid entries
                    }

                    // Normalize & prepare
                    $fieldData['model_type'] = $modelType;
                    $fieldData['school_id'] = null; // always global defaults

                    // Auto-add 'required' rule if set
                    if (!empty($fieldData['required'])) {
                        $fieldData['rules'] = array_merge(
                            $fieldData['rules'] ?? [],
                            ['required']
                        );
                        unset($fieldData['required']); // cleanup
                    }

                    // Validate field_type is real
                    if (!CustomFieldType::isValid($fieldData['field_type'])) {
                        continue;
                    }

                    // Upsert so running multiple times is safe
                    CustomField::updateOrCreate(
                        [
                            'name' => $fieldData['name'],
                            'model_type' => $modelType,
                            'school_id' => null,
                        ],
                        $fieldData
                    );
                }
            }
        });
    }
}
