<?php

namespace Database\Factories;

use App\Models\LabEquipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabEquipment>
 */
class LabEquipmentFactory extends Factory
{
    protected $model = LabEquipment::class;

    public function definition(): array
    {
        return [
            'equipment_code' => 'EQ'.fake()->unique()->numerify('######'),
            'name' => fake()->randomElement(['Autoclave', 'Laminar Flow Hood', 'UV Chamber', 'pH Meter', 'Weighing Balance']),
            'category' => fake()->word(),
            'location' => fake()->randomElement(['Culture Room 1', 'Culture Room 2', 'Media Prep', 'Hardening Unit']),
            'status' => 'active',
        ];
    }
}
