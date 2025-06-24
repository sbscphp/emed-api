<?php

namespace App\Services\PatientVisit;

use App\Models\PatientVisit;
use App\Repositories\PatientVisit\PatientVisitInterface;
use App\Http\Resources\PatientVisitResources;

/**
 * Class PatientVisitService
 *
 * This class provides services related to PatientVisit operations and acts as a
 * layer between the Controller and the PatientVisitRepository.
 */
class PatientVisitService
{
    protected PatientVisitInterface $PatientVisitInterface;
    /**
     * PatientVisit constructor.
     *
     * @param PatientVisitInterface $PatientVisitInterface
     */
    public function __construct(PatientVisitInterface $PatientVisitInterface)
    {
        $this->PatientVisitInterface = $PatientVisitInterface;
    }

    /**
     * Retrieve all PatientVisit.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->PatientVisitInterface->all();
    }

    /**
     * Create a new PatientVisit using the data provided.
     *
     * @param array $data
     * @return \App\Models\PatientVisit
     */
    public function create(array $data)
    {
        return $this->PatientVisitInterface->create($data);
    }


    /**
     * Update an existing PatientVisit with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function update(array $data, $id)
    {
        return $this->PatientVisitInterface->update($data, $id);
    }


    /**
     * Delete a PatientVisit by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PatientVisitInterface->delete($id);
    }


    /**
     * Find a PatientVisit by their ID.
     *
     * @param int $id
     * @return \App\Models\PatientVisit
     */
    public function find($id)
    {
        return $this->PatientVisitInterface->find($id);
    }


    /**
     * Find an existing PatientVisit  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\PatientVisit
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PatientVisitInterface->findByAttribute($attr, $value);
    }

    public function findByMultiAttributes(array $attrs)
    {
        return $this->PatientVisitInterface->findByMultiAttributes($attrs);
    }

    public function getPatientForConsultation($search, $sortBy, $date, $paginate, $perPage)
    {
        return $this->PatientVisitInterface->getPatientForConsultation($search, $sortBy, $date, $paginate, $perPage);
    }

    public function getPatientVisits($patientId)
    {
        return $this->PatientVisitInterface->getPatientVisits($patientId);
    }

    public function getPatientPreviousVisits($patientId, $visitNo)
    {
        return $this->PatientVisitInterface->getPatientPreviousVisits($patientId, $visitNo);
    }

    public function getPatients($search, $sortBy, $stage, $status, $date, $paginate, $perPage)
    {
        return $this->PatientVisitInterface->getPatients($search, $sortBy, $stage, $status, $date, $paginate, $perPage);
    }

    public function getByPatientId($patientId): ?PatientVisit
    {
        return PatientVisit::where('patient_id', $patientId)->first();
    }

    public function updateStage(PatientVisit $visit, string $stage): void
    {
        $visit->update(['stage' => $stage]);
    }

    public function getAllFiltered(?string $search, bool $paginate, int $perPage, array $filters = [])
    {
        $query = PatientVisit::with([
            'patient:id,firstname,lastname,patientno,service_id',
            'patient.service:id,name',
        ])->orderBy('created_at', 'desc');

        if (!empty($search)) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%")
                    ->orWhere('patientno', 'like', "%$search%");
            });
        }

        if (!empty($filters['stage'])) {
            $query->where('stage', $filters['stage']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $paginate ? $query->paginate($perPage) : $query->get();
    }

    public function getVisitRecordsForPatient(
        int $patientId,
        ?string $search = null,
        ?string $filter = null,
        bool $paginate = false,
        int $perPage = 20,
        ?string $export = null
    ) {
        $query = PatientVisit::with(['patient.service'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc');

        if (!empty($search)) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%")
                    ->orWhere('patientno', 'like', "%$search%");
            });
        }

        if (!empty($filter)) {
            $query->where('stage', $filter);
        }

        if ($paginate) {
            $paginated = $query->paginate($perPage);

            // $paginated->getCollection()->transform(function ($visit) {
            //     $serviceId = $visit->patient->service_id ?? null;

            //     $billingLog = $visit->billingLogs()
            //         ->where('service_type_id', $serviceId)
            //         ->first();

            //     return [
            //         'id'             => $visit->id,
            //         'patient_id'     => $visit->patient->id,
            //         'fullname'       => "{$visit->patient->firstname} {$visit->patient->lastname}",
            //         'date'           => $visit->created_at->format('Y-m-d'),
            //         'visit_number'   => $visit->visitno,
            //         'service_type'   => $visit->patient->service->name ?? 'Nil',
            //         'referral'       => 'Nil',
            //         'payment_status' => $billingLog->payment_status ?? 'pending',
            //         'payment_method' => $billingLog->payment_method ?? 'pending',
            //     ];
            // });

           $data = PatientVisitResources::collection($paginated)->toArray(request());


           $fetch = [
            "data"=>collect($data),
             "link"=>[
                   'first' => $paginated->url(1),
                    'last'  => $paginated->url($paginated->lastPage()),
                    'prev'  => $paginated->previousPageUrl(),
                    'next'  => $paginated->nextPageUrl(),
             ],
             "pages"=>[
                        'current_page' => $paginated->currentPage(),
                        'from'         => $paginated->firstItem(),
                        'last_page'    => $paginated->lastPage(),
                        'path'         => $paginated->path(),
                        'per_page'     => $paginated->perPage(),
                        'to'           => $paginated->lastItem(),
                        'total'        => $paginated->total(),
             ]
             ];

            return collect($fetch);
        }

        $records = $query->get();

        $formatted = $records->map(function ($visit) {
            $serviceId = $visit->patient->service_id ?? null;

            $billingLog = $visit->billingLogs()
                ->where('service_type_id', $serviceId)
                ->first();

            return [
                'id'             => $visit->id,
                'patient_id'     => $visit->patient->id,
                'fullname'       => "{$visit->patient->firstname} {$visit->patient->lastname}",
                'date'           => $visit->created_at->format('Y-m-d'),
                'visit_number'   => $visit->visitno,
                'service_type'   => $visit->patient->service->name ?? 'Nil',
                'referral'       => 'Nil',
                'payment_status' => $billingLog->payment_status ?? 'pending',
                'payment_method' => $billingLog->payment_method ?? 'pending',
            ];
        });

        return $formatted->values()->toArray();
    }

    // public function getVisitDetailWithBilling(int $patientId, int $visitId)
    // {
    //     return PatientVisit::with([
    //         'patient:id,firstname,lastname,patientno,service_id',
    //         'patient.service:id,name',
    //         'billingLogs'
    //     ])
    //         ->where('patient_id', $patientId)
    //         ->where('id', $visitId)
    //         ->first();
    // }
    public function getVisitDetailWithBilling(int $patientId, int $visitId)
    {
        $visit = PatientVisit::with([
            'patient:id,firstname,lastname,patientno,service_id',
            'patient.service:id,name',
            'billingLogsForPatient',
        ])
            ->where('patient_id', $patientId)
            ->where('id', $visitId)
            ->first();

        return $visit;
    }
}
