<?php

namespace App\Http\Requests;

use App\Models\Dealer;
use App\Models\Farmer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmerStoreRequest extends FormRequest
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
        return [
            'farmer_name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'dealer_id' => ['required', Rule::in($this->allowedDealerIds())],
            'mobile' => ['required', 'digits:10', Rule::unique('farmers', 'mobile')->where(fn ($q) => $q->whereNull('deleted_at'))],
            'alternate_mobile' => ['nullable', 'digits:10'],
            'aadhaar_no' => ['nullable', 'digits:12', Rule::unique('farmers', 'aadhaar_no')->where(fn ($q) => $q->whereNull('deleted_at'))],
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
            'dealer_id.in' => 'Select a dealer you are authorized to register farmers under.',
            'mobile.required' => 'Mobile number is required.',
            'mobile.digits' => 'Mobile number must be 10 digits.',
            'mobile.unique' => 'This mobile number is already registered to another farmer.',
            'aadhaar_no.digits' => 'Aadhaar number must be 12 digits.',
            'aadhaar_no.unique' => 'This Aadhaar number is already registered to another farmer.',
            'village_id.required' => 'Village is required.',
        ];
    }

    /**
     * Which dealers this user may attribute a farmer to: Admins/Accounts see
     * every dealer, Marketing only their assigned dealers, Dealer-role users
     * only themselves. Reused by the controller to scope the form's dealer
     * dropdown too, so the UI never even offers a dealer the submit would reject.
     */
    public function allowedDealerIds(): array
    {
        $user = $this->user();

        if ($user->hasRole(['super-admin', 'admin', 'accounts'])) {
            return Dealer::pluck('id')->all();
        }

        if ($user->hasRole('marketing')) {
            return Dealer::whereHas('assignment', fn ($q) => $q->where('marketing_user_id', $user->id))->pluck('id')->all();
        }

        if ($user->hasRole('dealer') && $user->dealer_id) {
            return [$user->dealer_id];
        }

        return [];
    }
}
