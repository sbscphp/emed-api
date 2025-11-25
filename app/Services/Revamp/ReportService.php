<?php

namespace App\Services\Revamp;

use App\Helpers\ExportHelper;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
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
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $dateRange = [$request->start_date, $request->end_date];
        } else {
            $dateRange = GeneralHelper::dateFilter($request->period);
        }

        // Base query
        $records = Service::query()
            ->where('tenant_id', $tenantId)
            ->when($dateRange, function ($q) use ($dateRange) {
                $q->whereBetween('created_at', $dateRange);
            });

        // === PATIENT REPORT ===
        if ($request->type === 'Patient') {
            $records->withCount(['visits as total_patient_attended']);
        }

        // === FINANCIAL REPORT ===
        if ($request->type === 'Financial') {
            $records
                ->withSum('billing', 'amount_paid')
                ->withSum([
                    'billing as billing_sum_amount_pending' => function ($q) {}
                ], DB::raw('grand_total - amount_paid'));
        }

        // Fetch paginated or all results
        $results = (!empty($request->paginate) && empty($request->export))
            ? $records->orderBy('id', 'DESC')->paginate($request->limit ?? 15)
            : $records->orderBy('id', 'DESC')->get();

        // Post-process for financial
        if ($request->type === 'Patient') {
            $totalPatient = $results->sum('total_patient_attended');
            $total_amount_pending = $results->sum('pending_payment');
            return [
                'total_patient' => $totalPatient,
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
        // Normalize input: accept either
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

        if ($recordsCollection->isEmpty()) {
            throw new \Exception("No records found for export.");
        }

        $exportData = [];
        $headers = [];

        if ($type == 'Patient') {
            $headers = ['Department', 'Total Patient Attended'];
            $exportData = $recordsCollection->map(function ($record) {
                return [
                    'Department'              => $record->name,
                    'Total Patient Attended'  => $record->total_patient_attended ?? 0,
                ];
                return [
                    'Department'      => data_get($record, 'name', ''),
                    'Total Patient Attended'   => data_get($record, 'total_patient_attended', 0) ?? 0,
                ];
            })->toArray();
        } elseif ($type == 'Financial') {
            $headers = ['Department', 'Total Revenue', 'Pending Payment'];

            // 1. Map the Service records first
            $exportData = $recordsCollection->map(function ($record) {
                return [
                    'Department'      => data_get($record, 'name', ''),
                    'Total Revenue'   => data_get($record, 'total_revenue', 0) ?? 0,
                    'Pending Payment' => data_get($record, 'pending_payment', 0) ?? 0,
                ];
            })->toArray();
        } else {
            throw new \Exception("Invalid report type.");
        }

        if (strtolower($format) == 'csv') {
            $fileName = strtolower($type) . '_report.csv';
            return ExportHelper::streamCsv($exportData, $headers, $fileName);
        }

        if (strtolower($format) == 'pdf') {
            $fileName = strtolower($type) . '_report.pdf';
            $pdf = Pdf::loadView('exports.patients', [
                'patients' => $exportData,
                'headers' => $headers,
                'type' => $type
            ])->setPaper('A1', 'landscape');

            return $pdf->download($fileName);
        }

        throw new \Exception("Invalid export format.");
    }
}
