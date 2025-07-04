<?php

namespace App\Services\Triage;

use App\Helpers\ExportHelper;
use App\Models\BillingLog;
use App\Models\PatientVisit;
use App\Models\Triage;
use App\Repositories\Triage\TriageInterface;
use App\Responser\JsonResponser;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
/**
 * Class TriageService
 * 
 * This class provides services related to Triage operations and acts as a 
 * layer between the Controller and the TriageRepository.
 */
class TriageService
{
    protected TriageInterface $TriageInterface;
    /**
     * Triage constructor.
     * 
     * @param TriageInterface $TriageInterface
     */
    public function __construct(TriageInterface $TriageInterface)
    {
        $this->TriageInterface = $TriageInterface;
    }

    /**
     * Retrieve all Triage.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->TriageInterface->all();
    }

    /**
     * Create a new Triage using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Triage
     */
    public function create(array $data)
    {
        return $this->TriageInterface->create($data);
    }

    public function updateOrCreate(array $conditions, array $data)
    {
        return $this->TriageInterface->updateOrCreate($conditions, $data);
    }

    /**
     * Update an existing Triage with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Triage
     */
    public function update(array $data, $id)
    {
        return $this->TriageInterface->update($data, $id);
    }


    /**
     * Delete a Triage by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->TriageInterface->delete($id);
    }


    /**
     * Find a Triage by their ID.
     * 
     * @param int $id
     * @return \App\Models\Triage
     */
    public function find($id)
    {
        return $this->TriageInterface->find($id);
    }


    /**
     * Find an existing Triage  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Triage
     */
    public function findByAttribute($attr, $value)
    {
        return $this->TriageInterface->findByAttribute($attr, $value);
    }

    public function getTriageByPatient(int $patientId)
    {
        return $this->TriageInterface->getByPatientId($patientId);
    }

