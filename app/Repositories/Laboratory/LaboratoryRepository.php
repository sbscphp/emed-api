<?php

namespace App\Repositories\Laboratory;

use App\Helpers\ExportHelper;
use App\Models\Laboratory;
use App\Responser\JsonResponser;
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

    public function getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage, $export = null)
    {
        // Default payment status to "pending" if not explicitly provided
        $paymentStatus = $paymentStatus ?? 'pending';

        $query = Laboratory::query()
            ->join('patients', 'patient_visit_lab.patient_id', '=', 'patients.id')
            ->leftJoin('billing_logs', 'patient_visit_lab.patient_id', '=', 'billing_logs.patient_id');

        $query->select(
            'patient_visit_lab.*',
            'patients.firstname',
            'patients.lastname',
            'patients.patientno',
            'patients.cardno',
            'billing_logs.id as billing_id',
            'billing_logs.sub_total as billing_amount',
            'billing_logs.payment_status as billing_status'
        );

        // if (!empty($search)) {
        //     $query->where(function ($q) use ($search) {
        //         $q->where('patients.firstname', 'LIKE', "%{$search}%")
        //             ->orWhere('patients.lastname', 'LIKE', "%{$search}%")
        //             ->orWhere('patients.patientno', 'LIKE', "%{$search}%")
        //             ->orWhere('patients.cardno', 'LIKE', "%{$search}%");
        //     });
        // }

        // if (!empty($status)) {
        //     $query->where('patient_visit_lab.test_status', $status);
        // }

        // if (!empty($paymentStatus)) {
        //     $query->where('patient_visit_lab.payment_status', $paymentStatus);
        // }

        // $query->orderBy('patient_visit_lab.created_at', 'desc');

        // if ($export) {
        //     $records = $query->get();

        //     $exportData = $records->map(function ($item) {
        //         return [
        //             'Patient Name' => "{$item->firstname} {$item->lastname}",
        //             'Patient No' => $item->patientno,
        //             'Card No' => $item->cardno,
        //             'Visit No' => $item->visitno,
        //             'Lab Dept' => $item->lab_dept,
        //             'Test Name' => $item->test_name,
        //             'Ordered Tests' => $item->ordered_test,
        //             'Others' => $item->others,
        //             'Test Status' => $item->test_status,
        //             'Payment Status' => $item->payment_status,
        //             'Billing Amount' => $item->billing_amount,
        //             'Billing Status' => $item->billing_status,
        //             'Created At' => $item->created_at->toDateTimeString(),
        //         ];
        //     });

        //     if ($export === 'csv') {
        //         return ExportHelper::streamCsv($exportData->toArray(), null, 'lab-records.csv');
        //     }

        //     if ($export === 'pdf') {
        //         return ExportHelper::downloadPdf($exportData->toArray(), 'lab-records.pdf');
        //     }

        //     return $paginate ? $query->paginate($perPage ?? 10) : $query->get();
        // }

        return $paginate ? $query->paginate($perPage ?? 10) : $query->get();
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
