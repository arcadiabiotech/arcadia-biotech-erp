<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabEquipment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'under_maintenance', 'decommissioned'];

    protected $fillable = [
        'equipment_code',
        'name',
        'category',
        'location',
        'status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function maintenanceLogs()
    {
        return $this->hasMany(LabEquipmentMaintenanceLog::class);
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
