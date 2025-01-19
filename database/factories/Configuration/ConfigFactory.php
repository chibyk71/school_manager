<?php

namespace Database\Factories\Configuration;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Configuration\Config>
 */
class ConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $defaultClassTeacherSubjectRoleConfig = [
            'headteacher',
            'assistant headteacher',
            'subject teacher',
        ];

        $defaultVehicleExpenseConfig = [
            'fuel',
            'maintainance',
            'repair',
            'papers renewal'
        ];

        $defaultSalaryStructureConfig = [
            'basic',
            'allowance',
            'bonus',
            'deduction',
        ];

        return [
            
        ];
    }
}
