<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Events\CreateUserEvent;
use App\Helpers\FileUploadHelper;
use App\Http\Requests\UserUpdateRequest;
use Throwable;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use App\Models\UserInformation;
// use Stancl\Tenancy\Tenancy;
use Stancl\Tenancy\Tenancy;

use Illuminate\Support\Facades\Config;

class UserController extends Controller
{
    protected $userService;

    public function __construct(
        UserService $userService,
    ) {
        $this->userService = $userService;
    }

    public function allUsers(Request $request)
    {
        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();
        try {


            $currentUser = Auth::user();

            $user = User::where('email', $currentUser->email)->first();


            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $filters = [
                'status' => $request->status,
                'role' => $request->role,
            ];

            $search = $request->search;
            $export = $request->export;
            $paginate = $request->boolean('paginate', true);
            $perPage = $request->get('per_page', 20);

            $result = $this->userService->all($filters, $search, $export, $paginate, $perPage);

            if (
                $result instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse ||
                $result instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            ) {
                return $result;
            }

            if ($result->isEmpty()) {
                DB::connection('tenant')->rollBack();
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(true, 'No users found.', null, 200);
            }

            return JsonResponser::send(false, 'Users retrieved successfully.', [
                'records' => $result,
                'total' => $paginate ? $result->total() : $result->count(),
            ], 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }


    public function addUser(StoreUserRequest $request)
    {
        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $data = $request->validated();

            // Validate tenant-side role
            $tenantRole = Role::where('name', $data['role'])->first();
            if (!$tenantRole) {
                return JsonResponser::send(true, 'Invalid role provided (tenant).', [], 422);
            }

            $password = $this->userService->generateSecurePassword();
            // Prepare user data
            $uuid = (string) Str::uuid();
            // $hashedPassword = Hash::make($data['password']);
            $userData = [
                'tenant_id'         => $currentUser->tenant_id,
                'uuid'              => $uuid,
                'fullname'          => $data['fullname'],
                'email'             => $data['email'],
                'role'              => $data['role'],
                'phone_number'      => $data['phone_number'],
                'date_of_birth'     => $data['date_of_birth'],
                'email_verified_at' => now(),
                'can_login'         => 1,
                'is_verified'       => 1,
                'is_active'         => 1,
                'is_change_password' => 1,
                'password'          => $password,
            ];

            $tenantUser = User::create($userData);
            $tenantUser->roles()->attach($tenantRole->id);

            $landlordUser = new \App\Models\Landlord\User($userData);
            $landlordUser->id = $tenantUser->id;
            $landlordUser->save();

            $landlordRole = \App\Models\Landlord\Role::where('name', $data['role'])->first();
            if ($landlordRole) {
                $landlordUser->roles()->attach($landlordRole->id);
            } else {
                DB::connection('tenant')->rollBack();
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(true, 'Role not found in landlord DB.', [], 422);
            }
            event(new CreateUserEvent($data['fullname'], $data['email'],  $password));
            DB::connection('tenant')->commit();
            DB::connection('landlord')->commit();



            return JsonResponser::send(false, 'User created successfully.', $tenantUser, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();

            return JsonResponser::send(true, 'Internal server error.', [], 500);
        }
    }





    public function viewUser($id)
    {
        try {
            $user = $this->userService->find($id);

            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            return JsonResponser::send(false, 'User retrieved successfully.', $user, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function updateUser(UpdateUserRequest $request, $id)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();

            $user = $this->userService->find($id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $data = $request->validated();

            if (isset($data['role'])) {
                $role = Role::where('name', $data['role'])->first();
                if (!$role) {
                    return JsonResponser::send(true, 'Invalid role provided.', [], 422);
                }

                $user->roles()->sync([$role->id]);
            }

            unset($data['role']);

            $updatedUser = $this->userService->update($data, $id);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $user->id,
                'action' => 'Update',
                'action_type' => "Models\User",
                'log_name' => "User updated successfully",
                'description' => "{$currentUser->first_name} {$currentUser->last_name} updated user: {$user->first_name} {$user->last_name}",
                'module_accessed' => ListModuleEnums::Records
            ];

            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();

            return JsonResponser::send(false, 'User updated successfully.', $updatedUser, 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }


    public function deleteUser($id)
    {
        try {
            $currentUser = Auth::user();

            $user = $this->userService->find($id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 200);
            }

            $this->userService->delete($id);

            return JsonResponser::send(false, 'User deleted successfully.', [], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function getPharmacists()
    {
        try {
            $pharmacists = User::whereHas('roles', fn($q) => $q->where('name', 'pharmacy'))
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => "{$user->fullname}",
                        'email' => $user->email,
                    ];
                });

            return JsonResponser::send(false, 'Pharmacists fetched successfully.', $pharmacists);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to fetch pharmacists.', [], 500, $e);
        }
    }

    public function fetch_country_state_city(Request $request)
    {
        DB::connection('tenant')->beginTransaction();
        DB::connection('landlord')->beginTransaction();

        try {
            $data =  $this->userService->fetch_country_state_city($request);
            return JsonResponser::send(false, 'fetch successful.', $data);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500);
        }
    }


    public function run_migration(Request $request,  Tenancy $tenancy)
    {
        $validated  =   $request->validate([
            "path" => 'required|string',
            'type' => "required|string|in:tenant,landlord,both"
        ]);

        $tenants = Tenant::all();

        if ($validated['type'] == 'both') {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => $validated['path'],
                '--force' => true,
            ]);

            foreach ($tenants as  $tenant) {
                $tenantDb = $tenant->database;
                $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenantDb]);

                if (!$dbExists) {
                    logger("Skipping tenant '{$tenantDb}' — database does not exist.");
                    continue;
                }

                DB::purge('tenant');

                Config::set('database.connections.tenant.database',  $tenant?->database);

                DB::reconnect('tenant');

                logger("Running migrations for tenant: " . $tenantDb);



                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => $validated['path'],
                    '--force' => true,
                ]);
            }
            return response()->json(['success' => 'successful migrations']);
        } else if ($validated['type'] == 'landlord') {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => $validated['path'],
                '--force' => true,
            ]);
        } else if ($validated['type'] == 'tenant') {
            foreach ($tenants as  $tenant) {
                $tenantDb = $tenant->database;
                $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenantDb]);

                if (!$dbExists) {
                    logger("Skipping tenant '{$tenantDb}' — database does not exist.");
                    continue;
                }

                DB::purge('tenant');

                Config::set('database.connections.tenant.database',  $tenant?->database);

                DB::reconnect('tenant');

                logger("Running migrations for tenant: " . $tenantDb);



                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => $validated['path'],
                    '--force' => true,
                ]);
            }
            return response()->json(['success' => 'successful migrations']);
        }
    }


    public function user_update(Request $request, $id)
    {

        $user = User::find($id);
        if (!$user) {
            return JsonResponser::send(false, 'User profile not found.');
        }

        $updates = [
            "first_name"    => $request->first_name,
            "last_name"     => $request->last_name,
            "email"         => $request->email,
            "phone_number"  => $request->phone_number,
            "role"          => $request->role,
        ];

        // Only update profile_picture if a new one is provided
        if (!empty($request->profile_picture)) {
            $updates['profile_picture'] = FileUploadHelper::singleStringFileUpload($request->profile_picture, 'profile');
        }

        $user->update($updates);

        return JsonResponser::send(false, 'User updated successfully.', $user->fresh(), 200);
    }


    public function account_deactive($id)
    {
        $user = User::find($id);
        if (!$user) {
            return JsonResponser::send(false, 'User profile not found.');
        }

        $user->status = $user->status == GeneralEnums::ACTIVE->value
            ? GeneralEnums::PENDING->value
            : GeneralEnums::ACTIVE->value;

        $user->save();

        return JsonResponser::send(false, "Account status updated", $user, 200);

        return JsonResponser::send(true, "User not found", null, 404);
    }

    public function account_deletion($id)
    {
        $user = User::find($id);
        if (!$user) {
            return JsonResponser::send(false, 'User profile not found.');
        }
        $user->delete();
        return JsonResponser::send(true, "User deleted successfully found", null, 404);
    }


    public function user_upload_image(Request $request)
    {
        $validated =  $request->validate([
            "profile_picture" => "nullable|string",
            "id" => "required|nullable",
        ]);

        $userInformation  = UserInformation::where("user_id", $validated['user_id'])->first();
        if ($userInformation) {
            $userInformation->update([
                'profile_picture' => $validated['profile_picture']
            ]);

            return JsonResponser::send(false, "Account Image Updated", $userInformation->user, 200);
        }
        return JsonResponser::send(true, "User not found", null, 404);
    }


    public function run_name(Request $request)
    {
        // DB::connection('landlord')->beginTransaction();
        DB::connection('tenant')->beginTransaction();

        try {
            // Landlord
            // $usersLandlord = (new User())->setConnection('landlord')->newQuery()->get();
            // foreach ($usersLandlord as $user) {
            //     $parts = explode(' ', $user->fullname);
            //     if (count($parts) > 0) {
            //         $user->first_name = $parts[0];
            //         $user->last_name = $parts[1] ?? null;
            //         $user->setConnection('landlord')->save();
            //     }
            // }

            // Tenant
            // $tenants = Tenant::all();

            // foreach ($tenants as $tenant) {
            //     $tenantDb = $tenant->database;

            //     $dbExists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$tenantDb]);
            //     if (!$dbExists) {
            //         logger("Skipping tenant '{$tenantDb}' — database does not exist.");
            //         continue;
            //     }

            //     DB::purge('tenant');
            //     Config::set('database.connections.tenant.database', $tenantDb);
            //     DB::reconnect('tenant');

            //     logger("Updating users for tenant: " . $tenantDb);

            //     // Now fetch users from this tenant's DB
            //     $usersTenant = (new User())->setConnection('tenant')->newQuery()->get();

            //     foreach ($usersTenant as $user) {
            //         $parts = explode(' ', $user->fullname);
            //         if (count($parts) > 0) {
            //             $user->first_name = $parts[0];
            //             $user->last_name = $parts[1] ?? null;
            //             $user->setConnection('tenant');
            //             $user->save();
            //         }
            //     }
            // }


            // DB::connection('landlord')->commit();
            DB::connection('tenant')->commit();

            return JsonResponser::send(false, "Success", 200);
        } catch (\Throwable $th) {
            // DB::connection('landlord')->rollBack();
            DB::connection('tenant')->rollBack();
            throw $th; // or return an error response
        }
    }
}
