<?php

namespace App\Repositories\ServiceDepartment;

use App\Models\ServiceDepartment;

class ServiceDepartmentRepository implements ServiceDepartmentInterface
{
    /**
     * Retrieve a collection of ServiceDepartment from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return ServiceDepartment::all();
    }


    /**
     * Create new ServiceDepartment in the database.
     * 
     * @param array $data
     * @return \App\Models\ServiceDepartment
     */
    public function create(array $data)
    {
        return ServiceDepartment::create($data);
    }


    /**
     * Update an existing ServiceDepartment in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function update(array $data, $id)
    {
        $record = ServiceDepartment::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing ServiceDepartment from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = ServiceDepartment::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing ServiceDepartment in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function find($id)
    {
        return ServiceDepartment::find($id);
    }


    /**
     * Find an existing ServiceDepartment in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\ServiceDepartment
     */
    public function findByAttribute($attr, $value)
    {
        return ServiceDepartment::where($attr, $value)->first();
    }
}
