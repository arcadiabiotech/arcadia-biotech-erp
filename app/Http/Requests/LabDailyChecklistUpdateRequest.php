<?php

namespace App\Http\Requests;

use App\Models\LabDailyChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabDailyChecklistUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $checklist = $this->route('lab_checklist');

        $rules = [
            'checklist_date' => [
                'required', 'date', 'before_or_equal:today',
                Rule::unique('lab_daily_checklists', 'checklist_date')
                    ->where(fn ($q) => $q->where('employee_id', $checklist->employee_id))
                    ->ignore($checklist->id),
            ],
            'general_remarks' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];

        foreach (LabDailyChecklist::CHECKLIST_ITEMS as $key => $meta) {
            $rules["items.{$key}.done"] = ['nullable', 'boolean'];
            $rules["items.{$key}.time"] = ['nullable', 'date_format:H:i'];

            if ($meta['chemical']) {
                $rules["items.{$key}.chemical"] = ['nullable', 'string', 'max:100'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'checklist_date.unique' => 'A checklist already exists for this employee on this date.',
        ];
    }
}
