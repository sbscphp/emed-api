<?php

namespace App\Repositories\PatientVisit;

use App\Models\PatientVisit;
use Carbon\Carbon;
use App\Http\Resources\PatientResources;

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
        $patientcreate = PatientVisit::create($data);
        $data = $patientcreate->load('patient');
        return  PatientResources::make($data);
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

    public function getPatients($search, $sortBy, $stage, $status, $date, $paginate, $perPage)
    {
        $query = PatientVisit::with(['patient']);
        $query->select(
            'patient_id',
            'visitno',
            'arrival_date',
            'stage',
            'status'
        );

        if (isset($search)) {
            $query->where('visitno', 'like', '%' . $search . '%')
                ->orWhere('arrival_date', 'like', '%' . $search . '%')
                ->orWhereHas('patient', function ($q) use ($search) {
                    $q->where('firstname', 'like', '%' . $search . '%')
                        ->orWhere('lastname', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('patientno', 'like', '%' . $search . '%');
                });
        }

        if ($stage) {
            $query->where('stage', $stage);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($sortBy) {
            $query->orderBy('created_at', $sortBy);
        }

        if ($paginate) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Patients for consultation
     *
     * @param [type] $date
     * @return void
     */
    public function getPatientForConsultation($search, $sortBy, $date = Null, $paginate, $perPage, $patient_type)
    {
        $query = PatientVisit::with(['patient', 'patient.triage']);
        // with(['patient', 'patient.triage']);
        // $query->join('billings', 'patient_visits.visitno', '=', 'billings.visitno');
        // $query->select(
        //     'patient_id',
        //     'visitno',
        //     'arrival_date',
        //     'departure_date',
        //     'stage',
        //     'status'
        // );

        if (isset($search)) {
            $query->where('visitno', 'like', '%' . $search . '%')
                ->orWhere('arrival_date', 'like', '%' . $search . '%')
                ->orWhereHas('patient', function ($q) use ($search) {
                    $q->where('firstname', 'like', '%' . $search . '%')
                        ->orWhere('lastname', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('patientno', 'like', '%' . $search . '%')
                        ->orWhere('cardno', 'like', '%' . $search . '%');
                });
        }

        if (isset($date)) {
            $query->whereDate('arrival_date', Carbon::parse($date)->toDateString());
        }

        if (!empty($patient_type)) {
            $query->whereHas('patient',  function ($q) use ($patient_type) {
                $q->where('patient_type',  $patient_type);
            });
        }

        $query->where('stage', 'consultation');
        $query->where('status', 'ongoing');
        // $query->where('billings.payment_status', 'paid');
        $query->orderBy('created_at', $sortBy);

        return $paginate ? $query->paginate($perPage) : $query->get();
    }

    public function getPatientVisits($patientId)
    {
        $patientVisits = PatientVisit::where('patient_id', $patientId)
            ->orderBy('arrival_date', 'desc')
            ->get();

        return $patientVisits;
    }


    public function getPatientPreviousVisits($patientId, $visitNo)
    {
        $patientPreviousVisits = PatientVisit::where('patient_id', $patientId)
            ->where('visitno', '!==', $visitNo)
            ->orderBy('arrival_date', 'desc')
            ->take(4)
            ->get();

        return $patientPreviousVisits;
    }
}
