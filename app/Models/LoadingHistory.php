<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadingHistory extends Model
{
    public const ACTIONS = ['item_loaded', 'item_unloaded', 'loading_started', 'loading_approved'];

    protected $table = 'loading_history';

    public $timestamps = false;

    protected $fillable = [
        'vehicle_assignment_id',
        'dispatch_plan_item_id',
        'action',
        'performed_by',
        'remarks',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function vehicleAssignment()
    {
        return $this->belongsTo(VehicleAssignment::class);
    }

    public function item()
    {
        return $this->belongsTo(DispatchPlanItem::class, 'dispatch_plan_item_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Append-only: every loading event gets a new row. There is
     * deliberately no update()/delete() call site for this model anywhere
     * in the app — history rows are permanent, same convention as
     * RatingHistory::record().
     */
    public static function record(int $vehicleAssignmentId, ?int $itemId, string $action, int $performedBy, ?string $remarks = null): self
    {
        return static::create([
            'vehicle_assignment_id' => $vehicleAssignmentId,
            'dispatch_plan_item_id' => $itemId,
            'action' => $action,
            'performed_by' => $performedBy,
            'remarks' => $remarks,
            'created_at' => now(),
        ]);
    }
}
