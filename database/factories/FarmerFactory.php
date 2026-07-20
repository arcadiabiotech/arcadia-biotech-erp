<?php

namespace Database\Factories;

use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
{
    protected $model = Farmer::class;

    public function definition(): array
    {
        return [
            'farmer_code' => 'FAR'.fake()->unique()->numerify('######'),
            'dealer_id' => Dealer::factory(),
            'farmer_name' => fake()->name(),
            'father_name' => fake()->firstNameMale(),
            'mobile' => fake()->unique()->numerify('##########'),
            'alternate_mobile' => null,
            'aadhaar_no' => null,
            'village_id' => Village::factory(),
            'address' => fake()->address(),
            'pincode' => fake()->numerify('######'),
            'farm_area' => fake()->randomFloat(2, 1, 20),
            'soil_type' => fake()->randomElement(Farmer::SOIL_TYPES),
            'irrigation_type' => fake()->randomElement(Farmer::IRRIGATION_TYPES),
            'status' => true,
        ];
    }
}
