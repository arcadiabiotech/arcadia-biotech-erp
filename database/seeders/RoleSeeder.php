<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'marketing' => 'Marketing',
            'dealer' => 'Dealer',
            'accounts' => 'Accounts',
            'dispatch' => 'Dispatch',
            'staff' => 'Staff',
        ] as $name => $displayName) {
            Role::updateOrCreate(['name' => $name], ['display_name' => $displayName, 'status' => true]);
        }
    }
}
