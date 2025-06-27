<?php

namespace App\Http\Controllers\v1\Admin;

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
use Throwable;

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
        try {
         config(['database.default' => 'tenant']);
            DB::connection('tenant');

            $currentUser = Auth::user();
            $user = User::on('tenant')->find($currentUser->id);
             //$this->userService->find();
              dd($user);
            if (!$user) {
                return JsonResponser::send(true, 'User not found.', null, 404);
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
                return JsonResponser::send(true, 'No users found.', null, 404);
            }

            return JsonResponser::send(false, 'Users retrieved successfully.', [
                'records' => $result,
                'total' => $paginate ? $result->total() : $result->count(),
            ], 200);
        } catch (\Throwable $th) {
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

            // Prepare user data
            $uuid = (string) Str::uuid();
            $hashedPassword = Hash::make($data['password']);
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
                'password'          => $hashedPassword,
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
                return JsonResponser::send(true, 'User not found.', null, 404);
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
                return JsonResponser::send(true, 'User not found.', null, 404);
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
}
