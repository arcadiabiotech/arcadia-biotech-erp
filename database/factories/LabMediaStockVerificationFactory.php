<?php

namespace Database\Factories;

use App\Models\LabMediaStockVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabMediaStockVerification>
 */
class LabMediaStockVerificationFactory extends Factory
{
    protected $model = LabMediaStockVerification::class;

    public function definition(): array
    {
        return [
            'verification_no' => 'MS-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'verified_by' => User::factory(),
            'verification_date' => now()->toDateString(),
            'media_name' => fake()->randomElement(['MS Medium', 'Agar', 'Sucrose']),
            'unit' => 'litres',
            'opening_stock' => 10,
            'received_qty' => 0,
            'consumed_qty' => 0,
            'closing_stock' => 10,
            'status' => 'draft',
        ];
    }
}
