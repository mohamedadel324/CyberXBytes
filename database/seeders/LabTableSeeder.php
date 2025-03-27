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
            'name' => 'Training Labs' ,
            'ar_name' => 'المعامل التدريبية'
        ]);
        Lab::create([
            'name' => 'Challangin labs' ,
            'ar_name' => 'المعامل التنافسية'
        ]);
        Lab::create([
            'name' => 'Servers' ,
            'ar_name' => 'الخوادم'
        ]);
    }
}
