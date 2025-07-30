<?php

namespace App\Services\Radiology;

use App\Helpers\FileUploadHelper;
use App\Helpers\UserMgtHelper;
use App\Models\BillingLog;
use App\Models\Radiology;
use App\Models\RadiologyResult;
use App\Repositories\Radiology\RadiologyInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class RadiologyService
 * 
 * This class provides services related to Radiology operations and acts as a 
 * layer between the Controller and the RadiologyRepository.
 */
class RadiologyService
{
    protected RadiologyInterface $RadiologyInterface;
    /**
     * Radiology constructor.
     * 
     * @param RadiologyInterface $RadiologyInterface
     */
    public function __construct(RadiologyInterface $RadiologyInterface)
    {
        $this->RadiologyInterface = $RadiologyInterface;
    }

    /**
     * Retrieve all Radiology.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->RadiologyInterface->all();
    }

    /**
     * Create a new Radiology using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Radiology
     */
    public function create(array $data)
    {
        return $this->RadiologyInterface->create($data);
    }


    /**
     * Update an existing Radiology with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function update(array $data, $id)
    {
        return $this->RadiologyInterface->update($data, $id);
    }


    /**
     * Delete a Radiology by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->RadiologyInterface->delete($id);
    }


    /**
     * Find a Radiology by their ID.
     * 
     * @param int $id
     * @return \App\Models\Radiology
     */
    public function find($id)
    {
        return $this->RadiologyInterface->find($id);
    }


    /**
     * Find an existing Radiology  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Radiology
     */
    public function findByAttribute($attr, $value)
    {
        return $this->RadiologyInterface->findByAttribute($attr, $value);
    }

    public function radiology_list($validated)
    {

        // $radiology = Radiology::with(['consultation.patient_visits.billingLogsForPatient', 'consulted_by', 'consultation.patient'])
        //     ->when(!empty($validated['search']), function ($query) use ($validated) {
        //         $query->where("test_name", $validated['search'])
        //             ->whereHas('consultation.patient', function ($q1) use ($validated) {
        //                 $q1->where('firstname', 'like', "%{$validated['search']}%")
        //                     ->orWhere('lastname', 'like', "%{$validated['search']}%")
        //                     ->orWhere('patientno', 'like', "%{$validated['search']}%");
        //             })
        //             ->orWhereHas('consultation.patient_visits.billingLogsForPatient', function ($query) use ($validated) {
        //                 // payment_status
        //                 $query->where('payment_status', $validated['search']);
        //             });
        //     });

        $radiology = Radiology::with([
            'consultation.patientVisit.billingLogsForPatient',
            'consulted_by',
            'consultation.patient'
        ])
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where("test_name", 'like', "%{$validated['search']}%")
                        ->orWhereHas('consultation.patient', function ($q1) use ($validated) {
                            $q1->where('firstname', 'like', "%{$validated['search']}%")
                                ->orWhere('lastname', 'like', "%{$validated['search']}%")
                                ->orWhere('patientno', 'like', "%{$validated['search']}%");
                        })
                        ->orWhereHas('consultation.patientVisit.billingLogsForPatient', function ($q2) use ($validated) {
                            $q2->where('payment_status', 'like', "%{$validated['search']}%");
                        });
                });
            });


        if (!empty($validated['phone_number'])) {
            $radiology->whereHas('consultation.patient', function ($q1) use ($validated) {
                $q1->where("phoneno", $validated["phone_number"]);
            });
        }

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $radiology->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        if (!empty($validated['test_status'])) {
            $radiology->where('test_name', $validated['test_status']);
        }

        if (!empty($validated['payment_status'])) {
            $radiology->whereHas('consultation.patient_visits.billingLogsForPatient', function ($q1) use ($validated) {
                $q1->where("payment_status", $validated["payment_status"]);
            });
        }

        $today = Radiology::whereDate('created_at', Carbon::today())
            ->distinct('patient_id')
            ->count('patient_id');
        $tested_today = Radiology::whereDate('created_at', Carbon::today())
            ->count();

        $payments =  Radiology::with([
            'consultation.patientVisit',
        ])->get();

        $arr = [];
        foreach ($payments as $payment) {
            $payment->consultation?->patientVisit?->id;

            if ($payment->consultation?->patientVisit?->id) {
                $bill = BillingLog::where("visit_id", $payment->consultation?->patientVisit?->id)->first();
                if ($bill) {
                    $arr[] =  [
                        $bill
                    ];
                }
            }
        }

        return [
            "today" => $today,
            'data' => $radiology->paginate(10),
            'tested_today' => $tested_today,
            'payment_confirm' => count($arr)

        ];
        // return   $radiology->paginate(10);
    }


    public function radiology_patient($validated)
    {
        $radiology =  Radiology::with(['consultation.patientVisit.billingLogsForPatient', 'consulted_by', 'result'])
            ->whereHas('patient', function ($q) use ($validated) {
                $q->where('id', $validated['patient_id']);
            })
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where("test_name", $validated['search'])
                    ->whereHas('patient', function ($q1) use ($validated) {
                        $q1->where('firstname', $validated['search'])
                            ->orWhere('lastname', $validated['search'])
                            ->orWhere('patientno', $validated['search']);
                    })
                    ->orWhereHas('consultation.patientVisit.billingLogsForPatient', function ($query) use ($validated) {
                        // payment_status payment_method
                        $query->where('payment_status', $validated['search'])
                            ->orWhere('payment_method', $validated['search']);
                    });
            });


        if (!empty($validated['phone_number'])) {
            $radiology->whereHas('patient', function ($q1) use ($validated) {
                $q1->where("phoneno", $validated["phone_number"]);
            });
        }

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $radiology->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        if (!empty($validated['test_status'])) {
            $radiology->where('test_name', $validated['test_status']);
        }

        if (!empty($validated['payment_status'])) {
            $radiology->whereHas('consultation.patientVisit.billingLogsForPatient', function ($q1) use ($validated) {
                $q1->where("payment_status", $validated["payment_status"]);
            });
        }


        return   $radiology->paginate(10);
    }

    public function result($data)
    {

        $currentUserInstance = UserMgtHelper::userInstance();
        $userId = $currentUserInstance->id;
        $tenant = $currentUserInstance->tenant->domain;

        $resultImage = isset($data->result_img) && !empty($data->result_img)
            ? FileUploadHelper::singleStringFileUpload($data->result_img, "radiology_results")
            : null;

        // Create radiology result
        $record = RadiologyResult::create([
            'tenant_domain' => $tenant,
            'user_id' => $userId,
            'radiology_id' => $data->radiology_id,
            'patient_id' => $data->patient_id,
            'examination_type' => $data->examination_type,
            'clinical_indication' => $data->clinical_indication,
            'technique' => $data->technique,
            'findings' => $data->findings,
            'result_img' => $resultImage,
        ]);

        return $record;
    }

    public function updateResult($data, $result)
    {
        $currentUserInstance = UserMgtHelper::userInstance();
        $userId = $currentUserInstance->id;

        $resultImage = isset($data->result_img) && !empty($data->result_img)
            ? FileUploadHelper::singleStringFileUpload($data->result_img, "radiology_results")
            : $result->result_img; // Keep existing image if not provided

        // Update radiology result
        $result->update([
            'updated_by' => $userId,
            // 'radiology_id' => $data->radiology_id,
            // 'patient_id' => $data->patient_id,
            'examination_type' => $data->examination_type,
            'clinical_indication' => $data->clinical_indication,
            'technique' => $data->technique,
            'findings' => $data->findings,
            'result_img' => $resultImage,
        ]);

        return $result->refresh();
    }
}
