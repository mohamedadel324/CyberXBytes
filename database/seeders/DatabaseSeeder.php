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
            UserTableSeeder::class,
            UserSocialMediaSeeder::class,
            PlayerTitleSeeder::class,

            // Labs and challenges
            LabTableSeeder::class,
            LabCategorySeeder::class,
            ChallangeCategorySeeder::class,
            ChallangeSeeder::class,

            // Events and teams
            EventSeeder::class,
            EventTeamSeeder::class,
            EventChallangeSeeder::class,
            EventChallangeFlagSeeder::class,
        ]);
    }
}
