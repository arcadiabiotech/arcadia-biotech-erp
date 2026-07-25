<?php

namespace Database\Factories;

use App\Models\LabEquipment;
use App\Models\LabEquipmentMaintenanceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabEquipmentMaintenanceLog>
 */
class LabEquipmentMaintenanceLogFactory extends Factory
{
    protected $model = LabEquipmentMaintenanceLog::class;

    public function definition(): array
    {
        return [
            'log_no' => 'EM-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'lab_equipment_id' => LabEquipment::factory(),
            'performed_by' => User::factory(),
            'maintenance_date' => now()->toDateString(),
            'maintenance_type' => 'routine',
            'status' => 'draft',
        ];
    }
}
