<?php

namespace App\Http\Requests;

use App\Models\LabEquipment;
use Illuminate\Validation\Rule;

class LabEquipmentUpdateRequest extends LabEquipmentStoreRequest
{
    public function rules(): array
    {
        return [
            'equipment_code' => ['required', 'string', 'max:50', Rule::unique('lab_equipment', 'equipment_code')->ignore($this->route('lab_equipment'))],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(LabEquipment::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
