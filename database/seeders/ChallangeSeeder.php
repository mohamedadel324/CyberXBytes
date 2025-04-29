<?php

namespace Database\Seeders;

use App\Models\Challange;
use App\Models\ChallangeCategory;
use App\Models\LabCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChallangeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labCategories = LabCategory::all();
        $challengeCategories = ChallangeCategory::all();
        
        if ($labCategories->isEmpty()) {
            $this->command->info('No lab categories found. Please run LabCategorySeeder first.');
            return;
        }
        
        if ($challengeCategories->isEmpty()) {
            $this->command->info('No challenge categories found. Please run ChallangeCategorySeeder first.');
            return;
        }
        
        $difficulties = ['easy', 'medium', 'hard'];
        
        foreach ($labCategories as $labCategory) {
            foreach ($challengeCategories as $category) {
                // Create 1 challenge for each category
                $difficulty = $difficulties[array_rand($difficulties)];
                $bytes = rand(10, 100);
                $firstBloodBytes = $bytes * 2;
                
                Challange::create([
                    'lab_category_uuid' => $labCategory->uuid,
                    'category_uuid' => $category->uuid,
                    'title' => "{$category->name} Challenge",
                    'description' => "This is a {$difficulty} {$category->name} challenge for {$labCategory->title}.",
                    'difficulty' => $difficulty,
                    'bytes' => $bytes,
                    'flag' => "flag_{$labCategory->uuid}_{$category->uuid}",
                    'firstBloodBytes' => $firstBloodBytes,
                    'made_by' => 'Admin',
                ]);
            }
        }
    }
}
