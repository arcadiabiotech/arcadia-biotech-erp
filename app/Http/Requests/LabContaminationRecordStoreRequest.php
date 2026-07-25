<?php

namespace App\Http\Requests;

use App\Models\LabContaminationRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabContaminationRecordStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reported_by' => $this->user()->hasRole(['super-admin', 'admin']) ? ['nullable', 'exists:users,id'] : ['nullable'],
            'contamination_date' => ['required', 'date', 'before_or_equal:today'],
            'culture_batch_number' => ['nullable', 'string', 'max:100'],
            'contamination_type' => ['required', Rule::in(LabContaminationRecord::TYPES)],
            'severity' => ['required', Rule::in(LabContaminationRecord::SEVERITIES)],
            'affected_qty' => ['nullable', 'integer', 'min:0'],
            'root_cause' => ['nullable', 'string', 'max:1000'],
            'corrective_action' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
