<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dispatch extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'pending', 'loading', 'dispatched', 'delivered', 'completed', 'cancelled'];

    protected $fillable = [
        'dispatch_no',
        'vehicle_assignment_id',
        'vehicle_id',
        'driver_name',
        'driver_mobile',
        'lr_number',
        'dispatch_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'delivery_location',
        'receiver_name',
        'receiver_mobile',
        'receiver_remarks',
        'delivered_at',
        'delivered_by',
        'vehicle_returned_at',
        'vehicle_returned_by',
        'return_remarks',
        'damage_remarks',
        'driver_remarks',
        'supervisor_remarks',
        'return_photos',
        'odometer_start',
        'odometer_end',
        'total_km',
        'cost_per_km',
        'transport_cost',
        'driver_allowance',
        'toll_charges',
        'other_expenses',
        'total_transport_expense',
        'status',
        'challan_no',
        'loaded_at',
        'loaded_by',
        'dispatched_at',
        'dispatched_by',
        'remarks',
        'photo',
        'signature',
        'gps_location',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'delivered_at' => 'datetime',
        'vehicle_returned_at' => 'date',
        'return_photos' => 'array',
        'loaded_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'cost_per_km' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'driver_allowance' => 'decimal:2',
        'toll_charges' => 'decimal:2',
        'other_expenses' => 'decimal:2',
        'total_transport_expense' => 'decimal:2',
    ];

    /**
     * Header-level rollups — never stored, always derived from lines so
     * they can't drift out of sync with the per-booking detail.
     */
    public function getTotalQtyAttribute(): int
    {
        return (int) $this->lines->sum('total_qty');
    }

    public function getTotalAmountAttribute(): float
    {
        return round((float) $this->lines->sum('amount'), 2);
    }

    public function getDealersAttribute()
    {
        return $this->lines->pluck('dealer')->filter()->unique('id')->values();
    }

    /**
     * Crate rollups across every line — only counts lines that actually
     * carry a crate count (qty_per_crate set), same as DispatchLine's own
     * crate_count/return_status. Null when no line on this dispatch tracks
     * crates at all, so callers can tell "nothing to reconcile" apart from
     * "fully reconciled".
     */
    public function getTotalCratesAttribute(): ?float
    {
        $lines = $this->lines->filter(fn (DispatchLine $line) => $line->crate_count !== null);

        return $lines->isEmpty() ? null : round((float) $lines->sum('crate_count'), 2);
    }

    public function getReturnedCratesAttribute(): ?float
    {
        if ($this->total_crates === null) {
            return null;
        }

        return round((float) $this->lines->sum('crates_returned'), 2);
    }

    public function getDamageCratesAttribute(): ?float
    {
        if ($this->total_crates === null) {
            return null;
        }

        return round((float) $this->lines->sum('damage_qty'), 2);
    }

    public function getMissingCratesAttribute(): ?float
    {
        if ($this->total_crates === null) {
            return null;
        }

        return round((float) $this->lines->sum('missing_qty'), 2);
    }

    public function getBrokenCratesAttribute(): ?float
    {
        if ($this->total_crates === null) {
            return null;
        }

        return round((float) $this->lines->sum('broken_qty'), 2);
    }

    public function getCrateReturnPendingAttribute(): ?float
    {
        if ($this->total_crates === null) {
            return null;
        }

        $accountedFor = $this->returned_crates + $this->damage_crates + $this->missing_crates + $this->broken_crates;

        return max(0, round($this->total_crates - $accountedFor, 2));
    }

    /**
     * pending | returned | null (dispatch has no crate-tracked lines).
     * Deliberately binary (unlike DispatchLine::return_status, which also
     * has "partial") — this is the dashboard/report-level summary, where
     * only "still owed" vs "fully accounted for" matters.
     */
    public function getCrateReturnStatusAttribute(): ?string
    {
        if ($this->crate_return_pending === null) {
            return null;
        }

        return $this->crate_return_pending > 0 ? 'pending' : 'returned';
    }

    /**
     * Display-only "where's the truck right now" label for the Dispatch
     * Lifecycle spec's "Vehicle Status" badge — derived entirely from the
     * existing status/vehicle_returned_at, no new column to keep in sync.
     */
    public function getVehicleStatusLabelAttribute(): ?string
    {
        if ($this->vehicle_returned_by) {
            return 'Available';
        }

        return match ($this->status) {
            'loading' => 'On Route',
            'dispatched' => 'In Transit',
            'delivered' => 'Delivered',
            default => null,
        };
    }

    /**
     * Step 7A dealer-wise cost split — each dealer's share of this
     * dispatch's total_transport_expense, proportional to their share of
     * total plants (qty) shipped on it. Empty when cost hasn't been
     * recorded yet (vehicle not yet returned) or there's no qty to split by.
     */
    public function getDealerTransportAllocationAttribute(): \Illuminate\Support\Collection
    {
        if (! $this->total_transport_expense || $this->total_qty <= 0) {
            return collect();
        }

        return $this->lines->groupBy('dealer_id')->map(function ($lines, $dealerId) {
            $qty = $lines->sum(fn (DispatchLine $l) => $l->total_qty);

            return [
                'dealer_id' => $dealerId,
                'dealer' => $lines->first()->dealer,
                'qty' => $qty,
                'allocated_cost' => round((float) $this->total_transport_expense * ($qty / $this->total_qty), 2),
            ];
        })->values();
    }

    public function getCostPerPlantAttribute(): ?float
    {
        return ($this->total_transport_expense && $this->total_qty > 0)
            ? round((float) $this->total_transport_expense / $this->total_qty, 2)
            : null;
    }

    public function vehicleAssignment()
    {
        return $this->belongsTo(VehicleAssignment::class);
    }

    public function lines()
    {
        return $this->hasMany(DispatchLine::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function loadedBy()
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function vehicleReturnedBy()
    {
        return $this->belongsTo(User::class, 'vehicle_returned_by');
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
