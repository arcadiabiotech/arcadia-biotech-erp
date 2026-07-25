<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\User;

/**
 * Placeholder auto-rating formula. No real scoring rules exist yet for
 * Dealers/Farmers/Employees — this just gives every active record a
 * baseline score so the auto/manual override plumbing has something to
 * display and recalculate nightly. Swap the body of calculate() for real
 * business rules later; nothing else in the rating system needs to change.
 */
class RatingCalculator
{
    public function calculate(Dealer|Farmer|User $model): array
    {
        $score = $model->status ? 7 : 4;

        return [
            'score' => $score,
            'star' => $this->starsForScore($score),
        ];
    }

    /**
     * Rating scale is 0-10; stars are 0-5, so every 2 points is one star.
     */
    public function starsForScore(int $score): int
    {
        return $score > 0 ? min(5, max(1, (int) ceil($score / 2))) : 0;
    }
}
