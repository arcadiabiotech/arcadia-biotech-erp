<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    public const STATUSES = ['draft', 'pending', 'verified', 'approved', 'rejected', 'hold', 'unlocked', 'completed'];

    /**
     * Convention used by every module that plugs into this engine — not
     * enforced at the DB level, since a future module may need more levels.
     */
    public const LEVEL_VERIFICATION = 1;

    public const LEVEL_APPROVAL = 2;

    protected $fillable = [
        'module_name',
        'record_id',
        'approval_level',
        'status',
        'remarks',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'hold_by',
        'hold_at',
        'unlock_by',
        'unlock_at',
    ];

    protected $casts = [
        'approval_level' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'hold_at' => 'datetime',
        'unlock_at' => 'datetime',
    ];

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function holdBy()
    {
        return $this->belongsTo(User::class, 'hold_by');
    }

    public function unlockBy()
    {
        return $this->belongsTo(User::class, 'unlock_by');
    }

    public function scopeForRecord($query, string $moduleName, int $recordId)
    {
        return $query->where('module_name', $moduleName)->where('record_id', $recordId);
    }
}
