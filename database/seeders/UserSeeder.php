<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'role_id' => 1,
            'dealer_id' => null,

            'name' => 'Admin',
            'username' => 'admin',
            'mobile' => '9999999999',
            'email' => 'admin@arcadia.com',

            'password' => Hash::make('12345678'),

            'status' => true,
        ]);
    }
}