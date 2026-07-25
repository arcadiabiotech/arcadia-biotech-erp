<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use App\Models\LabEquipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabEquipmentStoreRequest extends FormRequest
{
    use CapitalizesNames;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFirstLetterOnly(['name']);
    }

    public function rules(): array
    {
        return [
            'equipment_code' => ['required', 'string', 'max:50', 'unique:lab_equipment,equipment_code'],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(LabEquipment::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
