<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Services\DispatchPlanningService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchPlanUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_date' => ['required', 'date'],
            'route' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'farmer_estimates' => ['nullable', 'array'],
            'farmer_estimates.*.booking_id' => ['required', 'distinct', Rule::exists('bookings', 'id')],
            'farmer_estimates.*.dispatch_qty' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Same two checks as DispatchPlanStoreRequest (eligible booking + live
     * balance), except a booking already linked to this plan is exempted
     * from the eligibility gate (activeBookingsForPlanning() does this via
     * its own $plan argument) and is allowed to keep using the quantity it
     * already accounts for here — its own existing row's amount is added
     * back onto the live balance before comparing, so re-saving a plan
     * unchanged never fails just because the row itself is part of what's
     * "already dispatched" against the booking.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rows = collect($this->input('farmer_estimates', []));

            if ($rows->isEmpty()) {
                return;
            }

            $dispatchPlan = $this->route('dispatch_plan');
            $allowedBookingIds = app(DispatchPlanningService::class)->activeBookingsForPlanning($this->user(), $dispatchPlan)->pluck('id');

            $bookings = Booking::with('dispatchLines:id,booking_id,dispatch_qty')
                ->whereIn('id', $rows->pluck('booking_id')->filter())
                ->get()
                ->keyBy('id');

            $existingByBooking = $dispatchPlan
                ? $dispatchPlan->farmerEstimates()->whereNotNull('booking_id')->pluck('plant_quantity', 'booking_id')
                : collect();

            foreach ($rows as $key => $row) {
                $booking = $bookings->get($row['booking_id'] ?? null);

                if (! $booking) {
                    continue;
                }

                if (! $allowedBookingIds->contains($booking->id)) {
                    $validator->errors()->add(
                        "farmer_estimates.{$key}.booking_id",
                        "{$booking->booking_no} is not eligible for planning (rejected, insufficient payment, or nothing left to dispatch)."
                    );

                    continue;
                }

                $allowance = $booking->balance_qty + (int) ($existingByBooking->get($booking->id) ?? 0);

                if ((int) ($row['dispatch_qty'] ?? 0) > $allowance) {
                    $validator->errors()->add(
                        "farmer_estimates.{$key}.dispatch_qty",
                        "Dispatch quantity for {$booking->booking_no} cannot exceed the balance of {$allowance} plants."
                    );
                }
            }
        });
    }
}
