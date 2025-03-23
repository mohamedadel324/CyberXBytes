<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Lab;
class LabTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Lab::create([
            'name' => 'المعامل التدريبية' ,
        ]);
        Lab::create([
            'name' => 'المعامل التنافسية' ,
        ]);
        Lab::create([
            'name' => 'الخوادم' ,
        ]);
    }
}
