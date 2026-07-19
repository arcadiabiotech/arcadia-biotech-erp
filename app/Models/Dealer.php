<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dealer extends Model
{
    use HasFactory;

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
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'agreement_date' => 'date',
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
}