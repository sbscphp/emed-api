<?php

namespace App\Console\Commands;

use App\Models\Set;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateDefaultRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-default-record';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $RoleItems = [
            [
                'slug' => 'developer',
                'name' => 'Developer',
                'description' => 'Developer Role',
                'is_active' => 'false',
                'is_default' => 'true',
                'level' => 1,
                'created_by' => 1
            ],
            [
                'slug' => 'superadmin',
                'name' => 'Super Admin',
                'description' => 'Super Admin Role',
                'is_active' => 'false',
                'is_default' => 'true',
                'level' => 2,
                'created_by' => 1
            ],
            [
                'slug' => 'admin',
                'name' => 'Admin',
                'description' => 'Admin Role',
                'is_active' => 'false',
                'is_default' => 'true',
                'level' => 3,
                'created_by' => 1
            ],
            [
                'slug' => 'customer',
                'name' => 'Customer',
                'description' => 'Customer Role',
                'is_active' => 'false',
                'is_default' => 'false',
                'level' => 3,
                'created_by' => 1
            ],
            [
                'slug' => 'finance',
                'name' => 'Finance Manager',
                'description' => 'Finance Manager Role',
                'is_active' => 'true',
                'is_default' => 'false',
                'level' => 3,
                'created_by' => 1
            ],
        ];

        $Permissionitems = [
            [
                'name'        => 'Dashboard',
                'slug'        => 'dashboard',
                'description' => 'Allow user access dashboard functionalities.',
                'model'       => 'Permission',
                'is_active'       => true
            ],
            [
                'name'        => 'User Management',
                'slug'        => 'usermanagement',
                'description' => 'Allow user access user management functionalities.',
                'model'       => 'Permission',
                'is_active'       => true
            ],
            [
                'name'        => 'Audit Trail',
                'slug'        => 'audittrail',
                'description' => 'Allow user access audit trail functionalities.',
                'model'       => 'Permission',
                'is_active'       => true
            ],
            [
                'name'        => 'Settings',
                'slug'        => 'setings',
                'description' => 'Allow user access settings functionalities.',
                'model'       => 'Permission',
                'is_active'       => true
            ],
            [
                'name'        => 'Notification',
                'slug'        => 'notification',
                'description' => 'Allow user access notification functionalities.',
                'model'       => 'Permission',
                'is_active'       => true
            ],
        ];

        $transactions = [
            [
                'modelable_type'       => 'App\\Models\\Due',
                'modelable_id'         => 1,
                'user_id'              => 3,
                'amount_paid'          => 12000.00,
                'transaction_category' => 'credit',
                'status'               => 'successful',
                'payment_date'         => now(),
                'payment_method'       => 'credit_card',
            ],
            [
                'modelable_type'       => 'App\\Models\\Order',
                'modelable_id'         => 2,
                'user_id'              => 4,
                'amount_paid'          => 5000.00,
                'transaction_category' => 'credit',
                'status'               => 'pending',
                'payment_date'         => now(),
                'payment_method'       => 'bank_transfer',
            ],
            // Add more transactions as needed
        ];

        /*
        * Add Role Items
        */
        dump("Running Roles table seeder");
        foreach ($RoleItems as $RoleItem) {
            // Normalize the slug to avoid case sensitivity or spacing issues
            $normalizedSlug = strtolower(trim($RoleItem['slug']));

            $newRoleItem = config('roles.models.role')::where('slug', '=', $normalizedSlug)->first();

            if ($newRoleItem === null) {
                $newRoleItem = config('roles.models.role')::create([
                    'name'          => $RoleItem['name'],
                    'slug'          => $normalizedSlug,
                    'description'   => $RoleItem['description'],
                    'level'         => $RoleItem['level'],
                ]);
            }
        }
        dump("Role table seeder ran successfully");


        /*
         * Add Permission Items
         *
         */
        dump("Running Permission table seeder");
        foreach ($Permissionitems as $Permissionitem) {
            $newPermissionitem = config('roles.models.permission')::where('slug', '=', $Permissionitem['slug'])->first();
            if ($newPermissionitem === null) {
                $newPermissionitem = config('roles.models.permission')::create([
                    'name'          => $Permissionitem['name'],
                    'slug'          => $Permissionitem['slug'],
                    'description'   => $Permissionitem['description'],
                    'model'         => $Permissionitem['model'],
                    'is_active'     => $Permissionitem['is_active'],
                ]);
            }
        }
        dump("Permission table seeder ran successfully");


        dump("Running User table seeder");
        $developerRole = config('roles.models.role')::where('name', '=', 'Developer')->first();
        $superAdminRole = config('roles.models.role')::where('name', '=', 'Super Admin')->first();
        $adminRole = config('roles.models.role')::where('name', '=', 'Admin')->first();
        $financeManagerRole = config('roles.models.role')::where('name', '=', 'Finance Manager')->first();
        $customerRole = config('roles.models.role')::where('name', '=', 'Customer')->first();
        $permissions = config('roles.models.permission')::all();



        /*
         * Add Record
         *
         */
        if (User::where('email', '=', 'admin@hildagourmet.com')->first() === null) {
            $newUser = User::create([
                'firstname'     => 'User',
                'lastname'     => 'Admin',
                'username'     => 'hildagourmetadmin',
                'email'    => 'admin@hildagourmet.com',
                'country' => 'Nigeria',
                'state' => 'Lagos',
                'phoneno' => '09088449933',
                'address' => 'Lagos Nigeria',
                'is_verified' => "true",
                'is_active' => "true",
                'can_login' => "true",
                'email_verified_at' => now(),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => Str::random(10),
            ]);
            $newUser->attachRole($adminRole);
        }

        if (User::where('email', '=', 'developer@hildagourmet.com')->first() === null) {
            $newUser = User::create([
                'firstname'     => 'User',
                'lastname'     => 'Developer',
                'username'     => 'hildagourmetdeveloper',
                'email'    => 'developer@hildagourmet.com',
                'country' => 'Nigeria',
                'state' => 'Lagos',
                'phoneno' => '09088449933',
                'address' => 'Lagos Nigeria',
                'is_verified' => "true",
                'is_active' => "true",
                'can_login' => "true",
                'email_verified_at' => now(),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => Str::random(10),
            ]);
            $newUser->attachRole($developerRole);
        }

        if (User::where('email', '=', 'customer@hildagourmet.com')->first() === null) {
            $newUser = User::create([
                'firstname'     => 'User',
                'lastname'     => 'Customer',
                'username'     => 'hildagourmetcustomer',
                'email'    => 'customer@hildagourmet.com',
                'country' => 'Nigeria',
                'state' => 'Lagos',
                'phoneno' => '09088449933',
                'address' => 'Lagos Nigeria',
                'is_verified' => "true",
                'is_active' => "true",
                'can_login' => "true",
                'email_verified_at' => now(),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => Str::random(10),
            ]);
            $newUser->attachRole($customerRole);
        }

        if (User::where('email', '=', 'superadmin@hildagourmet.com')->first() === null) {
            $newUser = User::create([
                'firstname'     => 'User',
                'lastname'     => 'SuperAdmin',
                'username'     => 'hildagourmetsuperadmin',
                'email'    => 'superadmin@hildagourmet.com',
                'country' => 'Nigeria',
                'state' => 'Lagos',
                'phoneno' => '09088449933',
                'address' => 'Lagos Nigeria',
                'is_verified' => "true",
                'is_active' => "true",
                'can_login' => "true",
                'email_verified_at' => now(),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => Str::random(10),
            ]);
            $newUser->attachRole($superAdminRole);
        }

        if (User::where('email', '=', 'finance@hildagourmet.com')->first() === null) {
            $newUser = User::create([
                'firstname'     => 'User',
                'lastname'     => 'Finance',
                'username'     => 'hildagourmetfinance',
                'email'    => 'finance@hildagourmet.com',
                'country' => 'Nigeria',
                'state' => 'Lagos',
                'phoneno' => '09088449933',
                'address' => 'Lagos Nigeria',
                'is_verified' => "true",
                'is_active' => "true",
                'can_login' => "true",
                'email_verified_at' => now(),
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => Str::random(10),
            ]);
            $newUser->attachRole($financeManagerRole);
        }


        dump("Running Transactions table seeder");
        foreach ($transactions as $transaction) {
            $existingTransaction = Transaction::where('transactionId', $transaction['transactionId'] ?? Str::random(20))->first();
            if (is_null($existingTransaction)) {
                Transaction::create([
                    'modelable_type'       => $transaction['modelable_type'],
                    'modelable_id'         => $transaction['modelable_id'],
                    'user_id'              => $transaction['user_id'],
                    'amount_paid'          => $transaction['amount_paid'],
                    'transaction_category' => $transaction['transaction_category'],
                    'status'               => $transaction['status'],
                    'payment_date'         => $transaction['payment_date'],
                    'payment_method'       => $transaction['payment_method'],
                ]);
            }
        }
        dump("Transactions table seeder ran successfully");
    }
}
