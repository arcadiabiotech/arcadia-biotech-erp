<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DealerService
{
    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            // Last Dealer
            $lastDealer = Dealer::latest('id')->first();

            if ($lastDealer) {

                $number = (int) substr($lastDealer->dealer_code, 3);

                $dealerCode = 'ARC' . str_pad($number + 1, 6, '0', STR_PAD_LEFT);

            } else {

                $dealerCode = 'ARC000001';

            }

            // Dealer Create

            $dealer = Dealer::create([

                'dealer_code' => $dealerCode,

                'firm_name' => ucwords(strtolower($data['firm_name'])),

                'dealer_name' => ucwords(strtolower($data['dealer_name'])),

                'mobile' => $data['mobile'],

                'whatsapp' => $data['whatsapp'] ?? null,

                'email' => $data['email'] ?? null,

                'gst_number' => $data['gst_number'] ?? null,

                'pan_number' => $data['pan_number'] ?? null,

                'address' => $data['address'] ?? null,

                'status' => true,

            ]);

            // Login User

            User::create([

                'role_id' => 2,

                'dealer_id' => $dealer->id,

                'username' => $dealerCode,

                'password' => Hash::make($data['mobile']),

                'name' => $dealer->dealer_name,

                'mobile' => $dealer->mobile,

                'email' => $dealer->email,

                'status' => true,

            ]);

            return $dealer;

        });
    }
}