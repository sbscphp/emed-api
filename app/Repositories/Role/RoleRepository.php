<?php

namespace App\Repositories\Role;

use App\Models\Role;

class RoleRepository implements RoleInterface
{
    /**
     * Retrieve a collection of Role from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($tenantId, $request)
    {
        return Role::query()->where('tenant_id', $tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('display_name', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            });
    }

    /**
     * Create new Role in the database.
     * 
     * @param array $data
     * @return \App\Models\Role
     */
    public function create(array $data)
    {
        return Role::create($data);
    }


    /**
     * Update an existing Role in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Role
     */
    public function update(array $data, $id)
    {
        $record = Role::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Role from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Role::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Role in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Role
     */
    public function find($id)
    {
        return Role::find($id);
    }


    /**
     * Find an existing Role in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Role
     */
    public function findByAttribute($attr, $value)
    {
        return Role::where($attr, $value)->first();
    }
}
