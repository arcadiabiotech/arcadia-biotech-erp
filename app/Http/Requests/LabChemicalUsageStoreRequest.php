<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use Illuminate\Foundation\Http\FormRequest;

class LabChemicalUsageStoreRequest extends FormRequest
{
    use CapitalizesNames;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFirstLetterOnly(['chemical_name']);
    }

    public function rules(): array
    {
        return [
            'used_by' => $this->user()->hasRole(['super-admin', 'admin']) ? ['nullable', 'exists:users,id'] : ['nullable'],
            'usage_date' => ['required', 'date', 'before_or_equal:today'],
            'chemical_name' => ['required', 'string', 'max:150'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'quantity_used' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:30'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
