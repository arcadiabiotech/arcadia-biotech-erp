<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DispatchPlanItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_assignment_id',
        'booking_id',
        'sequence',
        'dispatch_qty',
        'loaded_at',
        'loaded_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'dispatch_qty' => 'integer',
        'loaded_at' => 'datetime',
    ];

    public function vehicleAssignment()
    {
        return $this->belongsTo(VehicleAssignment::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function loadedBy()
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function dispatchLines()
    {
        return $this->hasMany(DispatchLine::class);
    }

    /**
     * How much of this planned item has already been pulled into a real
     * Dispatch — an item is repeatable across more than one Dispatch, so
     * this is a live sum rather than a stored column.
     */
    public function getDispatchedQtyAttribute(): int
    {
        return (int) $this->dispatchLines->sum('dispatch_qty');
    }

    public function getRemainingQtyAttribute(): int
    {
        return max(0, (int) $this->dispatch_qty - $this->dispatched_qty);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isLoaded(): bool
    {
        return $this->loaded_at !== null;
    }
}
