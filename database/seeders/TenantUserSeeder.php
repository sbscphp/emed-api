<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenant = Tenant::whereDomain(env('APP_URL'))->first();

        if(is_null($tenant)){
            $tenant = Tenant::create([
                'name' => 'Landlord Tenant',
                'domain' => env('TENANT_URL'), // we are using domain-based (single db) tenant identification, please set the URL in the .env file
                'database' => env('DB_DATABASE'),
            ]);
       }

        $tenants = Tenant::all();
        $users = User::all();

        foreach ($tenants as $tenant) {
            $tenant->users()->attach(
                $users->random(rand(1, 3))->pluck('id')->toArray()
            );
        }
    }
}
