<?php

namespace App\Http\Controllers\Concerns;

use App\Models\District;
use App\Models\Farmer;
use App\Models\State;
use App\Models\Taluka;
use App\Models\Village;

trait HasLocationOptions
{
    private function locationOptions(): array
    {
        return [
            'states' => State::where('status', true)->orderBy('name')->get(['id', 'name']),
            'districts' => District::where('status', true)->orderBy('name')->get(['id', 'name', 'state_id']),
            'talukas' => Taluka::where('status', true)->orderBy('name')->get(['id', 'name', 'district_id']),
            'villages' => Village::where('status', true)->orderBy('name')->get(['id', 'name', 'taluka_id']),
            'soilTypes' => Farmer::SOIL_TYPES,
            'irrigationTypes' => Farmer::IRRIGATION_TYPES,
        ];
    }
}
