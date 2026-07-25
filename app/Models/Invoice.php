<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'generated', 'partially_paid', 'paid', 'cancelled'];

    protected $fillable = [
        'invoice_no',
        'dispatch_id',
        'dealer_id',
        'invoice_date',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'paid_amount',
        'balance_amount',
        'status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    /**
     * grand_total and balance_amount are always derived from the other
     * monetary fields — computing them here on every save (rather than in
     * each write path) guarantees they can never drift out of sync, the
     * same pattern Booking uses for booking_amount/balance_amount.
     */
    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice) {
            $invoice->grand_total = round((float) $invoice->subtotal - (float) $invoice->discount + (float) $invoice->tax, 2);
            $invoice->balance_amount = round($invoice->grand_total - (float) $invoice->paid_amount, 2);
        });
    }

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function lines()
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
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
