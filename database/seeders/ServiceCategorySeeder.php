<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'CLINICAL CHEMISTRY',
            'HAEMATOLOGY',
            'MICROBIOLOGY',
            'SEROLOGY',
            'PARASITOLOGY',
            'HISTOPATHOLOGY',
            'LIPID PROFILE',
            'IMMUNOLOGY',
            'ENDOCRINOLOGY',
            'PRENATAL SCREENING',
            'TUMOR MARKERS'
        ];

        foreach ($categories as $category) {
            ServiceCategory::firstOrCreate(['name' => $category], ['status' => true]);
        }
    }
}
