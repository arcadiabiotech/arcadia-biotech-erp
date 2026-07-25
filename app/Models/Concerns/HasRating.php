<?php

namespace App\Models\Concerns;

use App\Models\Rating;

trait HasRating
{
    public function ratingRecord()
    {
        return $this->morphOne(Rating::class, 'rateable');
    }

    public function currentScore(): ?int
    {
        return $this->ratingRecord?->currentScore();
    }

    public function currentStars(): ?int
    {
        return $this->ratingRecord?->currentStars();
    }

    public function isManualOverride(): bool
    {
        return (bool) $this->ratingRecord?->is_manual_override;
    }
}
