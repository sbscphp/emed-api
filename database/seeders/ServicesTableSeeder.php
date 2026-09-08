<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = app('currentTenant');
        $now = Carbon::now();

        $services = [
            ['tenant_id' => $tenant->uuid, 'name' => 'GOPD', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'SOPD', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'IMMUNIZATION', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'HIV/AIDS', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
            ['tenant_id' => $tenant->uuid, 'name' => 'ANTENATAL', 'price' => 0.00, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(
                ['tenant_id' => $service['tenant_id'], 'name' => $service['name']],
                ['price' => $service['price'], 'status' => true]
            );
        }
    }
}
