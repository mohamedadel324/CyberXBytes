<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {

        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'mohamedmersal98@gmail.com',
            'password' => Hash::make('1'),
        ]);
        
        // Assign Super Admin role to the admin user
        $admin->assignRole('Super Admin');
    }
}
