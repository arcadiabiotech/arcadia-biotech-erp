<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleLoading extends Model
{
    public const STATUSES = ['pending', 'loading', 'completed'];

    protected $table = 'vehicle_loading';

    protected $fillable = [
        'vehicle_assignment_id',
        'status',
        'loading_started_at',
        'loading_finished_at',
        'supervisor_id',
        'photo',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'loading_started_at' => 'datetime',
        'loading_finished_at' => 'datetime',
    ];

    public function vehicleAssignment()
    {
        return $this->belongsTo(VehicleAssignment::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
