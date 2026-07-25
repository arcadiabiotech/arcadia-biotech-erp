<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'dispatch_line_id',
        'booking_id',
        'farmer_id',
        'qty',
        'rate',
        'amount',
    ];

    protected $casts = [
        'qty' => 'integer',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function dispatchLine()
    {
        return $this->belongsTo(DispatchLine::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }
}
