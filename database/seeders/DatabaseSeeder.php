<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LabCategory;
use App\Models\ChallangeCategory;
use App\Models\Lab;
use App\Models\Challange;
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
        // Add 3 lab categories, one for each lab
        $labs = Lab::all();
        foreach ($labs as $index => $lab) {
            LabCategory::create([
                'lab_uuid' => $lab->uuid,
                'title' => 'Category ' . ($index + 1),
                'ar_title' => 'الفئة ' . ($index + 1),
                'image' => 'category' . ($index + 1) . '.png',
            ]);
        }

        // Add 3 challenge categories for each lab
        $labCategories = LabCategory::all();
        foreach ($labCategories as $labCategory) {
            for ($i = 1; $i <= 3; $i++) {
                $challengeCategory = ChallangeCategory::create([
                    'name' => 'Challenge Category ' . $i,
                    'icon' => 'challenge_category' . $i . '.png',
                ]);

                // Add 3 challenges for each challenge category
                for ($j = 1; $j <= 3; $j++) {
                    Challange::create([
                        'lab_category_uuid' => $labCategory->uuid,
                        'category_uuid' => $challengeCategory->uuid,
                        'title' => 'Challenge ' . $j,
                        'description' => 'Description for Challenge ' . $j,
                        'difficulty' => ['easy', 'medium', 'hard'][array_rand(['easy', 'medium', 'hard'])],
                        'bytes' => rand(10, 100),
                        'flag' => 'flag' . $j,
                        'key_words' => ['keyword1', 'keyword2', 'keyword3'][array_rand(['keyword1', 'keyword2', 'keyword3'])],
                        'firstBloodBytes' => rand(100, 1000),
                        'image' => 'challenge' . $j . '.png',
                    ]);
                }
            }
        }

    }
}
