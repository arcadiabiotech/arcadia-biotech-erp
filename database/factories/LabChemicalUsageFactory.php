<?php

namespace Database\Factories;

use App\Models\LabChemicalUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabChemicalUsage>
 */
class LabChemicalUsageFactory extends Factory
{
    protected $model = LabChemicalUsage::class;

    public function definition(): array
    {
        return [
            'usage_no' => 'CU-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'used_by' => User::factory(),
            'usage_date' => now()->toDateString(),
            'chemical_name' => fake()->randomElement(['Sodium Hypochlorite', 'Ethanol', 'HgCl2']),
            'quantity_used' => 1,
            'unit' => 'ml',
            'status' => 'draft',
        ];
    }
}
