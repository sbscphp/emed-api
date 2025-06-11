<?php

namespace Database\Seeders;

use App\Enums\GeneralEnums;
use App\Enums\RegistrationStepEnum;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserInformation\UserInformationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    protected UserInformationService $userInformationService;

    public function __construct(
        UserInformationService $userInformationService,
    ) {
        $this->userInformationService = $userInformationService;
    }

    public function run()
    {
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();
        $nurseRole = Role::where('name', 'nurse')->first();
        $consultantRole = Role::where('name', 'consultant')->first();
        $laboratoryRole = Role::where('name', 'laboratory')->first();
        $pharmacyRole = Role::where('name', 'pharmacy')->first();
        $billingRole = Role::where('name', 'billing')->first();
        $recordRole = Role::where('name', 'record')->first();

        /*
     * Add Users for each role
     */
        $users = [
            [
                'role' => $superAdminRole,
                'email' => 'superadmin@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Super Admin',
                'role_name' => 'Super Admin'
            ],
            [
                'role' => $adminRole,
                'email' => 'admin@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Admin',
                'role_name' => 'Admin'
            ],
            [
                'role' => $nurseRole,
                'email' => 'nurse@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Nurse',
                'role_name' => 'Nurse'
            ],
            [
                'role' => $consultantRole,
                'email' => 'consultant@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Consultant',
                'role_name' => 'Consultant'
            ],
            [
                'role' => $laboratoryRole,
                'email' => 'laboratory@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Laboratory',
                'role_name' => 'Laboratory'
            ],
            [
                'role' => $pharmacyRole,
                'email' => 'pharmacy@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Pharmacy',
                'role_name' => 'Pharmacy'
            ],
            [
                'role' => $billingRole,
                'email' => 'billing@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Billing',
                'role_name' => 'Billing'
            ],
            [
                'role' => $recordRole,
                'email' => 'record@' . Str::slug(env('APP_NAME')) . '.com',
                'fullname' => 'Record',
                'role_name' => 'Record'
            ]
        ];

        foreach ($users as $userData) {
            if ($userData['role']) {
                $newUser = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'uuid' => Str::uuid(),
                        'fullname' => $userData['fullname'],
                        'role' => $userData['role_name'],
                        'password' => bcrypt('password'),
                        'phone_number' => fake()->phoneNumber,
                        'status' => GeneralEnums::ACTIVE->value,
                        'can_login' => true,
                        'is_active' => true,
                        'is_verified' => true,
                        'is_completed' => true,
                        '2fa' => true
                    ]
                );

                // Assign role and permissions
                $newUser->roles()->sync([$userData['role']->id]);
                $newUser->permissions()->sync($userData['role']->permissions);
            }
        }
    }
}
