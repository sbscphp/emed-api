<?php

namespace Database\Seeders;

use App\Models\SuperAdminRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create default super admin role in landlord DB
        $role = SuperAdminRole::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Admin',
                'description' => 'Full access to the super admin panel',
                'status' => 'Active',
            ]
        );

        // Create super admin user in landlord DB
        $user = User::firstOrCreate(
            ['email' => 'superadmin@' . env('APP_NAME') . '.com'],
            [
                'uuid' => Str::uuid(),
                'first_name' => 'Super-Admin',
                'last_name' => env('APP_NAME'),
                'password' => bcrypt('password'),
                'phone_number' => fake()->phoneNumber,
                'status' => 'Active',
                'can_login' => true,
                'is_active' => true,
                'is_verified' => true,
                'is_completed' => true,
                '2fa' => true,
            ]
        );

        // Assign super admin role via landlord pivot
        $user->superAdminRoles()->syncWithoutDetaching([$role->id]);
    }
}
