<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Core users and permissions
            AdminSeeder::class,
            PlayerTitleSeeder::class,

            // Labs and challenges
            LabTableSeeder::class,
            LabCategorySeeder::class,
            ChallangeCategorySeeder::class,
        ]);
    }
}
