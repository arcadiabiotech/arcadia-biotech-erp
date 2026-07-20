<?php

namespace App\Http\Requests;

use App\Models\Dealer;
use App\Models\Farmer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmerUpdateRequest extends FormRequest
{
    /**
     * Authorization is enforced by FarmerPolicy via authorizeResource() in
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
        $farmerId = $this->route('farmer')?->id;

        return [
            'farmer_name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'dealer_id' => ['required', Rule::in($this->allowedDealerIds())],
            'mobile' => ['required', 'digits:10', Rule::unique('farmers', 'mobile')->ignore($farmerId)->where(fn ($q) => $q->whereNull('deleted_at'))],
            'alternate_mobile' => ['nullable', 'digits:10'],
            'aadhaar_no' => ['nullable', 'digits:12', Rule::unique('farmers', 'aadhaar_no')->ignore($farmerId)->where(fn ($q) => $q->whereNull('deleted_at'))],
            'state_id' => ['required', Rule::exists('states', 'id')],
            'district_id' => ['required', Rule::exists('districts', 'id')],
            'taluka_id' => ['required', Rule::exists('talukas', 'id')],
            'village_id' => ['required', Rule::exists('villages', 'id')],
            'address' => ['nullable', 'string', 'max:500'],
            'pincode' => ['nullable', 'digits:6'],
            'farm_area' => ['nullable', 'numeric', 'min:0'],
            'soil_type' => ['nullable', Rule::in(Farmer::SOIL_TYPES)],
            'irrigation_type' => ['nullable', Rule::in(Farmer::IRRIGATION_TYPES)],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'farmer_name.required' => 'Farmer name is required.',
            'dealer_id.required' => 'Dealer is required.',
            'dealer_id.in' => 'Select a dealer you are authorized to manage farmers under.',
            'mobile.required' => 'Mobile number is required.',
            'mobile.digits' => 'Mobile number must be 10 digits.',
            'mobile.unique' => 'This mobile number is already registered to another farmer.',
            'aadhaar_no.digits' => 'Aadhaar number must be 12 digits.',
            'aadhaar_no.unique' => 'This Aadhaar number is already registered to another farmer.',
            'village_id.required' => 'Village is required.',
        ];
    }

    /**
     * Same scoping as FarmerStoreRequest, but a Marketing/Dealer user editing
     * a farmer they're already authorized for (via FarmerPolicy::update)
     * must still see that farmer's current dealer in the allowed set even if
     * it wouldn't otherwise be offered — otherwise saving the form without
     * changing the dealer would fail validation.
     */
    public function allowedDealerIds(): array
    {
        $user = $this->user();
        $ids = [];

        if ($user->hasRole(['super-admin', 'admin', 'accounts'])) {
            $ids = Dealer::pluck('id')->all();
        } elseif ($user->hasRole('marketing')) {
            $ids = Dealer::whereHas('assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->pluck('id')->all();
        } elseif ($user->hasRole('dealer') && $user->dealer_id) {
            $ids = [$user->dealer_id];
        }

        if ($farmer = $this->route('farmer')) {
            $ids[] = $farmer->dealer_id;
        }

        return array_unique($ids);
    }
}
