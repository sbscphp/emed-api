<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\PatientVisit;
use App\Repositories\PatientVisit\PatientVisitInterface;
use App\Models\BillingLog;
use App\Models\Patient;
use App\Models\Triage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Class PatientVisitService
 *
 * This class provides services related to PatientVisit operations and acts as a
 * layer between the Controller and the PatientVisitRepository.
 */
class PatientVisitService
{
    /**
     * PatientVisit constructor.
     *
     * @param PatientVisitInterface $PatientVisitInterface
     */
    public function __construct(PatientVisitInterface $PatientVisitInterface) {}

    /**
     * Retrieve all PatientVisit.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = PatientVisit::query()
            ->where('service_id', $request->service_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['patient_status']), function ($query) use ($request) {
                $query->where('status', $request['patient_status']);
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_status', $request['payment_status']);
            })
            ->when(!empty($request['payment_method']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_method', $request['payment_method']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('arrival_date', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('arrival_date', 'DESC');
            })
            ->with('patient', 'service', 'patientBilling');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = PatientVisit::query()->where('service_id', $request->service_id);

        $awaitingTriage = (clone $query)->where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)->count();
        $awaitingConsultation = (clone $query)->where('status', PatientVisitStatusEnums::TRIAGE->value)->count();
        $admitted = (clone $query)->where('status', PatientVisitStatusEnums::ADMITTED->value)->count();
        $discharged = (clone $query)->where('status', PatientVisitStatusEnums::DISCHARGED->value)->count();

        $completedSugery = 0;
        $cancelled = 0;

        $triagePatient = (clone $query)->where('status', PatientVisitStatusEnums::TRIAGE->value)->count();
        $emergencyPrescription = 0;
        $medicationDispensedToday = 0;
        $routineMedication = 0;
        $criticalStockAlert = 0;

        $totalTest = 0;
        $pendingTest = 0;
        $completedTest = 0;
        $failedTest = 0;

        $totalDeliveriesToday = 0;
        $cSection = 0;
        $normalBirth = 0;
        $antenatalCheckup = 0;

        $patientLog = (clone $query)->count();

        return [
            'awaitingTriage' => $awaitingTriage,
            'awaitingConsultation' => $awaitingConsultation,
            'admitted' => $admitted,
            'discharged' => $discharged,

            'completedSugery' => $completedSugery,
            'cancelled' => $cancelled,

            'triagePatient' => $triagePatient,
            'emergencyPrescription' => $emergencyPrescription,
            'medicationDispensedToday' => $medicationDispensedToday,
            'routineMedication' => $routineMedication,
            'criticalStockAlert' => $criticalStockAlert,

            'totalTest' => $totalTest,
            'pendingTest' => $pendingTest,
            'completedTest' => $completedTest,
            'failedTest' => $failedTest,

            'totalDeliveriesToday' => $totalDeliveriesToday,
            'cSection' => $cSection,
            'normalBirth' => $normalBirth,
            'antenatalCheckup' => $antenatalCheckup,

            'patientLog' => $patientLog,
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Firstname'      => $visit->patient->firstname ?? '',
                'Lastname'       => $visit->patient->lastname ?? '',
                'Card No'        => $visit->patient->cardno ?? '',
                'Patient No'     => $visit->patient->patientno ?? '',
                'Arrival Date'   => $visit->arrival_date ?? '',
                'Patient Status' => $visit->status ?? '',
                'Payment Method'  => $visit->patientBilling->payment_method ?? 'N/A',
                'Payment Status'    => $visit->patientBilling->payment_status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Create a new PatientVisit using the data provided.
     *
     * @param array $data
     * @return \App\Models\PatientVisit
     */
    public function create($request)
    {
        try {

            $currentUser = Auth::user();
            $patient = Patient::find($request->patient_id);
            $visit = PatientVisit::find($request->visit_id);
            // Initiate Patient Triage
            $triage = Triage::create([
                'user_id' => $currentUser->id,
                'patient_id' => $request->patient_id,
                'visit_id' => $request->visit_id,
                'blood_pressure' => $request->blood_pressure,
                'pulse_bpm' => $request->pulse_bpm,
                'sugar_level' => $request->sugar_level,
                'weight_kg' => $request->weight_kg,
                'temperature' => $request->temperature,
                'severity' => $request->severity,
            ]);

            $invoiceNumber = GeneralHelper::getModelUniqueOrderlyId([
                'modelNamespace' => BillingLog::class,
                'modelField' => 'invoice_number',
                'prefix' => 'INV-',
                'idLength' => 6,
            ]);

            $visit->update([
                'status' => PatientVisitStatusEnums::TRIAGE->value,
            ]);

            // update patient registaration staus
            $patient->update([
                'reg_status' => GeneralEnums::EXISTING->value,
            ]);

            return $triage;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function investigationOrdersOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = Triage::query()
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['patient_status']), function ($query) use ($request) {
                $query->whereRelation('visit', 'status', $request['patient_status']);
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->whereRelation('visit.patientBilling', 'payment_status', $request['payment_status']);
            })
            ->when(!empty($request['payment_method']), function ($query) use ($request) {
                $query->whereRelation('visit.patientBilling', 'payment_method', $request['payment_method']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with('patient', 'visit');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function investigationOrdersStats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = Triage::query();

        $totalPatients = Patient::count();
        $pendingPatients = PatientVisit::where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)->count();
        $totalOrders = (clone $query)->count();
        $patientLog = (clone $query)->count();

        return [
            'totalPatients' => $totalPatients,
            'pendingPatients' => $pendingPatients,
            'totalOrders' => $totalOrders,
            'patientLog' => $patientLog,
        ];
    }

    public function investigationOrdersExport($records, $format)
    {
        $exportData = $records->map(function ($triage) {
            return [
                'Patient Name'     => $triage->patient->firstname . ' ' . $triage->patient->lastname,
                'Card No'          => $triage->patient->cardno,
                'Patient No'       => $triage->patient->patientno,
                'Time Of Arrival'  => $triage->visit->arrival_date ?? 'N/A',
                'Acuity'           => $triage->severity,
                'Payment Status'   => $triage->visit->patientBilling->payment_method ?? 'N/A',
                'Patient Status'   => $triage->visit->status ?? 'N/A'
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'investigationorders.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('investigationorders.pdf');
        }

        throw new \Exception("Invalid export format.");
    }
}
