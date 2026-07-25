<?php

namespace App\Models;

use App\Models\Concerns\HasSupervisorApproval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabDailyChecklist extends Model
{
    use HasFactory, SoftDeletes, HasSupervisorApproval;

    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    public const APPROVAL_MODULE = 'lab-checklists';

    /**
     * The lab's actual paper "Morning" + "Daily Report" sheet, digitized —
     * order matches the physical form so the digital checklist reads the
     * same way staff are used to. 'chemical' items get an extra free-text
     * field for which chemical was used (spray/fumigation); everything else
     * is just done + time. This is the single source of truth for the item
     * catalogue — lab_checklist_items rows only store the per-record
     * answers (is_done/time_recorded/chemical_used) keyed by item_key.
     */
    public const CHECKLIST_ITEMS = [
        'uv_on' => ['label' => 'UV on', 'section' => 'morning', 'chemical' => false],
        'sterilizer_on' => ['label' => 'Sterilizer on', 'section' => 'morning', 'chemical' => false],
        'paper_cotton' => ['label' => 'Paper & cotton', 'section' => 'morning', 'chemical' => false],
        'dustbin_uv' => ['label' => 'Dustbin UV', 'section' => 'morning', 'chemical' => false],
        'changeroom_mopping' => ['label' => 'Changeroom mopping', 'section' => 'morning', 'chemical' => false],
        'media_storage_mopping' => ['label' => 'Mopping in media storage', 'section' => 'morning', 'chemical' => false],
        'uv_changeroom' => ['label' => 'UV — changeroom', 'section' => 'morning', 'chemical' => false],
        'uv_media_storage' => ['label' => 'UV — media storage', 'section' => 'morning', 'chemical' => false],

        'changeroom_cleaning' => ['label' => 'Changeroom cleaning', 'section' => 'daily_report', 'chemical' => false],
        'room1_spray' => ['label' => 'Room 1 spray', 'section' => 'daily_report', 'chemical' => true],
        'room2_spray' => ['label' => 'Room 2 spray', 'section' => 'daily_report', 'chemical' => true],
        'room3_mopping' => ['label' => 'Room 3 mopping', 'section' => 'daily_report', 'chemical' => false],
        'media_storage_room_mopping' => ['label' => 'Media storage room mopping', 'section' => 'daily_report', 'chemical' => false],
        'media_stock' => ['label' => 'Media stock', 'section' => 'daily_report', 'chemical' => false],
        'paper_stock' => ['label' => 'Paper stock', 'section' => 'daily_report', 'chemical' => false],
        'contamination_record' => ['label' => 'Contamination record', 'section' => 'daily_report', 'chemical' => false],
        'media_preparation' => ['label' => 'Media preparation', 'section' => 'daily_report', 'chemical' => false],
        'autoclave_paper_cotton_instruments' => ['label' => 'Autoclave — paper/cotton/instruments', 'section' => 'daily_report', 'chemical' => false],
        'stock_preparation' => ['label' => 'Stock preparation', 'section' => 'daily_report', 'chemical' => false],
        'stock_at_culture' => ['label' => 'Stock at culture', 'section' => 'daily_report', 'chemical' => false],
        'laminar_airflow_cleaning' => ['label' => 'Laminar airflow cleaning', 'section' => 'daily_report', 'chemical' => false],
        'glass_bead_cleaning' => ['label' => 'Glass bead cleaning', 'section' => 'daily_report', 'chemical' => false],
        'apron_cleaning_autoclave' => ['label' => 'Apron cleaning / autoclave', 'section' => 'daily_report', 'chemical' => false],
        'filter_cleaning' => ['label' => 'Filter cleaning', 'section' => 'daily_report', 'chemical' => false],
        'fumigation' => ['label' => 'Fumigation', 'section' => 'daily_report', 'chemical' => true],
    ];

    protected $fillable = [
        'checklist_no',
        'employee_id',
        'checklist_date',
        'general_remarks',
        'photo',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        // Explicit Y-m-d format (not the plain 'date' cast) so the value
        // Eloquent re-serializes on save always matches the raw 'Y-m-d'
        // string the uniqueness check in LabDailyChecklistStoreRequest
        // validates against — under MySQL the DATE column type masks this
        // either way, but SQLite (used in tests) stores exactly what's
        // written, so the two need to agree byte-for-byte.
        'checklist_date' => 'date:Y-m-d',
    ];

    /**
     * % of catalogue items marked done on this checklist. Falls back to 0
     * (not a division-by-zero) if the item rows haven't been created yet.
     */
    public function getComplianceScoreAttribute(): float
    {
        $total = count(self::CHECKLIST_ITEMS);

        if ($total === 0) {
            return 0;
        }

        $done = $this->relationLoaded('checklistItems')
            ? $this->checklistItems->where('is_done', true)->count()
            : $this->checklistItems()->where('is_done', true)->count();

        return round(($done / $total) * 100, 2);
    }

    public function checklistItems()
    {
        return $this->hasMany(LabChecklistItem::class);
    }

    public function morningItems()
    {
        return $this->checklistItems()->whereIn('item_key', collect(self::CHECKLIST_ITEMS)->filter(fn ($i) => $i['section'] === 'morning')->keys());
    }

    public function dailyReportItems()
    {
        return $this->checklistItems()->whereIn('item_key', collect(self::CHECKLIST_ITEMS)->filter(fn ($i) => $i['section'] === 'daily_report')->keys());
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
