<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispatchPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const APPROVAL_STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'plan_no',
        'plan_date',
        'route',
        'remarks',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function vehicleAssignments()
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    /**
     * Plan-level dealer/farmer breakdown — a rough "who/how much" estimate
     * captured at planning time, separate from the actual per-booking
     * dealer/qty tracked later on dispatch_plan_items once vehicles are
     * assigned. Total plant quantity is the sum of these rows rather than
     * a separately stored figure, since a plan can span several dealers.
     */
    public function farmerEstimates()
    {
        return $this->hasMany(DispatchPlanFarmerEstimate::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
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
