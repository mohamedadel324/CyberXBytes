<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\LabCategory;
use Illuminate\Database\Seeder;

class LabCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labs = Lab::all();
        
        if ($labs->isEmpty()) {
            $this->command->info('No labs found. Please run LabTableSeeder first.');
            return;
        }
        
        $categories = [
            [
                'title' => 'Web Security',
                'ar_title' => 'أمن الويب',
                'image' => 'web_security.png',
            ],
         
        ];
        
        foreach ($labs as $lab) {
            foreach ($categories as $category) {
                LabCategory::create([
                    'lab_uuid' => $lab->uuid,
                    'title' => $category['title'],
                    'ar_title' => $category['ar_title'],
                    'image' => $category['image'],
                ]);
            }
        }
    }
}
