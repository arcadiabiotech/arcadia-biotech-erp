<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challan extends Model
{
    public const TYPE_DEALER = 'dealer';
    public const TYPE_FARMER = 'farmer';

    protected $fillable = [
        'dispatch_id',
        'parent_challan_id',
        'dealer_id',
        'farmer_id',
        'type',
        'challan_no',
        'created_by',
    ];

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function parentChallan()
    {
        return $this->belongsTo(Challan::class, 'parent_challan_id');
    }

    /**
     * A dealer (master) challan's own farmer challans — empty for a farmer
     * challan itself.
     */
    public function childChallans()
    {
        return $this->hasMany(Challan::class, 'parent_challan_id');
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

    /**
     * Not a real Eloquent relation (no FK on dispatch_lines back to
     * challans) — just the DispatchLine rows this challan actually covers:
     * every line for its dealer within the dispatch, narrowed to one farmer
     * for a farmer challan.
     */
    public function lines()
    {
        return DispatchLine::query()
            ->where('dispatch_id', $this->dispatch_id)
            ->where('dealer_id', $this->dealer_id)
            ->when($this->type === self::TYPE_FARMER, fn ($q) => $q->where('farmer_id', $this->farmer_id));
    }
}
