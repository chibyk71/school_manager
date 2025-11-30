<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuration\Config;

class ConfigSeeder extends Seeder
{
    public function run()
    {
        $configs = [
            // 1. Title
            [
                'label' => 'Title',
                'name' => 'title',
                'applies_to' => \App\Models\Profile::class,
                'description' => 'Prefix that appears before a person\'s name (Mr, Mrs, Dr, …).',
                'color' => 'bg-indigo-100 text-indigo-800',
                'options' => [
                    'Mr' => 'Mr',
                    'Mrs' => 'Mrs',
                    'Miss' => 'Miss',
                    'Ms' => 'Ms',
                    'Dr' => 'Dr',
                    'Prof' => 'Prof',
                    'Rev' => 'Rev',
                    'Engr' => 'Engr',
                ],
                'school_id' => null,
            ],

            // 2. Gender
            [
                'label' => 'Gender',
                'name' => 'gender',
                'applies_to' => \App\Models\Profile::class,
                'description' => 'Gender identity options for staff, students and guardians.',
                'color' => 'bg-pink-100 text-pink-800',
                'options' => [
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                    'prefer_not' => 'Prefer not to say',
                ],
                'school_id' => null,
            ],

            // 3. Profile Type
            [
                'label' => 'Profile Type',
                'name' => 'profile_type',
                'applies_to' => \App\Models\Profile::class,
                'description' => 'The role a profile represents inside the school.',
                'color' => 'bg-teal-100 text-teal-800',
                'options' => [
                    'staff' => 'Staff / Teacher',
                    'student' => 'Student',
                    'guardian' => 'Parent / Guardian',
                ],
                'school_id' => null,
            ],
        ];

        foreach ($configs as $c) {
            Config::updateOrCreate(
                [
                    'name' => $c['name'],
                    'applies_to' => $c['applies_to'],
                    'school_id' => $c['school_id'],
                ],
                $c
            );
        }
    }
}
