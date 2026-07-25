<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    public const MODULES = [
        'lab-checklists' => 'Daily Checklists',
        'lab-maintenance' => 'Equipment Maintenance',
        'lab-media' => 'Media Stock Verification',
        'lab-chemicals' => 'Chemical Usage',
        'lab-contamination' => 'Contamination Records',
    ];

    public const DATE_RANGE_TYPES = ['daily', 'weekly', 'monthly'];

    protected $fillable = ['name', 'modules', 'date_range_type', 'created_by'];

    protected $casts = [
        'modules' => 'array',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
