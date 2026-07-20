<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@arcadia.com'], [
            'role_id' => Role::where('name', 'super-admin')->value('id'),
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
