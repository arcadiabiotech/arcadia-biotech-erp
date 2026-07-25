<?php

namespace Database\Factories;

use App\Models\LabContaminationRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabContaminationRecord>
 */
class LabContaminationRecordFactory extends Factory
{
    protected $model = LabContaminationRecord::class;

    public function definition(): array
    {
        return [
            'contamination_no' => 'CR-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'reported_by' => User::factory(),
            'contamination_date' => now()->toDateString(),
            'contamination_type' => 'unknown',
            'severity' => 'low',
            'status' => 'draft',
        ];
    }
}
