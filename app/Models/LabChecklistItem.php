<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabChecklistItem extends Model
{
    protected $fillable = [
        'lab_daily_checklist_id',
        'item_key',
        'is_done',
        'time_recorded',
        'chemical_used',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function checklist()
    {
        return $this->belongsTo(LabDailyChecklist::class, 'lab_daily_checklist_id');
    }

    public function getLabelAttribute(): string
    {
        return LabDailyChecklist::CHECKLIST_ITEMS[$this->item_key]['label'] ?? $this->item_key;
    }

    public function getSectionAttribute(): string
    {
        return LabDailyChecklist::CHECKLIST_ITEMS[$this->item_key]['section'] ?? 'morning';
    }
}
