<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Taluka;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Taluka>
 */
class TalukaFactory extends Factory
{
    protected $model = Taluka::class;

    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name' => fake()->unique()->citySuffix().' Taluka',
            'status' => true,
        ];
    }
}
