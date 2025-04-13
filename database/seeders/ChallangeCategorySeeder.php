<?php

namespace Database\Seeders;

use App\Models\ChallangeCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChallangeCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Web',
                'icon' => 'web.png',
            ],
            [
                'name' => 'Cryptography',
                'icon' => 'crypto.png',
            ],
            [
                'name' => 'Forensics',
                'icon' => 'forensics.png',
            ],
            [
                'name' => 'Reverse Engineering',
                'icon' => 'reverse.png',
            ],
            [
                'name' => 'Pwn',
                'icon' => 'pwn.png',
            ],
            [
                'name' => 'Misc',
                'icon' => 'misc.png',
            ],
        ];

        foreach ($categories as $category) {
            ChallangeCategory::create($category);
        }
    }
}
