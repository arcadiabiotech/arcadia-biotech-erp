<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Dealer;
use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        // Created eagerly (not via lazy factory relationships) so the
        // farmer genuinely belongs to the same dealer as the booking —
        // matching the real cross-field validation rule.
        $dealer = Dealer::factory()->create();
        $farmer = Farmer::factory()->create(['dealer_id' => $dealer->id]);

        return [
            'booking_no' => 'BK-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'dealer_id' => $dealer->id,
            'farmer_id' => $farmer->id,
            'variety' => fake()->randomElement(Booking::VARIETIES),
            'booking_date' => now()->toDateString(),
            'plant_qty' => fake()->numberBetween(100, 5000),
            'plant_rate' => fake()->randomFloat(2, 5, 25),
            'discount' => 0,
            'advance_amount' => 0,
        ];
    }
}
