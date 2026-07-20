<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VarietyStock extends Model
{
    protected $fillable = [
        'variety',
        'actual_qty',
        'updated_by',
    ];

    protected $casts = [
        'actual_qty' => 'integer',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Sum of reserved_qty across still-active reservations for this variety.
     * Released/converted/cancelled reservations no longer count.
     */
    public function reservedQty(): int
    {
        return (int) StockReservation::where('variety', $this->variety)
            ->where('status', 'reserved')
            ->sum('reserved_qty');
    }

    public function availableQty(): int
    {
        return max(0, $this->actual_qty - $this->reservedQty());
    }
}
