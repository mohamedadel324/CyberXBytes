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
            'ar_name' => 'المعامل التدريبية',
            'description' => 'Training Labs Description',
            'ar_description' => 'وصف المعامل التدريبية باللغة العربية'
        ]);
        Lab::create([
            'name' => 'Challangin labs' ,
            'ar_name' => 'المعامل التنافسية',
            'description' => 'Challangin labs Description',
            'ar_description' => 'وصف المعامل التنافسية باللغة العربية'
        ]);
        Lab::create([
            'name' => 'Servers' ,
            'ar_name' => 'الخوادم',
            'description' => 'Servers Description',
            'ar_description' => 'وصف الخوادم باللغة العربية'
        ]);
    }
}
