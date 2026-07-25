<?php

namespace App\Http\Requests;

use App\Models\LabEquipmentMaintenanceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabEquipmentMaintenanceLogStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only meaningful for Admin/Super Admin, who may log this on
            // behalf of a technician; a Lab Technician creating their own
            // entry always gets forced to their own id in the controller.
            'performed_by' => $this->user()->hasRole(['super-admin', 'admin']) ? ['nullable', 'exists:users,id'] : ['nullable'],
            'lab_equipment_id' => ['required', 'exists:lab_equipment,id'],
            'maintenance_date' => ['required', 'date', 'before_or_equal:today'],
            'maintenance_type' => ['required', Rule::in(LabEquipmentMaintenanceLog::MAINTENANCE_TYPES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:maintenance_date'],
            'downtime_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
