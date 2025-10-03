<?php

namespace Database\Seeders;

use App\Enums\GeneralEnums;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch roles once
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole      = Role::where('name', 'admin')->first();
        $nurseRole      = Role::where('name', 'nurse')->first();
        $consultantRole = Role::where('name', 'consultant')->first();
        $laboratoryRole = Role::where('name', 'laboratory')->first();
        $pharmacyRole   = Role::where('name', 'pharmacy')->first();
        $billingRole    = Role::where('name', 'billing')->first();
        $recordRole     = Role::where('name', 'record')->first();

        $users = [
            [
                'role'       => $superAdminRole,
                'email'      => 'superadmin@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Super Admin',
                'first_name' => 'Super',
                'last_name'  => 'Admin',
            ],
            [
                'role'       => $adminRole,
                'email'      => 'admin@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Admin',
                'first_name' => 'Admin',
                'last_name'  => '',
            ],
            [
                'role'       => $nurseRole,
                'email'      => 'nurse@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Nurse',
                'first_name' => 'Nurse',
                'last_name'  => '',
            ],
            [
                'role'       => $consultantRole,
                'email'      => 'consultant@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Consultant',
                'first_name' => 'Consultant',
                'last_name'  => '',
            ],
            [
                'role'       => $laboratoryRole,
                'email'      => 'laboratory@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Laboratory',
                'first_name' => 'Laboratory',
                'last_name'  => '',
            ],
            [
                'role'       => $pharmacyRole,
                'email'      => 'pharmacy@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Pharmacy',
                'first_name' => 'Pharmacy',
                'last_name'  => '',
            ],
            [
                'role'       => $billingRole,
                'email'      => 'billing@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Billing',
                'first_name' => 'Billing',
                'last_name'  => '',
            ],
            [
                'role'       => $recordRole,
                'email'      => 'record@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname'   => 'Record',
                'first_name' => 'Record',
                'last_name'  => '',
            ],
        ];

        foreach ($users as $userData) {
            if ($userData['role']) {
                $newUser = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'uuid'        => Str::uuid(),
                        'fullname'    => $userData['fullname'],
                        'first_name'  => $userData['first_name'],
                        'last_name'   => $userData['last_name'],
                        'password'    => bcrypt('password'),
                        'phone_number' => fake()->phoneNumber,
                        'can_login'   => true,
                        'is_verified' => true,
                        'is_completed' => true,
                        '2fa'         => true,
                        // 'is_active'   => true,
                        // 'status'      => GeneralEnums::ACTIVE->value,
                    ]
                );

                $newUser->roles()->sync([$userData['role']->id]);
                $newUser->permissions()->sync($userData['role']->permissions->pluck('id')->toArray());
            }
        }
    }
}
