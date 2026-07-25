<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Services\DispatchPlanningService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchPlanStoreRequest extends FormRequest
{
    /**
     * Authorization is enforced by DispatchPlanPolicy via authorizeResource()
     * in the controller, which runs before this request is resolved.
     */
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
     * Two checks, both against the database rather than anything the client
     * last rendered (which could be stale, or simply never sent by a
     * legitimate form at all — the plain Rule::exists() above only proves
     * the booking_id is a real row, not that this user is actually allowed
     * to plan it):
     * 1. The booking must be one this user could actually pick from the
     *    dropdown — same rejected/payment-status/balance gate as
     *    DispatchPlanningService::activeBookingsForPlanning().
     * 2. Dispatch Quantity can never exceed the booking's own live balance
     *    (booked - already actually dispatched).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rows = collect($this->input('farmer_estimates', []));

            if ($rows->isEmpty()) {
                return;
            }

            $allowedBookingIds = app(DispatchPlanningService::class)->activeBookingsForPlanning($this->user())->pluck('id');

            $bookings = Booking::with('dispatchLines:id,booking_id,dispatch_qty')
                ->whereIn('id', $rows->pluck('booking_id')->filter())
                ->get()
                ->keyBy('id');

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

                if ((int) ($row['dispatch_qty'] ?? 0) > $booking->balance_qty) {
                    $validator->errors()->add(
                        "farmer_estimates.{$key}.dispatch_qty",
                        "Dispatch quantity for {$booking->booking_no} cannot exceed the balance of {$booking->balance_qty} plants."
                    );
                }
            }
        });
    }
}
