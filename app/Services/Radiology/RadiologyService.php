<?php

namespace App\Services\Radiology;

use App\Models\BillingLog;
use App\Models\Radiology;
use App\Repositories\Radiology\RadiologyInterface;
use Carbon\Carbon;

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

        $payment_confirm = Radiology::with([
            'consultation.patientVisit.billingLogsForPatient' => function ($query) {
                $query->where('payment_status', 'confirmed');
            },
            'consulted_by',
            'consultation.patient'
        ])->count();

        return [
            "today" => $today,
            'data' => $radiology->paginate(10),
            'tested_today' => $tested_today,
            'payment_confirm' => $payment_confirm

        ];
        // return   $radiology->paginate(10);
    }


    public function radiology_patient($validated)
    {
        $radiology =  Radiology::with(['patient.visits_recent.billingLogsForPatient', 'pharmacist'])
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
                    ->orWhereHas('patient.visits_recent.billingLogsForPatient', function ($query) use ($validated) {
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
            $radiology->whereHas('patient.visits_recent.billingLogsForPatient', function ($q1) use ($validated) {
                $q1->where("payment_status", $validated["payment_status"]);
            });
        }


        return   $radiology->paginate(10);
    }
}
