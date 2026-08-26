<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceUnit;
use App\Models\RadiologyService;
use Carbon\Carbon;

class RadiologyTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $tenant = app('currentTenant');

        $serviceUnit = ServiceUnit::firstOrCreate(
            ['name' => 'Radiology'],
            ['tenant_id' => $tenant?->uuid, 'price' => 0.00]
        );

        $radiologyTest = [
            'X-RAY IMAGING' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Chest X-ray (PA View)', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Chest X-ray (AP View)', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Skull X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Cervical Spine X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Lumbar Spine X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Pelvic X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Abdominal X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Upper Limb X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Lower Limb X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Dental X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Sinus X-ray', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ],

            'ULTRASOUND (SONOGRAPHY)' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Abdominal Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Pelvic Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Obstetric Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Transvaginal Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Transrectal Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Breast Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Thyroid Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Renal Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Scrotal Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Doppler Ultrasound', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Carotid Doppler', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ],

            'CT SCAN (COMPUTED TOMOGRAPHY)' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Brain', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Chest', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Abdomen', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Pelvis', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Spine', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Angiography', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Urogram', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Sinuses', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Whole Body', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Colonography', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'CT Cardiac', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Pelvic CT Scan', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Obstetric CT Scan', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ],

            'MRI (MAGNETIC RESONANCE IMAGING)' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Brain', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Spine', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Abdomen', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Pelvis', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Knee', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Shoulder', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Cardiac', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Angiography', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'MRI Whole Body', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ],

            'MAMMOGRAPHY' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Mammography Screening', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Mammography Diagnostic', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Digital Mammography', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Breast Tomosynthesis', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now]
            ],

            'FLUOROSCOPY' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Fluoroscopy', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Barium Swallow', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Barium Meal', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Barium Enema', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'HSG', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'IVU', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'VCUG', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now]
            ]
        ];

        $allTests = array_merge(...array_values($radiologyTest));
        foreach ($allTests as $test) {
            RadiologyService::firstOrCreate(
                ['name' => $test['name'], 'tenant_id' => $test['tenant_id']],
                $test
            );
        }
    }
}
