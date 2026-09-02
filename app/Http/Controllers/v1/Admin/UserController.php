<?php

namespace App\Http\Controllers\v1\Admin;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Enums\RoleEnums;
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
use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Http\Requests\UserUpdateRequest;
use App\Mail\CreateUser;
use App\Mail\ExistingUser;
use App\Models\Permission;
use App\Models\Registration;
use Throwable;
use Illuminate\Support\Facades\Artisan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\UserInformation;
// use Stancl\Tenancy\Tenancy;
use Stancl\Tenancy\Tenancy;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

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
            $tenantId = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantId)->first();
            $tenant->makeCurrent(); // Helper from tenancy to get current tenant instance

            if (!$tenant) {
                return JsonResponser::send(true, 'No tenant context found.', null, 400);
            }

            // Filters
            $filters = [
                'status' => $request->status,
                'role'   => $request->role, // renamed correctly (not department)
                'tenant_id' => $tenantId,
            ];

            $search   = $request->search;
            $export   = $request->export;
            $paginate = $request->boolean('paginate', true);
            $perPage  = $request->get('per_page', 20);

            $result = $this->userService->all($filters, $search, $export, $paginate, $perPage);

            if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
                DB::connection('tenant')->commit();
                DB::connection('landlord')->commit();
                return $result;
            }

            if ($result->isEmpty()) {
                DB::connection('tenant')->rollBack();
                DB::connection('landlord')->rollBack();
                return JsonResponser::send(true, 'No users found.', null, 200);
            }

            DB::connection('tenant')->commit();
            DB::connection('landlord')->commit();

            $total = $paginate && method_exists($result, 'total')
                ? $result->total()
                : $result->count();

            return JsonResponser::send(false, 'Users retrieved successfully.', [
                'records' => $result,
                'total'   => $total,
            ], 200);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function allRoles(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            // Roles a staff account can be given. Patient is left out: it is
            // granted by the patient registration flow, never picked here.
            $record = Role::where('tenant_id', $tenantId)
                ->where('name', '!=', RoleEnums::PATIENT->value)
                ->orderBy('id', 'DESC')
                ->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    // public function addUser(StoreUserRequest $request)
    // {
    //     // Start transactions on both connections
    //     DB::connection('tenant')->beginTransaction();
    //     DB::connection('landlord')->beginTransaction();

    //     try {
    //         $currentUser = Auth::user();
    //         $tenantId = $request->header('X-Tenant-ID');
    //         $tenant = Tenant::where('uuid', $tenantId)->first();
    //         $data = $request->validated();

    //         // 1. Validate role exists in tenant DB
    //         $tenantRole = Role::on('tenant')->where('name', $data['role'])->first();
    //         if (!$tenantRole) {
    //             return JsonResponser::send(true, 'Invalid role provided (tenant).', [], 422);
    //         }

    //         // 2. Prevent duplicates in tenant
    //         $tenantUserExists = User::on('tenant')
    //             ->where('email', $data['email'])
    //             ->orWhere('phone_number', $data['phone_number'])
    //             ->exists();

    //         if ($tenantUserExists) {
    //             return JsonResponser::send(true, 'User email or phone number already exists.', [], 422);
    //         }

    //         // 3. Generate password & UUID
    //         $password = $this->userService->generateSecurePassword();
    //         $uuid = (string) Str::uuid();;

    //         // 4. Prepare common data
    //         $userData = [
    //             'uuid'               => $uuid,
    //             'fullname'           => $data['fullname'],
    //             'first_name'           => $data['first_name'],
    //             'last_name'           => $data['last_name'],
    //             'email'              => $data['email'],
    //             'role'               => $data['role'],
    //             'phone_number'       => $data['phone_number'],
    //             'date_of_birth'      => $data['date_of_birth'],
    //             'email_verified_at'  => now(),
    //             'can_login'          => 1,
    //             'is_verified'        => 1,
    //             'is_active'          => 1,
    //             'is_change_password' => 1,
    //             'password'           => bcrypt($password),
    //         ];

    //         // 5. Create tenant-side user
    //         $tenantUser = User::on('tenant')->create(array_merge(
    //             $userData,
    //             ['tenant_id' => $currentUser->tenant_id]
    //         ));
    //         $tenantUser->roles()->attach($tenantRole->id);

    //         // 6. Create landlord-side user
    //         $landlordRole = Role::on('landlord')->where('name', $data['role'])->first();
    //         if (!$landlordRole) {
    //             DB::connection('tenant')->rollBack();
    //             DB::connection('landlord')->rollBack();
    //             return JsonResponser::send(true, 'Role not found in landlord DB.', [], 422);
    //         }

    //         $landlordUser = User::on('landlord')->create(array_merge(
    //             $userData,
    //             ['tenant_id' => $currentUser->tenant_id]
    //         ));
    //         $landlordUser->roles()->attach($landlordRole->id);

    //         // 7. Send onboarding email
    //         event(new CreateUserEvent($data['fullname'], $data['email'], $password));

    //         // Commit transactions
    //         DB::connection('tenant')->commit();
    //         DB::connection('landlord')->commit();

    //         return JsonResponser::send(false, 'User created successfully.', $tenantUser, 200);
    //     } catch (\Throwable $th) {
    //         DB::connection('tenant')->rollBack();
    //         DB::connection('landlord')->rollBack();

    //         return JsonResponser::send(true, 'Internal server error.', $th->getMessage(), 500);
    //     }
    // }

    public function addUser(StoreUserRequest $request)
    {
        DB::connection('landlord')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $tenantUuid = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantUuid)->firstOrFail();

            // 1️⃣ Fetch tenant-scoped role (use numeric tenant ID!)
            $tenantRole = Role::where('tenant_id', $tenantUuid)->find($request['role']);
            if (!$tenantRole) {
                return JsonResponser::send(true, 'Invalid role selected.', [], 422);
            }

            // Patient accounts are created by registering a patient record, not
            // from the staff user form, and they never appear in this list.
            if ($tenantRole->name === RoleEnums::PATIENT->value) {
                return JsonResponser::send(true, 'Patient accounts are created from patient registration.', [], 422);
            }

            // 2️⃣ Check if user exists in landlord DB
            $user = User::on('landlord')
                ->where('email', $request['email'])
                ->orWhere('phone_number', $request['phone_number'])
                ->first();

            $isNewUser = false;

            if (!$user) {
                // 3️⃣ Generate secure password & UUID
                $password = $this->userService->generateRoleBasedPassword(
                    $tenantRole->name,
                    $request['first_name'],
                    $request['last_name']
                );

                $uuid = (string) Str::uuid();

                // 4️⃣ Create user in landlord DB
                $user = User::on('landlord')->create([
                    'uuid'         => $uuid,
                    'fullname'     => $request['fullname'],
                    'first_name'   => $request['first_name'],
                    'last_name'    => $request['last_name'],
                    'email'        => $request['email'],
                    'phone_number' => $request['phone_number'],
                    'password'     => bcrypt($password),
                    'can_login'    => 1,
                    'is_verified'  => 1,
                    'is_active'    => 1,
                ]);

                $isNewUser = true;
            }

            // 5️⃣ Detach any existing roles **for this tenant only**
            $tenantRoleIds = Role::where('tenant_id', $tenantUuid)->pluck('id');
            $user->roles()->detach($tenantRoleIds);

            // 6️⃣ Attach the tenant role
            $user->roles()->attach($tenantRole->id);

            // 7️⃣ Attach permissions from tenant role
            $permissions = $tenantRole->permissions()->pluck('id')->toArray();
            if (!empty($permissions)) {
                $user->permissions()->syncWithoutDetaching($permissions);
            }

            // 8️⃣ Attach user to tenant via tenant_users table
            $tenantUser = TenantUser::on('landlord')->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id'   => $user->id,
                ],
                [
                    'status'        => $request['status'] ?: GeneralEnums::ACTIVE->value,
                    'date_of_birth' => $request['date_of_birth'],
                    'is_active'     => 1,
                ]
            );

            // 9️⃣ Send onboarding email only if new user
            if ($isNewUser) {
                $maildata = [
                    'email'         => $request['email'],
                    'name'          => $request['fullname'],
                    'hospital_name' => $tenant->name,
                    'password'      => $password,
                ];
                Mail::to($request['email'])->send(new CreateUser($maildata));
            } else {
                $maildata = [
                    'email'         => $request['email'],
                    'name'          => $request['fullname'],
                    'hospital_name' => $tenant->name,
                ];
                Mail::to($request['email'])->send(new ExistingUser($maildata));
            }

            DB::connection('landlord')->commit();

            return JsonResponser::send(false, 'User added successfully to the tenant.', $user->load('roles', 'permissions'), 200);
        } catch (\Throwable $th) {
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', $th->getMessage(), 500);
        }
    }

    public function viewUser(Request $request, $id)
    {
        try {
            $tenantUuid = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantUuid)->first();
            if (!$tenant) {
                return JsonResponser::send(true, 'Invalid tenant.', null, 400);
            }
            $tenant->makeCurrent();

            // Fetch user from landlord DB
            $user = User::on('landlord')->find($id);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
            }

            // Ensure user belongs to this tenant
            $tenantUser = DB::connection('landlord')
                ->table('tenant_users')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$tenantUser) {
                return JsonResponser::send(true, 'This user does not belong to the selected tenant.', null, 403);
            }

            // Fetch **tenant-specific role**
            $currentRole = Role::on('tenant')
                ->join('role_user', 'roles.id', '=', 'role_user.role_id')
                ->where('role_user.user_id', $user->id)
                ->where('roles.tenant_id', $tenant->uuid)
                ->first(['roles.id', 'roles.name', 'roles.display_name']);

            $rolePermissions = [];
            if ($currentRole) {
                $rolePermissions = Permission::on('tenant')
                    ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
                    ->where('permission_role.role_id', $currentRole->id)
                    ->get(['permissions.id', 'permissions.name', 'permissions.display_name']);
            }

            $user = $user->toArray();
            $user['current_tenant_user'] = $tenantUser;
            $user['current_role'] = $currentRole ? [
                'id'           => $currentRole->id,
                'name'         => $currentRole->name,
                'display_name' => $currentRole->display_name,
            ] : null;

            $user['permissions'] = $rolePermissions;

            return JsonResponser::send(false, 'User retrieved successfully.', $user, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    public function updateUser(UpdateUserRequest $request, $userId)
    {
        DB::connection('landlord')->beginTransaction();

        try {
            $tenantUuid = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantUuid)->firstOrFail();

            // Fetch user
            $user = User::on('landlord')->findOrFail($userId);

            // Fetch tenant-scoped role (corrected!)
            $tenantRole = Role::where('tenant_id', $tenantUuid)
                ->find($request['role']);

            if (!$tenantRole) {
                return JsonResponser::send(true, 'Invalid role selected.', [], 422);
            }

            // Update user fields
            $user->update([
                'first_name'   => $request['first_name'],
                'last_name'    => $request['last_name'],
                'phone_number' => $request['phone_number'],
            ]);

            // Detach only roles related to this tenant
            $tenantRoleIds = Role::where('tenant_id', $tenantUuid)->pluck('id');
            $user->roles()->detach($tenantRoleIds);

            // Attach the new tenant role
            $user->roles()->attach($tenantRole->id);

            // Sync permissions for this tenant role
            $permissions = $tenantRole->permissions()->pluck('id')->toArray();
            $user->permissions()->sync($permissions);

            // Update TenantUser row (keep existing values when a field is not supplied)
            $tenantUser = TenantUser::on('landlord')->firstOrNew([
                'tenant_id' => $tenant->id,
                'user_id'   => $user->id,
            ]);

            $tenantUser->status        = $request['status'] ?: ($tenantUser->status ?: GeneralEnums::ACTIVE->value);
            $tenantUser->date_of_birth = $request['date_of_birth'] ?: $tenantUser->date_of_birth;
            $tenantUser->is_active     = $request['is_active'] ?? $tenantUser->is_active ?? 1;
            $tenantUser->save();

            DB::connection('landlord')->commit();

            return JsonResponser::send(false, 'User updated successfully.', $user->load('roles', 'permissions'), 200);
        } catch (\Throwable $th) {

            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error.', $th->getMessage(), 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $tenant = Tenant::where('uuid', $tenantId)->firstOrFail();
        $tenantUser = TenantUser::on('landlord')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $id)
            ->first();
        if (!$tenantUser) {
            return JsonResponser::send(false, 'User profile not found.');
        }

        $tenantUser->status = $tenantUser->status == GeneralEnums::ACTIVE->value
            ? GeneralEnums::INACTIVE->value
            : GeneralEnums::ACTIVE->value;

        $tenantUser->save();

        return JsonResponser::send(false, "Account status updated", $tenantUser, 200);
    }


    public function deleteUser(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantId)->firstOrFail();

            $tenantUser = TenantUser::on('landlord')
                ->where('tenant_id', $tenant->id)
                ->where('user_id', $id)
                ->first();

            if (!$tenantUser) {
                return JsonResponser::send(true, 'User is not assigned to this tenant.', [], 404);
            }
            $tenantUser->delete();

            return JsonResponser::send(false, 'User removed from this tenant successfully.', [], 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', $th->getMessage(), 500);
        }
    }

    public function getPharmacists(Request $request)
    {
        try {
            $tenantUuid = $request->header('X-Tenant-ID');
            $tenant = Tenant::where('uuid', $tenantUuid)->firstOrFail();
            $tenant->makeCurrent();
            $tenantId = $tenant->id;
            $tenantDb = DB::connection('tenant')->getDatabaseName();

            $pharmacists = User::query()
                ->select('users.*')
                ->join('tenant_users', 'tenant_users.user_id', '=', 'users.id')
                ->where('tenant_users.tenant_id', $tenantId)
                ->whereNull('tenant_users.deleted_at')
                ->with(['tenantUsers' => function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }])
                ->whereExists(function ($sub) use ($tenantDb, $tenant) {
                    $sub->select(DB::raw(1))
                        ->from("{$tenantDb}.role_user")
                        ->join("{$tenantDb}.roles", "roles.id", "=", "role_user.role_id")
                        ->whereRaw("role_user.user_id = users.id")
                        ->where("roles.tenant_id", $tenant->uuid)
                        ->where("roles.name", 'pharmacy');
                })
                ->orderByDesc('users.id')
                ->get();

            $pharmacists = $pharmacists->transform(function ($user) use ($tenant) {
                $userRole = $user->roles()
                    ->where('roles.tenant_id', $tenant->uuid)
                    ->where('roles.name', 'pharmacy')
                    ->first(['roles.id', 'roles.name', 'roles.display_name']);

                $user->userRole = $userRole;
                unset($user->roles);

                return $user;
            });

            return JsonResponser::send(false, 'Pharmacists fetched successfully.', $pharmacists);
        } catch (\Throwable $e) {
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
            return response()->json(['success' => 'successful migrations']);
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
        $tenantId = $request->header('X-Tenant-ID');
        $tenant = Tenant::where('uuid', $tenantId)->firstOrFail();
        $user = User::find($id);
        if (!$user) {
            return JsonResponser::send(false, 'User profile not found.');
        }

        $tenantUser = TenantUser::on('landlord')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $id)
            ->first();

        if (!$tenantUser) {
            return JsonResponser::send(true, 'User is not assigned to this tenant.', [], 404);
        }

        $updates = [
            "first_name"    => $request->first_name,
            "last_name"     => $request->last_name,
            "email"         => $request->email,
            "phone_number"  => $request->phone_number,
            "role"          => $request->role,
        ];

        $user->update($updates);

        // Only update profile_picture if a new one is provided
        if ($request->filled('profile_picture')) {
            $tenantUser->profile_picture = FileUploadHelper::singleStringFileUpload($request->profile_picture, 'profile');
            $tenantUser->save();
        }

        // Attach tenantUser profile_picture to user object
        $userWithTenantData = $user->fresh()->toArray();
        $userWithTenantData['current_tenant_user'] = $tenantUser;

        return JsonResponser::send(false, 'User updated successfully.', $userWithTenantData, 200);
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
    }

    public function account_deletion($id)
    {
        $user = User::find($id);
        if (!$user) {
            return JsonResponser::send(false, 'User profile not found.');
        }
        $user->delete();
        return JsonResponser::send(true, "User deleted successfully found", null, 200);
    }

    public function hospital_information(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $hospital = Tenant::where('uuid', $tenantId)->firstOrFail();
        if (!$hospital) {
            return JsonResponser::send(false, 'Hospital record not found.');
        }
        $user = Auth::user();
        $updates = [
            "theme_color"    => $request->theme_color,
            "updated_by"    => $user->id,
        ];

        // Only update profile_picture if a new one is provided
        if (!empty($request->logo)) {
            $updates['logo'] = FileUploadHelper::singleStringFileUpload($request->logo, 'logo');
        }

        $hospital->update($updates);

        return JsonResponser::send(true, "Record updated successfully", $hospital->refresh(), 200);
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
