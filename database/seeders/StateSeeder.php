<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant; // Import the Tenant model if using spatie/laravel-multitenancy

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = [
            ['id' => 1, 'state_name' => 'Abia State'],
            ['id' => 2, 'state_name' => 'Adamawa State'],
            ['id' => 3, 'state_name' => 'Akwa Ibom State'],
            ['id' => 4, 'state_name' => 'Anambra State'],
            ['id' => 5, 'state_name' => 'Bauchi State'],
            ['id' => 6, 'state_name' => 'Bayelsa State'],
            ['id' => 7, 'state_name' => 'Benue State'],
            ['id' => 8, 'state_name' => 'Borno State'],
            ['id' => 9, 'state_name' => 'Cross River State'],
            ['id' => 10, 'state_name' => 'Delta State'],
            ['id' => 11, 'state_name' => 'Ebonyi State'],
            ['id' => 12, 'state_name' => 'Edo State'],
            ['id' => 13, 'state_name' => 'Ekiti State'],
            ['id' => 14, 'state_name' => 'Enugu State'],
            ['id' => 15, 'state_name' => 'FCT'],
            ['id' => 16, 'state_name' => 'Gombe State'],
            ['id' => 17, 'state_name' => 'Imo State'],
            ['id' => 18, 'state_name' => 'Jigawa State'],
            ['id' => 19, 'state_name' => 'Kaduna State'],
            ['id' => 20, 'state_name' => 'Kano State'],
            ['id' => 21, 'state_name' => 'Katsina State'],
            ['id' => 22, 'state_name' => 'Kebbi State'],
            ['id' => 23, 'state_name' => 'Kogi State'],
            ['id' => 24, 'state_name' => 'Kwara State'],
            ['id' => 25, 'state_name' => 'Lagos State'],
            ['id' => 26, 'state_name' => 'Nasarawa State'],
            ['id' => 27, 'state_name' => 'Niger State'],
            ['id' => 28, 'state_name' => 'Ogun State'],
            ['id' => 29, 'state_name' => 'Ondo State'],
            ['id' => 30, 'state_name' => 'Osun State'],
            ['id' => 31, 'state_name' => 'Oyo State'],
            ['id' => 32, 'state_name' => 'Plateau State'],
            ['id' => 33, 'state_name' => 'Rivers State'],
            ['id' => 34, 'state_name' => 'Sokoto State'],
            ['id' => 35, 'state_name' => 'Taraba State'],
            ['id' => 36, 'state_name' => 'Yobe State'],
            ['id' => 37, 'state_name' => 'Zamfara State'],
        ];

        DB::connection('landlord')->table('states')->insert($states);

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            DB::purge('tenant');
            config(['database.connections.tenant.database' => $tenant->database]);
            DB::reconnect('tenant');

            DB::connection('tenant')->table('states')->insert($states);
        }
    }
}
