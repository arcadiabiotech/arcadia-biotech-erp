<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispatchLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'dispatch_id',
        'dispatch_plan_item_id',
        'booking_id',
        'dealer_id',
        'farmer_id',
        'dispatch_qty',
        'extra_qty',
        'accepted_qty',
        'rejected_qty',
        'rejection_reason',
        'qty_per_crate',
        'crates_returned',
        'damage_qty',
        'missing_qty',
        'broken_qty',
        'dead_plant_qty',
        'extra_returned_qty',
        'batch_number',
        'plant_age',
        'remaining_qty',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'dispatch_qty' => 'integer',
        'extra_qty' => 'integer',
        'accepted_qty' => 'integer',
        'rejected_qty' => 'integer',
        'qty_per_crate' => 'integer',
        'crates_returned' => 'decimal:2',
        'damage_qty' => 'decimal:2',
        'missing_qty' => 'decimal:2',
        'broken_qty' => 'decimal:2',
        'dead_plant_qty' => 'integer',
        'extra_returned_qty' => 'integer',
        'remaining_qty' => 'integer',
    ];

    /**
     * dispatch_qty stays the ordered/booked quantity (what stock reservation
     * and remaining_qty are calculated against) — extra_qty is bonus/free
     * plants added on top for this line only. total_qty is what's actually
     * loaded and crated.
     */
    public function getTotalQtyAttribute(): int
    {
        return $this->dispatch_qty + $this->extra_qty;
    }

    /**
     * Crate count is never stored — it's always total_qty ÷ qty_per_crate,
     * so a 420-plant line at 40/crate reads as 10.5 crates rather than
     * needing a manually entered count that could drift out of sync.
     */
    public function getCrateCountAttribute(): ?float
    {
        if (! $this->qty_per_crate) {
            return null;
        }

        return round($this->total_qty / $this->qty_per_crate, 2);
    }

    /**
     * Crates still owed back from the dealer/farmer for this line. A crate
     * marked damaged, missing, or broken is accounted for (not "returned")
     * but no longer pending either — it's written off rather than owed
     * back. Null when there's no crate count to begin with (qty_per_crate
     * unset).
     */
    public function getPendingCratesAttribute(): ?float
    {
        if ($this->crate_count === null) {
            return null;
        }

        $accountedFor = (float) ($this->crates_returned ?? 0)
            + (float) ($this->damage_qty ?? 0)
            + (float) ($this->missing_qty ?? 0)
            + (float) ($this->broken_qty ?? 0);

        return max(0, round($this->crate_count - $accountedFor, 2));
    }

    /**
     * pending | partial | complete | null (nothing to return against).
     */
    public function getReturnStatusAttribute(): ?string
    {
        if ($this->crate_count === null) {
            return null;
        }

        if (! $this->crates_returned && ! $this->damage_qty) {
            return 'pending';
        }

        return $this->pending_crates <= 0 ? 'complete' : 'partial';
    }

    /**
     * What actually gets billed: the farmer's accepted quantity once
     * delivery is recorded (accepted_qty can be less than total_qty if
     * some plants were rejected), or the full total_qty before delivery has
     * happened yet.
     */
    public function getBillableQtyAttribute(): int
    {
        return $this->accepted_qty ?? $this->total_qty;
    }

    /**
     * Invoice line amount — billable_qty priced at the booking's agreed
     * rate, so a farmer is never charged for plants they rejected. The
     * challan still shows total_qty (what was physically shipped); this is
     * only what gets invoiced. Not stored: booking.plant_rate is the single
     * source of truth for pricing.
     */
    public function getAmountAttribute(): float
    {
        return round($this->billable_qty * (float) $this->booking?->plant_rate, 2);
    }

    /**
     * Whether a farmer's acceptance/rejection has been recorded for this
     * line yet — set together at Mark Delivered time.
     */
    public function getDeliveryRecordedAttribute(): bool
    {
        return $this->accepted_qty !== null;
    }

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function dispatchPlanItem()
    {
        return $this->belongsTo(DispatchPlanItem::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
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
