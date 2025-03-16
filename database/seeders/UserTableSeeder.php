<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'user@example.com',
            'user_name' => 'admin',
            'country' => 'US',
            'email_verified_at' => now(),
            'password' => 'string',
        ]);
    }
}
