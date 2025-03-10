<?php

namespace App\Repositories\Patient;

use App\Models\Patient;
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

    public function getAllRecords($search, $paginate, $perPage)
    {
        $query = DB::table('patients as pa')
            ->join('patient_visits as pv', 'pa.id', '=', 'pv.patient_id')
            ->leftJoin('services as se', 'se.id', '=', 'pa.service_id')
            ->select(
                'pa.firstname as firtsname',
                'pa.lastname as lastname',
                'pa.patient_type as patient_type',
                'pa.cardno as cardno',
                'pa.phoneno as phoneno',
                'pv.check_in',
                'pv.check_out',
                'pa.status',
                'se.name'

            );

        if (isset($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('cardno', 'LIKE', "%{$search}%")
                    ->orWhere('patient_type', 'LIKE', "%{$search}%")
                    ->orWhere('phoneno', 'LIKE', "%{$search}%");
            });
        }

        return $paginate ? $query->paginate($perPage) : $query->get();
    }

    public function stats()
    {
        // $query = DB::table('patients');
        // $totalPatients = $query->count();
        // $totalPatientsVisitedToday = $query->where
    }
}
