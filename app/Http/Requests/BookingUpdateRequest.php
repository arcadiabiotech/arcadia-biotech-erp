<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Dealer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingUpdateRequest extends FormRequest
{
    /**
     * Authorization is enforced by BookingPolicy via authorizeResource() in
     * the controller, which runs as route middleware before this request is
     * even resolved — returning true here just defers to that existing gate
     * instead of duplicating it.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dealer_id' => ['required', Rule::in($this->allowedDealerIds())],
            'farmer_id' => ['required', Rule::exists('farmers', 'id')->where(fn ($q) => $q->where('dealer_id', $this->input('dealer_id')))],
            'variety' => ['required', Rule::in(Booking::VARIETIES)],
            'booking_date' => ['required', 'date'],
            'plant_qty' => ['required', 'integer', 'min:1'],
            'plant_rate' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'dealer_id.required' => 'Dealer is required.',
            'dealer_id.in' => 'Select a dealer you are authorized to book for.',
            'farmer_id.required' => 'Farmer is required.',
            'farmer_id.exists' => 'Select a farmer belonging to the chosen dealer.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Booking $booking */
            $booking = $this->route('booking');
            $user = $this->user();

            if (! $user->hasRole(['super-admin', 'admin'])) {
                if ((float) $this->input('plant_rate') !== (float) $booking->plant_rate) {
                    $validator->errors()->add('plant_rate', 'Only an Admin can change the plant rate.');
                }

                if ((float) $this->input('discount', 0) !== (float) $booking->discount) {
                    $validator->errors()->add('discount', 'Only an Admin can change the discount.');
                }
            }
        });
    }

    /**
     * Same scoping as BookingStoreRequest, but the booking's current dealer
     * must remain selectable even if it wouldn't otherwise be offered.
     */
    public function allowedDealerIds(): array
    {
        $user = $this->user();
        $ids = [];

        if ($user->hasRole(['super-admin', 'admin', 'accounts'])) {
            $ids = Dealer::pluck('id')->all();
        } elseif ($user->hasRole('marketing')) {
            $ids = Dealer::whereHas('assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->pluck('id')->all();
        }

        if ($booking = $this->route('booking')) {
            $ids[] = $booking->dealer_id;
        }

        return array_unique($ids);
    }
}
