<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'dispatch_plan_id',
        'vehicle_id',
        'marketing_user_id',
        'driver_name',
        'driver_mobile',
        'helper_name',
        'estimated_departure_time',
        'start_km',
        'remarks',
        'created_by',
        'updated_by',
    ];

    public function dispatchPlan()
    {
        return $this->belongsTo(DispatchPlan::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function marketingOfficer()
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    public function items()
    {
        return $this->hasMany(DispatchPlanItem::class);
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class);
    }

    public function loading()
    {
        return $this->hasOne(VehicleLoading::class);
    }

    public function history()
    {
        return $this->hasMany(LoadingHistory::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTotalFarmersAttribute(): int
    {
        return $this->items->pluck('booking.farmer_id')->filter()->unique()->count();
    }

    /**
     * Informational only — a vehicle is expected to carry bookings from one
     * or two dealers on a route, but nothing here enforces a hard cap.
     */
    public function getTotalDealersAttribute(): int
    {
        return $this->items->pluck('booking.dealer_id')->filter()->unique()->count();
    }

    public function getTotalChallansAttribute(): int
    {
        return $this->dispatches->whereNotNull('challan_no')->count();
    }

    public function getTotalPlantsAttribute(): int
    {
        return (int) $this->items->sum(fn (DispatchPlanItem $item) => $item->dispatch_qty ?? $item->booking?->plant_qty ?? 0);
    }

    public function getUsedCapacityAttribute(): int
    {
        return $this->total_plants;
    }

    public function getRemainingCapacityAttribute(): ?int
    {
        if (! $this->vehicle?->capacity) {
            return null;
        }

        return max(0, (int) $this->vehicle->capacity - $this->used_capacity);
    }

    public function getLoadingStatusAttribute(): string
    {
        return $this->loading?->status ?? 'pending';
    }

    /**
     * available (loading done, nothing dispatched yet) / partially_dispatched
     * (some but not all planned quantity is out) / completed (every item's
     * remaining_qty has hit 0). Null while loading itself isn't done yet —
     * there's nothing to dispatch against until then.
     */
    public function getDispatchStatusAttribute(): ?string
    {
        if ($this->loading_status !== 'completed') {
            return null;
        }

        if ($this->items->isEmpty()) {
            return 'available';
        }

        if ($this->items->every(fn (DispatchPlanItem $item) => $item->remaining_qty <= 0)) {
            return 'completed';
        }

        if ($this->items->contains(fn (DispatchPlanItem $item) => $item->dispatched_qty > 0)) {
            return 'partially_dispatched';
        }

        return 'available';
    }

    /**
     * The Dispatch currently mid-flight for this vehicle (challan already
     * generated, not yet marked "Dispatch Vehicle") — drives the inline
     * "Dispatch Vehicle" button on the Dispatches index page. Relies on
     * `dispatches` being eager-loaded; falls back to a query otherwise.
     */
    public function getActiveDispatchAttribute(): ?Dispatch
    {
        return ($this->relationLoaded('dispatches') ? $this->dispatches : $this->dispatches()->get())
            ->firstWhere('status', 'loading');
    }
}
