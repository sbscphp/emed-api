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

        $serviceUnit = ServiceUnit::where('name', 'Radiology')->first();

        $radiologyTest = [
            'CLINICAL CHEMISTRY' => [
                ['tenant_id' => $tenant->uuid, 'service_unit_id' => $serviceUnit->id, 'name' => 'Basic Metabolic Panel (BMP)', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
                
            ],
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
