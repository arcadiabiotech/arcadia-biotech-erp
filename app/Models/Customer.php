<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'farmer_name',
        'mobile',
        'village',
        'taluka',
        'district',
        'state',
    ];
}