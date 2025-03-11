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
        $customerRole = Role::where('name', 'customer')->first();
        $guestRole = Role::where('name', 'guest')->first();

        /*
         * Add Users
         *
         */
        if (User::where('email', '=', 'superadmin@' . Str::slug(env('APP_NAME')) . '.com')->first() === null) {
            $newUser = User::updateOrCreate(
                [
                    'email' => 'superadmin@' . Str::slug(env('APP_NAME')) . '.com',

                ],
                [
                    'uuid' => Str::uuid(),
                    'fullname'     => 'Super-Admin',
                    'role'  => "Super Admin",
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
            $newUser->addRole($superAdminRole);
            $newUser->permissions()->sync($superAdminRole->permissions);
        }

        if (User::where('email', '=', 'customer@' . Str::slug(env('APP_NAME')) . '.com')->first() === null) {
            $newUser = User::updateOrCreate(
                [
                    'email' => 'customer@' . Str::slug(env('APP_NAME')) . '.com',
                ],
                [
                    'uuid' => Str::uuid(),
                    'fullname'     => 'Customer',
                    'role'  => "Customer",
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

            $newUser->addRole($customerRole);
            $newUser->permissions()->sync($customerRole->permissions);
        }

        if (User::where('email', '=', 'guest@' . Str::slug(env('APP_NAME')) . '.com')->first() === null) {
            $newUser = User::updateOrCreate(
                [
                    'email' => 'guest@' . Str::slug(env('APP_NAME')) . '.com',
                ],
                [
                    'uuid' => Str::uuid(),
                    'fullname'     => 'Guest',
                    'role'  => "Guest",
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

            $newUser->addRole($guestRole);
            $newUser->permissions()->sync($guestRole->permissions);

            $this->userInformationService->create([
                'user_id' => $newUser->id,
                'uuid' => Str::uuid(),
                'phone_number' => $newUser->phone_number,
                'date_of_birth' => fake()->date(),
                'address' => fake()->address,
                'city' => fake()->city,
                'post_code' => fake()->postcode,
                'state' => fake()->state,
                'country' => fake()->country,
                'profile_picture' => null,
                'status' => GeneralEnums::ACTIVE->value,
            ]);
        }
    }
}
