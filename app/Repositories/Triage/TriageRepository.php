<?php

namespace App\Repositories\Triage;

use App\Models\Triage;

class TriageRepository implements TriageInterface
{
    /**
     * Retrieve a collection of Triage from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Triage::all();
    }


    /**
     * Create new Triage in the database.
     * 
     * @param array $data
     * @return \App\Models\Triage
     */
    public function create(array $data): Triage
    {
        return Triage::create($data);
    }

    public function updateOrCreate(array $conditions, array $data)
    {
        return Triage::updateOrCreate($conditions, $data);
    }

    /**
     * Update an existing Triage in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Triage
     */
    public function update(array $data, $id)
    {
        $record = Triage::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Triage from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Triage::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Triage in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Triage
     */
    public function find($id)
    {
        return Triage::find($id);
    }


    /**
     * Find an existing Triage in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Triage
     */
    public function findByAttribute($attr, $value)
    {
        return Triage::where($attr, $value)->first();
    }

    public function getByPatientId(int $patientId)
    {
        return Triage::where('patient_id', $patientId)->get();
    }
}
