<?php

namespace App\Services\Revamp;

use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\Service;
use App\Repositories\Laboratory\LaboratoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $customDate = [];

        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);

        $records = Service::query()
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($q) use ($dateFilter) {
                $q->whereBetween('created_at', $dateFilter);
            });

        if ($request->type === 'Patient') {
            $records->withCount(['visits as total_patient_attended']);
        } elseif ($request->type === 'Financial') {
            $records->withSum('billing', 'grand_total')
                ->withSum('billing', 'amount_outstanding');
        }

        // Apply pagination or fetch all
        $results = !empty($request->paginate) && empty($request->export)
            ? $records->orderBy('id', 'DESC')->paginate($request->limit ?? 15)
            : $records->orderBy('id', 'DESC')->get();

        if ($request->type === 'Financial') {
            $results->transform(function ($record) {
                $record->total_revenue = $record->billing_sum_grand_total ?? 0;
                $record->pending_payment = $record->billing_sum_amount_outstanding ?? 0;

                unset($record->billing_sum_grand_total, $record->billing_sum_amount_outstanding);

                return $record;
            });
        }

        return $results;
    }

    public function fetchAllReportExport($records, $format, $type = null)
    {
        if ($records->isEmpty()) {
            throw new \Exception("No records found for export.");
        }

        if ($type == 'Patient') {
            $exportData = $records->map(function ($record) {
                return [
                    'Department'              => $record->name,
                    'Total Patient Attended'  => $record->total_patient_attended ?? 0,
                ];
            })->toArray();
        } elseif ($type == 'Financial') {
            $exportData = $records->map(function ($record) {
                return [
                    'Department'       => $record->name,
                    'Total Revenue'    => $record->total_revenue ?? 0,
                    'Pending Payment'  => $record->pending_payment ?? 0,
                ];
            })->toArray();
        } else {
            throw new \Exception("Invalid report type.");
        }

        if (strtolower($format) == 'csv') {
            $fileName = strtolower($type) . '_report.csv';
            return ExportHelper::streamCsv($exportData, null, $fileName);
        }

        if (strtolower($format) == 'pdf') {
            $fileName = strtolower($type) . '_report.pdf';
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download($fileName);
        }

        throw new \Exception("Invalid export format.");
    }
}
