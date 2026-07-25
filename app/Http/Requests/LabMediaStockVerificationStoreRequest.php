<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CapitalizesNames;
use Illuminate\Foundation\Http\FormRequest;

class LabMediaStockVerificationStoreRequest extends FormRequest
{
    use CapitalizesNames;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->capitalizeFirstLetterOnly(['media_name']);
    }

    public function rules(): array
    {
        return [
            'verified_by' => $this->user()->hasRole(['super-admin', 'admin']) ? ['nullable', 'exists:users,id'] : ['nullable'],
            'verification_date' => ['required', 'date', 'before_or_equal:today'],
            'media_name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:30'],
            'opening_stock' => ['required', 'numeric', 'min:0'],
            'received_qty' => ['nullable', 'numeric', 'min:0'],
            'consumed_qty' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
