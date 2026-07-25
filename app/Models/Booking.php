<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    public const VARIETIES = ['G9', 'Williams', 'Jorden', 'Other'];

    public const PAYMENT_STATUSES = ['pending', 'partial', 'completed'];

    public const APPROVAL_STATUSES = ['draft', 'pending', 'verified', 'approved', 'rejected', 'hold'];

    /**
     * 'spot' = created inline from the Dispatch Plan form's "Create booking"
     * modal — the plant leaves the same day the plan is built, so there's
     * no time for the normal multi-day approval chain; BookingController::
     * store() fast-tracks these straight to 'approved'. 'regular' is every
     * booking made through the normal Bookings module.
     */
    public const SALE_TYPES = ['regular', 'spot'];

    public const DISPATCH_STATUSES = ['pending', 'partial', 'completed'];

    public const INVOICE_STATUSES = ['pending', 'generated', 'completed'];

    protected $fillable = [
        'booking_no',
        'dealer_id',
        'farmer_id',
        'marketing_user_id',
        'accounts_user_id',
        'variety',
        'booking_date',
        'plant_qty',
        'plant_rate',
        'booking_amount',
        'discount',
        'advance_amount',
        'balance_amount',
        'payment_status',
        'approval_status',
        'sale_type',
        'dispatch_status',
        'invoice_status',
        'remarks',
        'created_by',
        'updated_by',
        'approved_by',
        'deleted_by',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'plant_qty' => 'integer',
        'plant_rate' => 'decimal:2',
        'booking_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'advance_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    /**
     * booking_amount and balance_amount are always derived from the other
     * monetary fields — computing them here on every save (rather than in
     * each write path) guarantees they can never drift out of sync.
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking) {
            $booking->booking_amount = round($booking->plant_qty * $booking->plant_rate, 2);
            $booking->balance_amount = round($booking->booking_amount - $booking->discount - $booking->advance_amount, 2);
        });
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function marketingUser()
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    public function accountsUser()
    {
        return $this->belongsTo(User::class, 'accounts_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function reservation()
    {
        return $this->hasOne(StockReservation::class);
    }

    /**
     * A booking's active Dispatch Planning assignment, if any — at most one
     * non-cancelled row ever exists per booking (enforced at the query/
     * service layer, not a DB constraint; see DispatchPlanningService).
     */
    public function planItem()
    {
        return $this->hasOne(DispatchPlanItem::class);
    }

    /**
     * Every real shipment line ever cut against this booking, across every
     * Dispatch — a booking is repeatable across more than one Dispatch (full
     * or partial), so "how much has actually gone out" is always a live sum
     * rather than a stored column (same convention as
     * DispatchPlanItem::getDispatchedQtyAttribute()).
     */
    public function dispatchLines()
    {
        return $this->hasMany(DispatchLine::class);
    }

    public function getDispatchedQtyAttribute(): int
    {
        return (int) $this->dispatchLines->sum('dispatch_qty');
    }

    public function getBalanceQtyAttribute(): int
    {
        return max(0, (int) $this->plant_qty - $this->dispatched_qty);
    }
}
