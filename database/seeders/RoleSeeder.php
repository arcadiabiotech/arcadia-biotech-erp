<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::insert([
            [
                'name'=>'admin',
                'display_name'=>'Administrator',
                'status'=>1,
            ],
            [
                'name'=>'dealer',
                'display_name'=>'Dealer',
                'status'=>1,
            ],
        ]);
    }
}