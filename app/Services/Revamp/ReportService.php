<?php

namespace App\Services\Revamp;

use App\Helpers\ExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Repositories\Laboratory\LaboratoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Class ReportService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class ReportService
{
    /**
     * Laboratory constructor.
     *
     */
    public function __construct(LaboratoryInterface $LaboratoryInterface) {}

    /**
     * Retrieve all Laboratory.
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

        $records = BillingLog::query()
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('invoice_number', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('payment_status', $request['status']);
            })
            ->when(!empty($request['service_type_id']), function ($query) use ($request) {
                $query->where('service_type_id', $request['service_type_id']);
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
            ->with('patient', 'service:id,name');

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

        $query = BillingLog::query()
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($q) use ($dateFilter) {
                $q->whereBetween('created_at', $dateFilter);
            });

        $totalPatient = (clone $query)->distinct('patient_id')->count('patient_id');
        $totalRevenue = (clone $query)->sum('grand_total');
        $totalPaid = (clone $query)->sum('amount_paid');
        $totalOutstanding = (clone $query)->sum('amount_outstanding');

        return [
            'totalPatient' => $totalPatient,
            'totalRevenue' => $totalRevenue,
            'totalPaid' => $totalPaid,
            'totalOutstanding' => $totalOutstanding,
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($billing) {
            return [
                'Patient Card'      => $billing->patient->cardno,
                'First Name'      => $billing->patient->firstname,
                'Last Name'       => $billing->patient->lastname,
                'Invoice No'       => $billing->invoice_number,
                'Total Amount'       => $billing->grand_total,
                'Payment Method'       => $billing->payment_method,
                'Date'      => $billing->created_at->format('Y-m-d H:i'),
                'Assigned Unit'       => $billing->service->name ?? 'N/A'
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'reports.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('reports.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function fetchAllReportOverview($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        // Handle date range: either custom OR system dateFilter, not both
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        // Base query
        $records = Service::query()
            ->where('tenant_id', $tenantId);

        // === PATIENT REPORT ===
        if ($request->type === 'Patient') {

            $records->withCount([
                'visits as total_patient_visits' => function ($q) use ($dateFilter, $tenantId) {
                    $q->where('tenant_id', $tenantId);

                    if ($dateFilter) {
                        $q->whereBetween('created_at', $dateFilter);
                    }
                },
            ]);

            $records->addSelect([
                'total_patient_attended' => PatientVisit::query()
                    ->selectRaw('COUNT(DISTINCT patient_id)')
                    ->where('tenant_id', $tenantId)
                    ->whereColumn('patient_visits.service_id', 'services.id')
                    ->when($dateFilter, function ($q) use ($dateFilter) {
                        $q->whereBetween('created_at', $dateFilter);
                    })
            ]);
        }

        // === FINANCIAL REPORT ===
        if ($request->type === 'Financial') {

            $records
                ->withSum([
                    'billing as billing_sum_amount_paid' => function ($q) use ($dateFilter) {
                        if ($dateFilter) {
                            $q->whereBetween('created_at', $dateFilter);
                        }
                    }
                ], 'amount_paid')
                ->withSum([
                    'billing as billing_sum_amount_pending' => function ($q) use ($dateFilter) {
                        if ($dateFilter) {
                            $q->whereBetween('created_at', $dateFilter);
                        }
                    }
                ], DB::raw('grand_total - amount_paid'));
        }

        // Fetch paginated or all results
        $results = (!empty($request->paginate) && empty($request->export))
            ? $records->orderBy('id', 'DESC')->paginate($request->limit ?? 15)
            : $records->orderBy('id', 'DESC')->get();

        // Post-process for financial
        if ($request->type === 'Patient') {
            $totalPatientVisits = PatientVisit::query()
                ->where('tenant_id', $tenantId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->whereBetween('created_at', $dateFilter);
                })
                ->count();

            $totalPatientAttended = PatientVisit::query()
                ->where('tenant_id', $tenantId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->whereBetween('created_at', $dateFilter);
                })
                ->distinct('patient_id')
                ->count('patient_id');
            $total_amount_pending = $results->sum('pending_payment');
            return [
                'total_patient_visits' => $totalPatientVisits,
                'total_patient_attended' => $totalPatientAttended,
                'total_amount_pending' => $total_amount_pending,
                'services' => $results, // The paginated Service records
            ];
        }
        if ($request->type === 'Financial') {
            $results->transform(function ($record) {
                $record->total_revenue = $record->billing_sum_amount_paid ?? 0;
                $record->pending_payment = $record->billing_sum_amount_pending ?? 0;

                unset(
                    $record->billing_sum_amount_paid,
                    $record->billing_sum_amount_pending,
                    $record->billing_sum_grand_total
                );

                return $record;
            });

            $total_amount_paid = $results->sum('total_revenue');
            $total_amount_pending = $results->sum('pending_payment');
            return [
                'total_amount_paid' => $total_amount_paid,
                'total_amount_pending' => $total_amount_pending,
                'services' => $results, // The paginated Service records
            ];
        }

        return $results;
    }

    public function fetchAllReportExport($records, $format, $type = null)
    {
        $overallTotals = is_array($records) ? $records : [];

        // Normalize input
        if (is_array($records) && isset($records['services'])) {
            $recordsCollection = $records['services'];
        } else {
            $recordsCollection = is_array($records) ? collect($records) : $records;
        }

        // If it's a paginator instance, extract the underlying collection
        if ($recordsCollection instanceof LengthAwarePaginator || $recordsCollection instanceof Paginator) {
            $recordsCollection = $recordsCollection->getCollection();
        }

        // Ensure we have a Collection
        if (!($recordsCollection instanceof Collection)) {
            $recordsCollection = collect($recordsCollection);
        }

        $exportData = [];
        $headers = [];
        $footerTotals = [];

        /* ----------------------------------------------------------------------
       PATIENT REPORT
    ---------------------------------------------------------------------- */
        if ($type == 'Patient') {
            $headers = ['Department', 'Total Patient Visits'];

            // Department rows
            $exportData = $recordsCollection->map(function ($record) {
                return [
                    'Department'             => data_get($record, 'name', ''),
                    'Total Patient Visits' => data_get($record, 'total_patient_visits', 0),
                    // 'Total Patient Attended' => data_get($record, 'total_patient_attended', 0),
                ];
            })->toArray();

            // Footer totals (NOT part of exportData)
            $footerTotals = [
                'Department'             => 'TOTAL',
                'Total Patient Visits' => data_get($overallTotals, 'total_patient_visits', $recordsCollection->sum('total_patient_visits')),
                // 'Total Patient Attended' => data_get($overallTotals, 'total_patient_attended', $recordsCollection->sum('total_patient_attended')),
            ];
        }
        /* ----------------------------------------------------------------------
       FINANCIAL REPORT
    ---------------------------------------------------------------------- */ elseif ($type == 'Financial') {
            $headers = ['Department', 'Total Revenue', 'Pending Payment'];

            // Department rows
            $exportData = $recordsCollection->map(function ($record) {
                return [
                    'Department'      => data_get($record, 'name', ''),
                    'Total Revenue'   => data_get($record, 'total_revenue', 0),
                    'Pending Payment' => data_get($record, 'pending_payment', 0),
                ];
            })->toArray();

            // Summary footer (NOT part of exportData)
            $footerTotals = [
                'Department'      => 'TOTAL',
                'Total Revenue'   => data_get($overallTotals, 'total_amount_paid', $recordsCollection->sum('total_revenue')),
                'Pending Payment' => data_get($overallTotals, 'total_amount_pending', $recordsCollection->sum('pending_payment')),
            ];
        } else {
            throw new \Exception("Invalid report type.");
        }

        // If after mapping there are no rows to export, throw
        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        /* ----------------------------------------------------------------------
       EXPORT FORMATS
    ---------------------------------------------------------------------- */

        // CSV Export
        if (strtolower($format) == 'csv') {
            $fileName = strtolower($type) . '_report.csv';

            // Add footer to CSV manually at end
            $exportData[] = $footerTotals;

            return ExportHelper::streamCsv($exportData, $headers, $fileName);
        }

        // PDF Export
        if (strtolower($format) == 'pdf') {
            $fileName = strtolower($type) . '_report.pdf';

            // Pass as 'patients' so your existing blade works unchanged
            $pdf = Pdf::loadView('exports.patients', [
                'patients'     => $exportData,
                'headers'      => $headers,
                'type'         => $type,
                'footerTotals' => $footerTotals,
            ])->setPaper('A1', 'landscape');

            return $pdf->download($fileName);
        }

        throw new \Exception("Invalid export format.");
    }
}
