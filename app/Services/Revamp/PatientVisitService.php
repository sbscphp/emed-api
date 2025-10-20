<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\PatientVisit;
use App\Repositories\PatientVisit\PatientVisitInterface;
use App\Models\BillingLog;
use App\Models\CounsellingDetail;
use App\Models\DeliveryDetail;
use App\Models\DosageAdministration;
use App\Models\Immunization;
use App\Models\Patient;
use App\Models\Surgery;
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
        $tenantId = $request->header('X-Tenant-ID');

        $records = PatientVisit::query()
            ->where('tenant_id', $tenantId)
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
            ->with('patient', 'service', 'patientBilling', 'triage:id,visit_id,severity');

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
        $tenantId = $request->header('X-Tenant-ID');
        $query = PatientVisit::query()->where('tenant_id', $tenantId)->where('service_id', $request->service_id);

        $awaitingTriage = (clone $query)->where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)->count();
        $awaitingConsultation = (clone $query)->where('status', PatientVisitStatusEnums::TRIAGE->value)->count();
        $admitted = (clone $query)->where('status', PatientVisitStatusEnums::ADMITTED->value)->count();
        $discharged = (clone $query)->where('status', PatientVisitStatusEnums::DISCHARGED->value)->count();

        $completedSugery = Surgery::where('tenant_id', $tenantId)->where('status', GeneralEnums::COMPLETED)->count();
        $cancelledSugery = Surgery::where('tenant_id', $tenantId)->where('status', GeneralEnums::CANCELLED)->count();

        $triagePatient = (clone $query)->where('status', PatientVisitStatusEnums::TRIAGE->value)->count();
        $totalImmunization = Immunization::where('tenant_id', $tenantId)->count();
        $vaccineAdministered = DosageAdministration::where('tenant_id', $tenantId)->count();

        $awaitingCounselling = (clone $query)->where('status', PatientVisitStatusEnums::TRIAGE->value)->count();
        $totalCounselled = CounsellingDetail::where('tenant_id', $tenantId)->count();

        $totalDeliveries = DeliveryDetail::where('tenant_id', $tenantId)->count();
        $totalCSectionDeliveries = DeliveryDetail::where('tenant_id', $tenantId)->where('delivery_mode', 'C-Section')->count();
        $totalNormalDeliveries = DeliveryDetail::where('tenant_id', $tenantId)->where('delivery_mode', 'Spontanteous Vaginal Delivery')->count();
        $patientLog = (clone $query)->count();

        return [
            'awaitingTriage' => $awaitingTriage,
            'awaitingConsultation' => $awaitingConsultation,
            'admitted' => $admitted,
            'discharged' => $discharged,

            'completedSugery' => $completedSugery,
            'cancelledSugery' => $cancelledSugery,

            'triagePatient' => $triagePatient,
            'totalImmunization' => $totalImmunization,
            'vaccineAdministered' => $vaccineAdministered,

            'awaitingCounselling' => $awaitingCounselling,
            'totalCounselled' => $totalCounselled,

            'totalDeliveries' => $totalDeliveries,
            'totalCSectionDeliveries' => $totalCSectionDeliveries,
            'totalNormalDeliveries' => $totalNormalDeliveries,

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
            $tenantId = $request->header('X-Tenant-ID');
            // Initiate Patient Triage
            $triage = Triage::create([
                'tenant_id' => $tenantId,
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
        $tenantId = $request->header('X-Tenant-ID');

        $records = Triage::query()->where('tenant_id', $tenantId)
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
            ->with('patient', 'visit.billingLogsForPatient');

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
        $tenantId = $request->header('X-Tenant-ID');
        $query = Triage::query()->where('tenant_id', $tenantId);

        $totalPatients = Patient::where('tenant_id', $tenantId)->count();
        $pendingPatients = PatientVisit::where('tenant_id', $tenantId)
            ->where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)->count();
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
