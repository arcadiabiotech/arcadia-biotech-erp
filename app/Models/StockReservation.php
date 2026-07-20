<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    public const STATUSES = ['reserved', 'released', 'converted', 'cancelled'];

    protected $fillable = [
        'booking_id',
        'dealer_id',
        'farmer_id',
        'variety',
        'reserved_qty',
        'released_qty',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reserved_qty' => 'integer',
        'released_qty' => 'integer',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('status', 'reserved');
    }
}
