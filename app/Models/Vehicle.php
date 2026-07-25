<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_no',
        'vehicle_type',
        'capacity',
        'driver_name',
        'driver_mobile',
        'transport_company',
        'cost_per_km',
        'driver_allowance',
        'fuel_type',
        'average_mileage',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'cost_per_km' => 'decimal:2',
        'driver_allowance' => 'decimal:2',
        'average_mileage' => 'decimal:2',
    ];

    public function setVehicleNoAttribute(?string $value): void
    {
        $this->attributes['vehicle_no'] = $value === null ? null : mb_strtoupper($value);
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class);
    }

    public function vehicleAssignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
