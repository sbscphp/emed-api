<?php

namespace App\Repositories\Patient;

use App\Models\Admission;
use App\Models\Patient;
use App\Models\PatientVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatientRepository implements PatientInterface
{
    /**
     * Retrieve a collection of Patient from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Patient::all();
    }


    /**
     * Create new Patient in the database.
     *
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create(array $data)
    {
        return Patient::create($data);
    }


    /**
     * Update an existing Patient in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update(array $data, $id)
    {
        $record = Patient::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Patient from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Patient::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Patient in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Patient
     */
    public function find($id)
    {
        return Patient::find($id);
    }


    /**
     * Find an existing Patient in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Patient
     */
    public function findByAttribute($attr, $value)
    {
        return Patient::where($attr, $value)->first();
    }

    public function findByMultiAttributes(array $attrs)
    {
        $record = Patient::query();

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

    public function findUserByFirstnameAndLastname($firstname, $lastname)
    {
        $patient = Patient::where('firstname', $firstname)->where('lastname', $lastname)->first();
        return $patient;
    }

    public function findMultipleRecordsByMultiAttributes(array $attrs)
    {
        $record = Patient::query();

        foreach ($attrs as $attr => $value) {
            $record = $record->where($attr, $value);
        }

        return $record->get();
    }

    public function getAllRecords($search, $paginate, $perPage)
    {
        $query = Patient::query();

        if (isset($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('cardno', 'LIKE', "%{$search}%")
                    ->orWhere('patient_type', 'LIKE', "%{$search}%")
                    ->orWhere('phoneno', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');
        return $paginate ? $query->paginate($perPage) : $query->get();
    }



    public function getRecordStats()
    {
        //
        $currentDate = Carbon::now();
        $registeredPatients = Patient::count();
        $admittedPatients = Admission::count();
        $totalPatientsVisitedToday = PatientVisit::whereDate('created_at', $currentDate)->count();
        $totalFollowUp = Patient::where('status', 'follow up')->count();

        return [
            'totalRegisteredPatient' => $registeredPatients,
            'admittedPatients' => $admittedPatients,
            'totalPatientsVisitedToday' => $totalPatientsVisitedToday,
            'numberOfFollowUp' => $totalFollowUp
        ];
    }
}
