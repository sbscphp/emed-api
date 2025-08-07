<?php

namespace App\Services\Laboratory;

use App\Helpers\ExportHelper;
use App\Models\Laboratory;
use App\Repositories\Laboratory\LaboratoryInterface;
use Carbon\Carbon;

/**
 * Class LaboratoryService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class LaboratoryService
{
    protected LaboratoryInterface $LaboratoryInterface;
    /**
     * Laboratory constructor.
     *
     * @param LaboratoryInterface $LaboratoryInterface
     */
    public function __construct(LaboratoryInterface $LaboratoryInterface)
    {
        $this->LaboratoryInterface = $LaboratoryInterface;
    }

    /**
     * Retrieve all Laboratory.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->LaboratoryInterface->all();
    }

    /**
     * Create a new Laboratory using the data provided.
     *
     * @param array $data
     * @return \App\Models\Laboratory
     */
    public function create(array $data)
    {
        return $this->LaboratoryInterface->create($data);
    }


    /**
     * Update an existing Laboratory with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function update(array $data, $id)
    {
        return $this->LaboratoryInterface->update($data, $id);
    }


    /**
     * Delete a Laboratory by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->LaboratoryInterface->delete($id);
    }


    /**
     * Find a Laboratory by their ID.
     *
     * @param int $id
     * @return \App\Models\Laboratory
     */
    public function find($id)
    {
        return $this->LaboratoryInterface->find($id);
    }


    /**
     * Find an existing Laboratory  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Laboratory
     */
    public function findByAttribute($attr, $value)
    {
        return $this->LaboratoryInterface->findByAttribute($attr, $value);
    }

    public function getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage, $export, $from, $to)
    {
        // return $this->LaboratoryInterface->getAllLabRecords($search, $status, $paginate, $paymentStatus, $perPage, $export, $from, $to);
        $query = Laboratory::query()
            ->with('consultation.pharmacist')
            ->join('patients', 'patient_visit_lab.patient_id', '=', 'patients.id')
            ->leftJoin('billing_logs', 'patient_visit_lab.patient_id', '=', 'billing_logs.patient_id')
            ->select(
                'patient_visit_lab.*',
                'patients.firstname',
                'patients.lastname',
                'patients.patientno',
                'patients.cardno',
                'billing_logs.id as billing_id',
                'billing_logs.sub_total as billing_amount',
                'billing_logs.payment_status as billing_status'
            );

        // Optional filters (ensure variables are defined before this block: $search, $status, $from, $to, $export, $perPage, $paginate)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('patients.firstname', 'LIKE', "%{$search}%")
                    ->orWhere('patients.lastname', 'LIKE', "%{$search}%")
                    ->orWhere('patients.patientno', 'LIKE', "%{$search}%")
                    ->orWhere('patients.cardno', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $query->where('patient_visit_lab.test_status', $status);
        }

        if (!empty($paymentStatus)) {
            $query->where('patient_visit_lab.payment_status', $paymentStatus);
        }

        if (!empty($from) && !empty($to)) {
            $query->whereBetween('patient_visit_lab.created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay()
            ]);
        }

        $query->orderBy('patient_visit_lab.created_at', 'desc');

        // Export handling
        // if (!empty($export)) {
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
        // }

        // Optional pagination (uncomment if needed)
        return $paginate ? $query->paginate($perPage ?? 10) : $query->get();
    }

    public function getStats()
    {
        return $this->LaboratoryInterface->getStats();
    }
}
