<?php

namespace Database\Factories;

use App\Models\Dealer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dealer>
 */
class DealerFactory extends Factory
{
    protected $model = Dealer::class;

    public function definition(): array
    {
        return [
            'dealer_code' => 'ARC'.fake()->unique()->numerify('######'),
            'firm_name' => fake()->company(),
            'dealer_name' => fake()->name(),
            'mobile' => fake()->unique()->numerify('##########'),
            'whatsapp' => null,
            'email' => fake()->unique()->safeEmail(),
            'gst_number' => null,
            'pan_number' => null,
            'address' => fake()->address(),
            'status' => true,
        ];
    }
}
