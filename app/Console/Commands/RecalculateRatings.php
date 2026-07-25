<?php

namespace App\Console\Commands;

use App\Models\Dealer;
use App\Models\Farmer;
use App\Models\Rating;
use App\Models\User;
use App\Services\RatingCalculator;
use Illuminate\Console\Command;

class RecalculateRatings extends Command
{
    protected $signature = 'ratings:recalculate';

    protected $description = 'Recalculate auto ratings for dealers, farmers and users; skips any record with a manual override in place';

    public function handle(RatingCalculator $calculator): int
    {
        foreach ([Dealer::class, Farmer::class, User::class] as $modelClass) {
            $modelClass::query()->chunkById(200, function ($records) use ($calculator) {
                foreach ($records as $record) {
                    $result = $calculator->calculate($record);

                    $rating = Rating::firstOrNew([
                        'rateable_type' => $record->getMorphClass(),
                        'rateable_id' => $record->id,
                    ]);

                    $rating->auto_score = $result['score'];
                    $rating->auto_star = $result['star'];
                    $rating->save();
                }
            });
        }

        $this->info('Ratings recalculated.');

        return self::SUCCESS;
    }
}
