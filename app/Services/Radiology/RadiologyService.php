<?php

namespace App\Services\Radiology;

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

        $radiology = Radiology::with(['patient.visits_recent.billingLogsForPatient', 'pharmacist'])
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where("test_name", $validated['search'])
                    ->whereHas('patient', function ($q1) use ($validated) {
                        $q1->where('firstname', $validated['search'])
                            ->orWhere('lastname', $validated['search'])
                            ->orWhere('patientno', $validated['search']);
                    })
                    ->orWhereHas('patient.visits_recent.billingLogsForPatient', function ($query) use ($validated) {
                        // payment_status
                        $query->where('payment_status', $validated['search']);
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


        $radiology->paginate(10);
    }
}
