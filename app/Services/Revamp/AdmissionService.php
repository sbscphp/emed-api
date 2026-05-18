<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\AdmittedPatient;
use App\Models\Bed;
use App\Models\Ward;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\CareNote;
use App\Models\DrugChart;
use App\Models\ServiceUnit;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class AdmissionService
{
    public function overview($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $query = AdmittedPatient::query()->where('tenant_id', $tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(isset($request['status']), function ($query) use ($request) {
                $query->where('status', filter_var($request['status']));
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('date_admitted', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('date_admitted', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('date_admitted', 'DESC');
            });

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $admissionQuery = AdmittedPatient::query()->where('tenant_id', $tenantId);

        return [
            'total_number_of_admitted_patients' => (clone $admissionQuery)->where('status', GeneralEnums::ADMITTED->value)->count(),
            'number_of_patients_admission' => (clone $admissionQuery)->count(),
            'discharged_patients' => (clone $admissionQuery)->where('status', GeneralEnums::DISCHARGED->value)->count(),
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($admission) {
            return [
                'Firstname'      => $admission->patient->firstname ?? 'N/A',
                'Lastname'       => $admission->patient->lastname ?? 'N/A',
                'Card No'        => $admission->patient->cardno ?? 'N/A',
                'Patient No'     => $admission->patient->patientno ?? 'N/A',
                'Wards And Bed' => $admission->ward->name . ' - ' . $admission->bed ?? 'N/A',
                'Date Registered'     => $admission->date_admitted ? $admission->date_admitted->format('Y-m-d') : 'N/A',
                'Status'   => $admission->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_admissions.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_admissions.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function getAdmissionDetails($request, $id)
    {
        $tenantId = $request->header('X-Tenant-ID');
        return AdmittedPatient::with(['patient', 'visit', 'ward'])->where('tenant_id', $tenantId)->find($id);
    }

    public function getWards($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        return Ward::query()->where('tenant_id', $tenantId)->with('bed')->get();
    }

    public function admitPatient($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        return DB::connection('tenant')->transaction(function () use ($request, $tenantId, $currentUser) {
            $admission = AdmittedPatient::where('tenant_id', $tenantId)->find($request->admission_id);

            if (!$admission) {
                throw new \Exception("Admission record not found.");
            }

            $ward = Ward::where('tenant_id', $tenantId)->with('bed')->find($request->ward_id);

            if (!$ward) {
                throw new \Exception("Ward not found.");
            }

            $bed = $ward->bed;

            if (!$bed) {
                throw new \Exception("No bed space configured for this ward.");
            }

            // Check if available_bed_number is greater than 0
            if ($bed->available_bed_number <= 0) {
                throw new \Exception("No available beds in this ward.");
            }

            $cost = (float) $ward->cost;

            // If ward cost is greater than 0, create billing
            if ($cost > 0) {
                $patient = $admission->patient;
                if (!$patient) {
                    throw new \Exception("Patient record not found for this admission.");
                }

                $invoiceNumber = GeneralHelper::getModelUniqueOrderlyId([
                    'modelNamespace' => BillingLog::class,
                    'modelField' => 'invoice_number',
                    'prefix' => 'INV-',
                    'idLength' => 6,
                ]);

                // Query the first available ServiceUnit
                $serviceUnit = ServiceUnit::where('tenant_id', $tenantId)->where('name', 'Registration')->first();
                $serviceUnitId = $serviceUnit ? $serviceUnit->id : null;

                // store billing info
                $patientBilling = BillingLog::create([
                    'tenant_id' => $tenantId,
                    'updated_by' => $currentUser ? $currentUser->id : null,
                    'created_by' => $currentUser ? $currentUser->id : null,
                    'visit_id' => $admission->visit_id,
                    'patient_id' => $admission->patient_id,
                    'invoice_number' => $invoiceNumber,
                    'patient_name' => $patient->firstname . ' ' . $patient->lastname,
                    'billing_date' => now(),
                    'service_unit_id' => $serviceUnitId,
                    'grand_total' => $cost,
                    'total_amount' => $cost,
                    'amount_outstanding' => $cost,
                    'payment_status' => 'Pending',
                ]);

                // update billing log details
                BillingLogDetail::create([
                    'tenant_id' => $tenantId,
                    'billing_id' => $patientBilling->id,
                    'service_unit_id' => $serviceUnitId,
                    'item_name' => 'Ward Admission Fee - ' . $ward->name,
                    'quantity' => 1,
                    'amount' => $cost,
                    'status' => 'Pending',
                ]);
            }

            // Bed assignment name: "Bed X"
            // For example: if original bed_number is 5, and available_bed_number is 5, the first is "Bed 1".
            // Calculation: ($bed->bed_number - $bed->available_bed_number + 1)
            $assignedBedName = 'Bed ' . ($bed->bed_number - $bed->available_bed_number + 1);

            // Reduce available_bed_number by 1
            $bed->available_bed_number = max(0, $bed->available_bed_number - 1);

            // If available_bed_number reaches 0, mark as occupied
            if ($bed->available_bed_number == 0) {
                $bed->occupied = true;
            }

            $bed->save();

            // Proceed to admit the patient by updating the admitted_patients record
            $admission->ward_id = $request->ward_id;
            $admission->admitted_by = $currentUser ? $currentUser->id : null;
            $admission->date_admitted = $request->date_admitted ? Carbon::parse($request->date_admitted)->format('Y-m-d') : now()->toDateString();
            $admission->bed = $assignedBedName;
            $admission->status = GeneralEnums::ADMITTED->value;
            $admission->save();

            return $admission;
        });
    }

    public function dischargePatient($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        return DB::connection('tenant')->transaction(function () use ($request, $tenantId, $currentUser) {
            $admission = AdmittedPatient::where('tenant_id', $tenantId)->find($request->admission_id);

            if (!$admission) {
                throw new \Exception("Admission record not found.");
            }

            // Check if patient has any pending payments
            $pendingBills = BillingLog::where('patient_id', $admission->patient_id)
                ->where('visit_id', $admission->visit_id)
                ->whereIn('payment_status', ['Pending', 'Part Paid'])
                ->exists();

            if ($pendingBills) {
                throw new \Exception("Patient has pending payments. All bills must be paid before discharge.");
            }

            // Find the Bed associated with the ward and release it
            $ward = Ward::where('tenant_id', $tenantId)->with('bed')->find($admission->ward_id);
            if ($ward && $ward->bed) {
                $bed = $ward->bed;
                $bed->available_bed_number = min($bed->bed_number, $bed->available_bed_number + 1);
                if ($bed->occupied) {
                    $bed->occupied = false;
                }
                $bed->save();
            }

            // Update admission status to discharged
            $admission->status = GeneralEnums::DISCHARGED->value;
            $admission->discharged_by = $currentUser ? $currentUser->id : null;
            $admission->date_discharged = $request->date_discharged ? Carbon::parse($request->date_discharged)->format('Y-m-d') : now()->toDateString();
            $admission->save();

            return $admission;
        });
    }

    public function getPatientCareNotes($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $patientId = $request->patient_id;
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = CareNote::query()->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('visit_id', $request->visit_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })->when(isset($request['type']), function ($query) use ($request) {
                $query->where('type', filter_var($request['type']));
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
            });

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function exportPatientCareNotes($request)
    {
        $exportData = $request->map(function ($note) {
            return [
                'Firstname'      => $note->patient->firstname ?? 'N/A',
                'Lastname'       => $note->patient->lastname ?? 'N/A',
                'Card No'        => $note->patient->cardno ?? 'N/A',
                'Patient No'     => $note->patient->patientno ?? 'N/A',
                'Note Type'      => $note->type ?? 'N/A',
                // 'Notes'          => $note->notes ?? 'N/A',
                'Written By'     => $note->writer ? $note->writer->firstname . ' ' . $note->writer->lastname : 'N/A',
                'Date Written'   => $note->created_at ? Carbon::parse($note->created_at)->format('Y-m-d H:i:s') : 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($request['format']) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_care_notes.csv');
        }

        if (strtolower($request['format']) === 'pdf') {
            $pdf = Pdf::loadView('exports.care_notes', ['careNotes' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_care_notes.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function addPatientCareNote($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        $careNote = CareNote::create([
            'tenant_id' => $tenantId,
            'patient_id' => $request->patient_id,
            'visit_id' => $request->visit_id,
            'written_by' => $currentUser ? $currentUser->id : null,
            'notes' => $request->notes,
            'type' => $request->type,
        ]);

        return $careNote;
    }

    public function viewPatientCareNotes($id)
    {
        $careNote = CareNote::find($id);
        if (empty($careNote)) {
            throw new \Exception("Care note not found.");
        }
        return $careNote->load(['patient', 'visit', 'writer']);
    }

    public function viewPatientVisitDrugs($id)
    {
        $prescribedDrugs = Treatment::where('visit_id', $id)->where('status', GeneralEnums::FULLFILLED)->with('medication')->get();
        if (count($prescribedDrugs) == 0) {
            throw new \Exception("No dispensed drugs found for this visit.");
        }
        return $prescribedDrugs;
    }

    public function getPatientDrugCharts($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $patientId = $request->patient_id;
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $query = DrugChart::query()->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('visit_id', $request->visit_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })->when(isset($request['status']), function ($query) use ($request) {
                $query->where('status', filter_var($request['status']));
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
            });

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function exportPatientDrugCharts($request)
    {
        $exportData = $request->map(function ($chart) {
            return [
                'Firstname'      => $chart->patient->firstname ?? 'N/A',
                'Lastname'       => $chart->patient->lastname ?? 'N/A',
                'Card No'        => $chart->patient->cardno ?? 'N/A',
                'Patient No'     => $chart->patient->patientno ?? 'N/A',
                'Drug Name'      => $chart->drug_name ?? 'N/A',
                'Dosage'         => $chart->dosage ?? 'N/A',
                'Route'          => $chart->route ?? 'N/A',
                'Frequency'      => $chart->frequency ?? 'N/A',
                'Duration'       => $chart->duration ?? 'N/A',
                'Start Date'     => $chart->start_date ? Carbon::parse($chart->start_date)->format('Y-m-d') : 'N/A',
                'Administration' => $chart->administration ?? 'N/A',
                'Notes'          => $chart->notes ?? 'N/A',
                'Status'         => $chart->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($request['format']) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_drug_charts.csv');
        }

        if (strtolower($request['format']) === 'pdf') {
            $pdf = Pdf::loadView('exports.drug_charts', ['drugCharts' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_drug_charts.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function addPatientDrugChart($request)
    {
        $tenantId = $request->header('X-Tenant-ID');
        $currentUser = Auth::user();

        $drugChart = DrugChart::create([
            'tenant_id' => $tenantId,
            'patient_id' => $request->patient_id,
            'visit_id' => $request->visit_id,
            'drug_id' => $request->drug_id,
            'administered_by' => $currentUser ? $currentUser->id : null,
            'drug_name' => $request->drug_name,
            'dosage' => $request->dosage,
            'route' => $request->route,
            'frequency' => $request->frequency,
            'duration' => $request->duration,
            'start_date' => $request->start_date ? Carbon::parse($request->start_date)->format('Y-m-d') : null,
            'administration' => $request->administration,
            'notes' => $request->notes,
            'status' => GeneralEnums::ADMINISTERED->value,
        ]);

        return $drugChart;
    }

    public function viewPatientDrugCharts($id)
    {
        $drugChart = DrugChart::find($id);
        if (empty($drugChart)) {
            throw new \Exception("Drug chart record not found.");
        }
        return $drugChart->load(['patient', 'visit', 'drug', 'administeredBy']);
    }
}
