<?php

namespace Database\Seeders;

use App\Models\PlayerTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlayerTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $titleRanges = [
            [
                'title' => 'Novice',
                'arabic_title' => ' خالص مبتدئ',
                'from' => 0,
                'to' => 9.99,
            ],
            [
                'title' => 'Beginner',
                'arabic_title' => 'مبتدئ',
                'from' => 10,
                'to' => 24.99,
            ],
            [
                'title' => 'Intermediate',
                'arabic_title' => 'متوسط',
                'from' => 25,
                'to' => 39.99,
            ],
            [
                'title' => 'Advanced',
                'arabic_title' => 'متقدم',
                'from' => 40,
                'to' => 59.99,
            ],
            [
                'title' => 'Expert',
                'arabic_title' => 'محترف',
                'from' => 60,
                'to' => 79.99,
            ],
            [
                'title' => 'Master',
                'arabic_title' => 'محترف',
                'from' => 80,
                'to' => 89.99,
            ],
            [
                'title' => 'Legend',
                'arabic_title' => 'شخصية مثيرة',
                'from' => 90,
                'to' => 100,
            ],
        ];

        PlayerTitle::create([
            'title_ranges' => $titleRanges
        ]);
    }
}
