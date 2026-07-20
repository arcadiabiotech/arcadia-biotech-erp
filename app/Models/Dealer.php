<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dealer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'dealer_code',
        'firm_name',
        'dealer_name',
        'mobile',
        'whatsapp',
        'email',
        'gst_number',
        'pan_number',
        'address',
        'state_id',
        'district_id',
        'taluka_id',
        'village_id',
        'pin_code',
        'agreement_date',
        'credit_limit',
        'remarks',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'agreement_date' => 'date',
        'credit_limit' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function taluka()
    {
        return $this->belongsTo(Taluka::class);
    }

    public function village()
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * The marketing assignment for this dealer (one dealer -> one marketing user).
     */
    public function assignment()
    {
        return $this->hasOne(DealerAssignment::class);
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
