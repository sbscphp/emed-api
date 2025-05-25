<?php

namespace App\Repositories\Laboratory;

use App\Models\Laboratory;
use Carbon\Carbon;

class LaboratoryRepository implements LaboratoryInterface
{
    /**
     * Retrieve a collection of Laboratory from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Laboratory::all();
    }


    /**
     * Create new Laboratory in the database.
     *
     * @param array $data
     * @return \App\Models\Laboratory
     */
    public function create(array $data)
    {
        return Laboratory::create($data);
    }


    /**
     * Update an existing Laboratory in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function update(array $data, $id)
    {
        $record = Laboratory::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Laboratory from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Laboratory::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Laboratory in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function find($id)
    {
        return Laboratory::find($id);
    }


    /**
     * Find an existing Laboratory in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Laboratory
     */
    public function findByAttribute($attr, $value)
    {
        return Laboratory::where($attr, $value)->first();
    }

    public function getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage)
    {
        $query = Laboratory::query()
            ->join('patients', 'patient_visit_lab.patient_id', '=', 'patients.id')
            ->join('billing_logs', 'patient_visit_lab.patient_id', '=', 'billing_logs.patient_id');
        $query->select(
            'patient_visit_lab.*',
            'patients.firstname',
            'patients.lastname',
            'patients.patientno',
            'patients.cardno',
            'billing_logs.*'
        );

        if (isset($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('patientno', 'LIKE', "%{$search}%")
                    ->orWhere('cardno', 'LIKE', "%{$search}%");
            });
        }

        if (isset($status)) {
            $query->where('patient_visit_lab.test_status', $status);
        }

        if (isset($paymentStatus)) {
            $query->where('patient_visit_lab.payment_status', $paymentStatus);
        }

        $query->orderBy('patient_visit_lab.created_at', 'desc');

        return $paginate ? $query->paginate($perPage) : $query->get();
    }

    public function getStats()
    {
        $today = Carbon::today();
        $query = Laboratory::query();

        $totalPatients = $query->where('created_at', $today)->count();
        $completedTestToday = $query->where('test_status', 'completed')->where('updated_at', $today)->count();
        $confirmedPaymentToday = $query->where('payment_status', 'paid')->where('updated_at', $today)->count(); // check back, should come from billing_logs

        return [
            'totalPatientsToday' => $totalPatients ?? 0,
            'completedTestToday' => $completedTestToday ?? 0,
            'confirmedPaymentToday' => $confirmedPaymentToday ?? 0
        ];
    }
}
