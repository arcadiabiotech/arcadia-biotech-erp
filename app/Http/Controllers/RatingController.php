<?php

namespace App\Http\Controllers;

use App\Http\Requests\RatingUpdateRequest;
use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\Rating;
use App\Models\RatingHistory;
use App\Models\User;
use App\Services\RatingCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Manual rating override for Dealers, Farmers and Users ("Employees" —
 * there is no separate Employee model in this ERP). Gated entirely by the
 * role:super-admin route middleware; Admins are deliberately excluded.
 */
class RatingController extends Controller
{
    private const TYPES = [
        'dealer' => Dealer::class,
        'farmer' => Farmer::class,
        'user' => User::class,
    ];

    public function update(RatingUpdateRequest $request, string $type, int $id)
    {
        $modelClass = self::TYPES[$type];
        $record = $modelClass::findOrFail($id);
        $data = $request->validated();

        $calculator = app(RatingCalculator::class);
        $newStar = $calculator->starsForScore((int) $data['score']);

        $rating = DB::transaction(function () use ($record, $modelClass, $data, $newStar, $type) {
            $rating = Rating::firstOrNew([
                'rateable_type' => $record->getMorphClass(),
                'rateable_id' => $record->id,
            ]);

            $oldScore = $rating->currentScore();
            $oldStar = $rating->currentStars();

            $rating->is_manual_override = true;
            $rating->manual_score = $data['score'];
            $rating->manual_star = $newStar;
            $rating->manual_reason = $data['reason'];
            $rating->manual_by = auth()->id();
            $rating->manual_at = now();
            $rating->save();

            RatingHistory::record($type, $record->id, 'manual_override', $oldScore, $oldStar, (int) $data['score'], $newStar, $data['reason']);

            return $rating;
        });

        return back()->with('success', 'Rating updated successfully.');
    }

    public function reset(string $type, int $id)
    {
        $modelClass = self::TYPES[$type];
        $record = $modelClass::findOrFail($id);

        DB::transaction(function () use ($record, $type) {
            $rating = Rating::firstOrNew([
                'rateable_type' => $record->getMorphClass(),
                'rateable_id' => $record->id,
            ]);

            $oldScore = $rating->currentScore();
            $oldStar = $rating->currentStars();

            $rating->is_manual_override = false;
            $rating->manual_score = null;
            $rating->manual_star = null;
            $rating->manual_reason = null;
            $rating->manual_by = null;
            $rating->manual_at = null;
            $rating->save();

            RatingHistory::record($type, $record->id, 'reset_to_auto', $oldScore, $oldStar, $rating->auto_score, $rating->auto_star, null);
        });

        return back()->with('success', 'Rating reset to auto.');
    }
}
