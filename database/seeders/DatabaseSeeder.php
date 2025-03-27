<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LabCategory;
use App\Models\ChallangeCategory;
use App\Models\Lab;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            UserTableSeeder::class,
            LabTableSeeder::class
        ]);
        LabCategory::create([
            'lab_uuid' => Lab::first()->uuid,
            'title' => 'Training',
            'ar_title' => 'التدريب',
            'image' => 'test.png',
        ]);
        ChallangeCategory::create([
            'name' => 'Training',
            'icon' => 'test.png',
        ]);

    }
}
