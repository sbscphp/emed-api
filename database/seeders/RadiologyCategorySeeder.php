<?php

namespace Database\Seeders;

use App\Models\RadiologyCategory;
use Illuminate\Database\Seeder;

class RadiologyCategorySeeder extends Seeder
{
    /**
     * The imaging modalities every hospital starts with.
     *
     * RadiologyTestSeeder groups its tests under these exact names, so the two
     * lists have to stay in step — a name changed here without changing it
     * there leaves those tests uncategorised.
     */
    public const CATEGORIES = [
        'X-RAY IMAGING',
        'ULTRASOUND (SONOGRAPHY)',
        'CT SCAN (COMPUTED TOMOGRAPHY)',
        'MRI (MAGNETIC RESONANCE IMAGING)',
        'MAMMOGRAPHY',
        'FLUOROSCOPY',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            RadiologyCategory::firstOrCreate(['name' => $category], ['status' => true]);
        }
    }
}
