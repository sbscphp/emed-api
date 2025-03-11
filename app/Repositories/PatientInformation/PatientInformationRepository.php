<?php

namespace App\Repositories\PatientInformation;

use App\Models\PatientInformation;

class PatientInformationRepository implements PatientInformationInterface
{
    /**
     * Retrieve a collection of PatientInformation from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return PatientInformation::all();
    }


    /**
     * Create new PatientInformation in the database.
     * 
     * @param array $data
     * @return \App\Models\PatientInformation
     */
    public function create(array $data)
    {
        return PatientInformation::create($data);
    }


    /**
     * Update an existing PatientInformation in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientInformation
     */
    public function update(array $data, $id)
    {
        $record = PatientInformation::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing PatientInformation from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = PatientInformation::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing PatientInformation in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\PatientInformation
     */
    public function find($id)
    {
        return PatientInformation::find($id);
    }


    /**
     * Find an existing PatientInformation in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientInformation
     */
    public function findByAttribute($attr, $value)
    {
        return PatientInformation::where($attr, $value)->first();
    }
}
