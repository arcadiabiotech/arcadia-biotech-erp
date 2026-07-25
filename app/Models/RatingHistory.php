<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'module',
        'rateable_id',
        'action',
        'old_score',
        'old_star',
        'new_score',
        'new_star',
        'reason',
        'changed_by',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Append-only: record a manual override or reset-to-auto event.
     * There is deliberately no update()/delete() call site for this model
     * anywhere in the app — history rows are permanent.
     */
    public static function record(string $module, int $rateableId, string $action, ?int $oldScore, ?int $oldStar, ?int $newScore, ?int $newStar, ?string $reason): self
    {
        return static::create([
            'module' => $module,
            'rateable_id' => $rateableId,
            'action' => $action,
            'old_score' => $oldScore,
            'old_star' => $oldStar,
            'new_score' => $newScore,
            'new_star' => $newStar,
            'reason' => $reason,
            'changed_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
