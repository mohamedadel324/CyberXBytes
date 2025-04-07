<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LabCategory;
use App\Models\ChallangeCategory;
use App\Models\Lab;
use App\Models\Challange;
use App\Models\Event;
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
        Event::create([
            'title' => 'Sample Event',
            'description' => 'This is a sample event description.',
            'background_image' => 'background.jpg',
            'image' => 'event_image.jpg',
            'is_private' => false,
            'registration_start_date' => now(),
            'registration_end_date' => now()->addDays(30),
            'team_formation_start_date' => now()->addDays(7),
            'team_formation_end_date' => now()->addDays(25),
            'start_date' => now()->addDays(35),
            'end_date' => now()->addDays(37),
            'requires_team' => true,
            'team_minimum_members' => 2,
            'team_maximum_members' => 5,
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
        
        $labs = Lab::all();
        foreach ($labs as $index => $lab) {
            LabCategory::create([
                'lab_uuid' => $lab->uuid,
                'title' => 'Category ' . ($index + 1),
                'ar_title' => 'الفئة ' . ($index + 1),
                'image' => 'category' . ($index + 1) . '.png',
            ]);
        }

        $labCategories = LabCategory::all();
        foreach ($labCategories as $labCategory) {
            for ($i = 1; $i <= 3; $i++) {
                $challengeCategory = ChallangeCategory::create([
                    'name' => 'Challenge Category ' . $i,
                    'icon' => 'challenge_category' . $i . '.png',
                ]);

                for ($j = 1; $j <= 3; $j++) {
                    Challange::create([
                        'lab_category_uuid' => $labCategory->uuid,
                        'category_uuid' => $challengeCategory->uuid,
                        'title' => 'Challenge ' . $j,
                        'description' => 'Description for Challenge ' . $j,
                        'difficulty' => ['easy', 'medium', 'hard'][array_rand(['easy', 'medium', 'hard'])],
                        'bytes' => rand(10, 100),
                        'flag' => 'flag' . $j,
                        'firstBloodBytes' => rand(100, 1000),
                    ]);
                }
            }
        }

    }
}
