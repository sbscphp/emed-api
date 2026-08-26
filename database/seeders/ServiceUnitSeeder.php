<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\ServiceUnit;
use Illuminate\Database\Seeder;

class ServiceUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $tenant = app('currentTenant');

        $units = [
            ['tenant_id' => $tenant->uuid, 'name' => 'Registration', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Pharmacy', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Consultation', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Laboratory', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'Radiology', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($units as $unit) {
            $serviceUnit = ServiceUnit::firstOrCreate(
                ['name' => $unit['name']],
                ['tenant_id' => $unit['tenant_id'], 'price' => $unit['price']]
            );

            if (!$serviceUnit->tenant_id) {
                $serviceUnit->tenant_id = $unit['tenant_id'];
                $serviceUnit->save();
            }
        }
    }
}
