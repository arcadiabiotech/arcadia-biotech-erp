<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchPlanFarmerEstimate extends Model
{
    public const DISPATCH_TYPES = ['full', 'partial'];

    /**
     * plant_quantity now means "Dispatch Quantity" — the amount chosen
     * against the linked Booking when this row was created/edited, not a
     * rough estimate. Column name kept as-is to avoid a disruptive rename
     * across every place that already sums it (dispatch-plans/index,
     * dispatches/index widgets, etc.) — see the migration that added
     * booking_id/dispatch_type for the full rationale.
     */
    protected $fillable = ['dispatch_plan_id', 'dealer_id', 'farmer_id', 'booking_id', 'plant_quantity', 'dispatch_type'];

    protected $casts = [
        'plant_quantity' => 'integer',
    ];

    public function dispatchPlan()
    {
        return $this->belongsTo(DispatchPlan::class);
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
