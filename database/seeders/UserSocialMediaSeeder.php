<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSocialMedia;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSocialMediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->info('No users found. Please run UserTableSeeder first.');
            return;
        }
        
        foreach ($users as $user) {
            // 70% chance for a user to have social media links
            if (rand(1, 100) <= 70) {
                UserSocialMedia::create([
                    'user_uuid' => $user->uuid,
                    'discord' => rand(1, 100) <= 80 ? "discord_" . $user->user_name : null,
                    'instagram' => rand(1, 100) <= 60 ? "instagram_" . $user->user_name : null,
                    'twitter' => rand(1, 100) <= 70 ? "twitter_" . $user->user_name : null,
                    'tiktok' => rand(1, 100) <= 40 ? "tiktok_" . $user->user_name : null,
                    'youtube' => rand(1, 100) <= 30 ? "youtube_" . $user->user_name : null,
                    'linkedIn' => rand(1, 100) <= 50 ? "linkedin_" . $user->user_name : null,
                ]);
            }
        }
    }
}
