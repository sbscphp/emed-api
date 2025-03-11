<?php

namespace App\Repositories\NextOfKin;

use App\Models\NextOfKin;

class NextOfKinRepository implements NextOfKinInterface
{
    /**
     * Retrieve a collection of NextOfKin from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return NextOfKin::all();
    }


    /**
     * Create new NextOfKin in the database.
     * 
     * @param array $data
     * @return \App\Models\NextOfKin
     */
    public function create(array $data)
    {
        return NextOfKin::create($data);
    }


    /**
     * Update an existing NextOfKin in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\NextOfKin
     */
    public function update(array $data, $id)
    {
        $record = NextOfKin::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing NextOfKin from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = NextOfKin::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing NextOfKin in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\NextOfKin
     */
    public function find($id)
    {
        return NextOfKin::find($id);
    }


    /**
     * Find an existing NextOfKin in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\NextOfKin
     */
    public function findByAttribute($attr, $value)
    {
        return NextOfKin::where($attr, $value)->first();
    }
}
