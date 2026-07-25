<?php

namespace App\Http\Requests;

use App\Models\Booking;
use App\Models\Dealer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStoreRequest extends FormRequest
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
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
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
            $qty = (float) $this->input('plant_qty');
            $rate = (float) $this->input('plant_rate');
            $discount = (float) $this->input('discount', 0);
            $advance = (float) $this->input('advance_amount', 0);

            if ($advance > round($qty * $rate - $discount, 2)) {
                $validator->errors()->add('advance_amount', 'Advance amount cannot exceed the booking balance.');
            }
        });
    }

    /**
     * Which dealers this user may book for: Admins/Accounts/dispatch-planner
     * see every dealer (dispatch-planner needs this for the Dispatch Plan
     * form's inline "Create Booking" modal, which can plan for any dealer),
     * Marketing only their assigned dealers. Dealer-role users never create
     * bookings at all (empty set — the Policy already blocks them, this is
     * the belt to that braces).
     */
    public function allowedDealerIds(): array
    {
        $user = $this->user();

        if ($user->hasRole(['super-admin', 'admin', 'accounts', 'dispatch-planner'])) {
            return Dealer::pluck('id')->all();
        }

        if ($user->hasRole('marketing')) {
            return Dealer::whereHas('assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->pluck('id')->all();
        }

        return [];
    }
}
