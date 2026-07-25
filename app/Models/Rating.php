<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $fillable = [
        'rateable_type',
        'rateable_id',
        'auto_score',
        'auto_star',
        'is_manual_override',
        'manual_score',
        'manual_star',
        'manual_reason',
        'manual_by',
        'manual_at',
    ];

    protected function casts(): array
    {
        return [
            'is_manual_override' => 'boolean',
            'manual_at' => 'datetime',
        ];
    }

    public function rateable()
    {
        return $this->morphTo();
    }

    public function manualBy()
    {
        return $this->belongsTo(User::class, 'manual_by');
    }

    public function currentScore(): ?int
    {
        return $this->is_manual_override ? $this->manual_score : $this->auto_score;
    }

    public function currentStars(): ?int
    {
        return $this->is_manual_override ? $this->manual_star : $this->auto_star;
    }
}
