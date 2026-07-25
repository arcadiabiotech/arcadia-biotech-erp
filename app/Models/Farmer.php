<?php

namespace App\Models;

use App\Models\Concerns\HasRating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farmer extends Model
{
    use HasFactory, SoftDeletes, HasRating;

    public const SOIL_TYPES = ['Black Soil', 'Red Soil', 'Alluvial Soil', 'Loamy Soil', 'Sandy Soil', 'Clay Soil', 'Laterite Soil', 'Other'];

    public const IRRIGATION_TYPES = ['Drip', 'Sprinkler', 'Flood / Canal', 'Borewell', 'Rain-fed', 'Other'];

    protected $fillable = [
        'farmer_code',
        'dealer_id',
        'farmer_name',
        'father_name',
        'mobile',
        'alternate_mobile',
        'aadhaar_no',
        'state_id',
        'district_id',
        'taluka_id',
        'village_id',
        'address',
        'pincode',
        'farm_area',
        'soil_type',
        'irrigation_type',
        'status',
        'remarks',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'farm_area' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

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
