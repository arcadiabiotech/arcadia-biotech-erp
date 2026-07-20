<?php

namespace Database\Factories;

use App\Models\Taluka;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Village>
 */
class VillageFactory extends Factory
{
    protected $model = Village::class;

    public function definition(): array
    {
        return [
            'taluka_id' => Taluka::factory(),
            'name' => fake()->unique()->streetName(),
            'status' => true,
        ];
    }
}