    public function getPatientsAndStatsByService($serviceId, $search = null, $from, $to)
    {
        DB::connection('tenant');

        $today = now()->toDateString();

        $query = PatientVisit::join('patients', 'patient_visits.patient_id', '=', 'patients.id')
            ->join('services', 'patients.service_id', '=', 'services.id')
            ->leftJoin('triages', 'patient_visits.patient_id', '=', 'triages.patient_id')
            ->where('services.id', $serviceId)
            ->select(
                'patient_visits.id as id',
                'patients.id as patient_id',
                'patients.firstname',
                'patients.lastname',
                'patients.cardno',
                'patients.patient_type',
                'patients.patientno',
                'patient_visits.arrival_date',
                'patient_visits.departure_date',
                'services.id as service_id',
                'services.name as service_name',
                'patient_visits.created_at as visit_date',
                DB::raw('COALESCE(triages.severity, 0) as acuity'),
                'patient_visits.stage as patient_status'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('patients.firstname', 'like', "%$search%")
                    ->orWhere('patients.lastname', 'like', "%$search%")
                    ->orWhere('patients.cardno', 'like', "%$search%")
                    ->orWhere('patients.patientno', 'like', "%$search%")
                    ->orWhere('patient_visits.stage', 'like', "%$search%")
                    ->orWhere('triages.severity', 'like', "%$search%");
            });
        }

        $query->when($from && $to, function ($q) use ($from, $to) {
            $q->whereBetween('patients.arrival_date', [Carbon::parse($from), Carbon::parse($to)]);
        });

        $patients = $query->orderBy('patient_visits.created_at', 'desc')->paginate(10);

        foreach ($patients as $patient) {
            $billingLog = BillingLog::where('patient_id', $patient->patient_id)
                ->where('service_type_id', $serviceId)
                ->latest()
                ->first();

            $patient->payment_status = $billingLog->payment_status ?? 'pending';
        }

        $stats = $this->generateServiceStats($serviceId, $today);

        return [
            'patients' => $patients,
            'stats' => $stats
        ];
    }

    public function exportTriagePatientsByService(string $serviceId, ?string $format = 'csv', ?string $search = null, ?string $startDate = null, ?string $endDate = null)
    {
        $query = Triage::with(['patient.visits'])
            ->whereHas('patient', function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId);
            });
        if ($search) {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%")
                    ->orWhere('cardno', 'like', "%$search%");
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('arrival_date', [$startDate, $endDate]);
        }

        $patients = $query->get();

        if ($patients->isEmpty()) {
            return JsonResponser::send(true, 'No triage records found for export.', null, 204);
        }

        $exportData = $patients->map(function ($p) {
            $visit = $p->patient->visits->first() ?? null;
            return [
                'Firstname'       => $p->firstname ?? $p->patient->firstname ?? '',
                'Lastname'        => $p->lastname ?? $p->patient->lastname ?? '',
                'Card No'         => $p->cardno ?? $p->patient->cardno ?? '',
                'Patient Type'    => $p->patient_type ?? $p->patient->patient_type ?? '',
                'Patient No'      => $p->patientno ?? $p->patient->patientno ?? '',
                'Arrival Date'    => $visit->arrival_date ?? '',
                'Departure Date'  => $visit->departure_date ?? '',
                'Acuity'          => $p->acuity,
                'Patient Status'  => $p->patient_status ?? $p->patient->status ?? '',
            ];
        })->toArray();

        $filename = 'triage_export_' . now()->format('Y-m-d_H-i-s');

        switch (strtolower($format)) {
            case 'pdf':
                return ExportHelper::downloadPdf($exportData, "{$filename}.pdf", 'exports.triage_patients');
            case 'csv':
            default:
                return ExportHelper::streamCsv($exportData, null, "{$filename}.csv");
        }
    }

    private function generateServiceStats($serviceId, $today)
    {
        if ($serviceId == 1) {
            return [
                'awaiting_triage' => $this->countByStage($serviceId, 'triage'),
                'awaiting_consultation' => $this->countByStage($serviceId, 'consultation'),
                'admitted_today' => $this->countByStage($serviceId, 'admitted', $today),
                'discharged' => $this->countByStage($serviceId, 'discharged'),
            ];
        } elseif ($serviceId == 2) {
            return [
                'awaiting_triage' => $this->countByStage($serviceId, 'triage'),
                'awaiting_consultation' => $this->countByStage($serviceId, 'consultation'),
                'completed_surgery' => $this->countByStage($serviceId, 'completed_surgery'),
                'cancelled_or_postponed' => $this->countByStage($serviceId, ['cancelled', 'postponed'], null, true),
            ];
        } elseif (in_array($serviceId, [3, 4, 5])) {
            return [
                'awaiting_triage' => $this->countByStage($serviceId, 'triage'),
                'triaged_patient' => $this->countByStage($serviceId, 'triaged'),
                'total_test' => $this->countTests($serviceId),
                'pending_test' => $this->countTests($serviceId, 'pending'),
                'emergency_prescription' => 0,
                'medication_dispenses_today' => 0,
                'routine_medication' => 0,
                'critical_stock_alert' => 0,
            ];
        }

        return [];
    }

    private function countByStage($serviceId, $stage, $date = null, $isMultiple = false)
    {
        $query = PatientVisit::whereHas('patient', function ($q) use ($serviceId) {
            $q->where('service_id', $serviceId);
        });

        if ($isMultiple) {
            $query->whereIn('stage', $stage);
        } else {
            $query->where('stage', $stage);
        }

        if ($date) {
            $query->whereDate('created_at', $date);
        }

        return $query->count();
    }

    private function countTests($serviceId, $status = null)
    {
        $query = DB::connection('tenant')->table('lab_test_results')
            ->join('patient_visit_lab', 'lab_test_results.patient_visit_lab_id', '=', 'patient_visit_lab.id')
            ->join('patients', 'patient_visit_lab.patient_id', '=', 'patients.id')
            ->where('patients.service_id', $serviceId);

        if ($status) {
            $query->where('lab_test_results.result', $status);
        }

        return $query->count();
    }

    public function getAllInvestigationOrders($search = null)
    {
        $query = PatientVisit::join('patients', 'patient_visits.patient_id', '=', 'patients.id')
            ->join('services', 'patients.service_id', '=', 'services.id')
            ->leftJoin('triages', 'patient_visits.patient_id', '=', 'triages.patient_id')
            ->select(
                'patient_visits.id as id',
                'patients.id as patient_id',
                'patients.firstname',
                'patients.lastname',
                'patients.cardno',
                'patients.patient_type',
                'patients.patientno',
                'patient_visits.arrival_date',
                'patient_visits.departure_date',
                'services.id as service_id',
                'services.name as service_name',
                'patient_visits.created_at as visit_date',
                DB::raw('COALESCE(triages.severity, 0) as acuity'),
                'patient_visits.stage as patient_status'
            )
            ->orderByDesc('patient_visits.created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('patients.firstname', 'like', "%$search%")
                    ->orWhere('patients.lastname', 'like', "%$search%")
                    ->orWhere('patients.cardno', 'like', "%$search%");
            });
        }

        $patients = $query->paginate(10);

        $statsQuery = clone $query;

        $stats = [
            'total_patients' => $statsQuery->count(),
            'pending_patients' => (clone $statsQuery)->where('patient_visits.stage', 'triaged')->count(),
            'order_available' => (clone $statsQuery)->where('patient_visits.stage', '!=', 'triaged')->count(),
        ];

        return [
            'patients' => $patients,
            'stats' => $stats
        ];
    }
}
