<?php

namespace App\Models;

use App\Models\Concerns\HasSupervisorApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabEquipmentMaintenanceLog extends Model
{
    use HasFactory, SoftDeletes, HasSupervisorApproval;

    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    public const MAINTENANCE_TYPES = ['routine', 'breakdown', 'calibration'];

    public const APPROVAL_MODULE = 'lab-maintenance';

    protected $fillable = [
        'log_no',
        'lab_equipment_id',
        'performed_by',
        'maintenance_date',
        'maintenance_type',
        'description',
        'next_due_date',
        'downtime_hours',
        'cost',
        'photo',
        'remarks',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        // Explicit Y-m-d format so range queries (whereBetween/where <) that
        // bind plain date strings compare consistently under SQLite, where
        // (unlike MySQL's DATE column type) there's no automatic coercion —
        // see LabDailyChecklist::$casts for the full explanation.
        'maintenance_date' => 'date:Y-m-d',
        'next_due_date' => 'date:Y-m-d',
        'downtime_hours' => 'decimal:2',
        'cost' => 'decimal:2',
    ];

    public function equipment()
    {
        return $this->belongsTo(LabEquipment::class, 'lab_equipment_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
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
