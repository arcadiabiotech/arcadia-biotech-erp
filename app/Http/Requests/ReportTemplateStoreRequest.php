<?php

namespace App\Http\Requests;

use App\Models\ReportTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportTemplateStoreRequest extends FormRequest
{
    /**
     * Authorization is enforced by ReportTemplatePolicy via
     * authorizeResource() in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => [Rule::in(array_keys(ReportTemplate::MODULES))],
            'date_range_type' => ['required', Rule::in(ReportTemplate::DATE_RANGE_TYPES)],
        ];
    }
}
