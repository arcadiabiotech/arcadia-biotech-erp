<?php

namespace Database\Factories;

use App\Models\LabChecklistItem;
use App\Models\LabDailyChecklist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabDailyChecklist>
 */
class LabDailyChecklistFactory extends Factory
{
    protected $model = LabDailyChecklist::class;

    public function definition(): array
    {
        return [
            'checklist_no' => 'LC-'.now()->year.'-'.fake()->unique()->numerify('######'),
            'employee_id' => User::factory(),
            'checklist_date' => now()->toDateString(),
            'status' => 'draft',
        ];
    }

    /**
     * Every checklist needs one lab_checklist_items row per catalogue item
     * to behave correctly (compliance_score, report item-compliance) — all
     * marked done by default so a plain factory-made checklist reads as
     * 100% compliant, matching the old boolean-columns default.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (LabDailyChecklist $checklist) {
            foreach (LabDailyChecklist::CHECKLIST_ITEMS as $key => $meta) {
                LabChecklistItem::create([
                    'lab_daily_checklist_id' => $checklist->id,
                    'item_key' => $key,
                    'is_done' => true,
                ]);
            }
        });
    }
}
