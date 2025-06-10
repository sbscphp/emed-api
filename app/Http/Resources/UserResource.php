<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'role' => $this->role,
            'email_verified_at' => $this->email_verified_at,
            'can_login' => $this->can_login,
            'is_active' => $this->is_active,
            'is_verified' => $this->is_verified,
            'is_completed' => $this->is_completed,
            '2fa' => $this->{'2fa'},
            'status' => $this->status,
            'date_of_birth' => $this->date_of_birth,
            'tenant_id' => $this->tenant_id,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->roles,

            // 'roles' => $this->whenLoaded('roles', function () {
            //     return $this->roles->map(function ($role) {
            //         return [
            //             'id' => $role->id,
            //             'name' => $role->name,
            //             'display_name' => $role->display_name,
            //             'description' => $role->description,
            //             'status' => $role->status,
            //             'created_at' => $role->created_at,
            //             'updated_at' => $role->updated_at,
            //         ];
            //     });
            // }),
        ];
    }
}
