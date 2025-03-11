<?php

namespace App\Repositories\PatientVisit;

use App\Models\PatientVisit;

class PatientVisitRepository implements PatientVisitInterface
{
    /**
     * Retrieve a collection of PatientVisit from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return PatientVisit::all();
    }


    /**
     * Create new PatientVisit in the database.
     *
     * @param array $data
     * @return \App\Models\PatientVisit
     */
    public function create(array $data)
    {
        return PatientVisit::create($data);
    }


    /**
     * Update an existing PatientVisit in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function update(array $data, $id)
    {
        $record = PatientVisit::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing PatientVisit from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = PatientVisit::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing PatientVisit in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function find($id)
    {
        return PatientVisit::find($id);
    }


    /**
     * Find an existing PatientVisit in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientVisit
     */
    public function findByAttribute($attr, $value)
    {
        return PatientVisit::where($attr, $value)->first();
    }

    /**
     * Find Membership Request Approval by multiple where clauses in the database.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\AdvertisementRequestApproval
     */
    public function findByMultiAttributes(array $attrs)
    {
        $record = PatientVisit::query();

        foreach ($attrs as $key => $value) {
            if (is_string($key)) {
                $record = $record->where($key, $value);
            } elseif (is_array($value) && count($value) === 3) {
                [$column, $operator, $conditionValue] = $value;
                $record = $record->where($column, $operator, $conditionValue);
            } elseif (is_array($value) && count($value) === 2) {
                [$column, $conditionValue] = $value;
                $record = $record->where($column, $conditionValue);
            }
        }

        return $record->first();

    }
}
