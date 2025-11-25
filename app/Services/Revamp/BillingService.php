<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\Laboratory;
use App\Models\LaboratoryResult;
use App\Models\PatientVisit;
use App\Models\ServiceUnit;
use App\Models\User;
use App\Repositories\Laboratory\LaboratoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Class BillingService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class BillingService
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
        $tenantId = $request->header('X-Tenant-ID');

        $records = BillingLog::query()
            ->where('tenant_id', $tenantId)
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
            ->with('patient', 'service', 'visits:id,visitno');

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

        $query = BillingLog::query()
            ->where('tenant_id', $tenantId)
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->with('billingLogDetails');

        // Service units
        $units = [
            'Registrations' => 'Registration',
            'Pharmacy'      => 'Pharmacy',
            'Laboratory'    => 'Laboratory',
            'Radiology'     => 'Radiology',
            'Consultation'    => 'Consultation',
        ];

        $stats = [];

        foreach ($units as $key => $unitName) {
            $unit = ServiceUnit::where('name', $unitName)->first();
            if (!$unit) {
                $stats[$key] = [
                    'total_amount' => 0,
                    'patients'     => 0,
                ];
                continue;
            }

            $unitQuery = (clone $query)->where('service_unit_id', $unit->id);

            $stats[$key] = [
                'total_amount' => $unitQuery->sum('grand_total'),
                'patients'     => $unitQuery->distinct('patient_id')->count('patient_id'),
            ];
        }

        // Totals
        $stats['TotalRevenue']     = $query->sum('grand_total');
        $stats['PendingPayment']   = (clone $query)->where('payment_status', GeneralEnums::PENDING->value)->sum('grand_total');
        $stats['CompletedPayment'] = (clone $query)->where('payment_status', GeneralEnums::PAID->value)->sum('grand_total');

        // ===== Billing Trends (Monthly Revenue) =====
        $year = $request->year ?? now()->year;

        $monthlyRevenue = BillingLog::selectRaw('MONTH(created_at) as month, SUM(grand_total) as total')
            ->whereYear('created_at', $year)
            ->where('tenant_id', $tenantId)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Fill missing months with 0
        $graphData = [];
        for ($m = 1; $m <= 12; $m++) {
            $graphData[] = [
                'month' => Carbon::create()->month($m)->format('M'),
                'total' => $monthlyRevenue[$m] ?? 0,
            ];
        }

        $stats['BillingTrends'] = [
            'year'  => $year,
            'total' => array_sum($monthlyRevenue),
            'data'  => $graphData,
        ];

        return $stats;
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
                'Amount Paid'       => $billing->amount_paid,
                'Service'       => $billing->service->name ?? 'N/A',
                'Date'      => $billing->created_at->format('Y-m-d H:i'),
                'Status'       => $billing->payment_status,
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'billing.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('billing.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function invoiceOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = BillingLog::query()
            ->where('tenant_id', $tenantId)
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
            ->when(!empty($request['payment_method']), function ($query) use ($request) {
                $query->where('payment_method', $request['payment_method']);
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
            ->with('patient', 'service', 'visits');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function invoiceExport($records, $format)
    {
        $exportData = $records->map(function ($billing) {
            return [
                'Patient Name'      => $billing->patient->firstname . ' ' . $billing->patient->lastname,
                'Invoice No'       => $billing->invoice_number,
                'Service'       => $billing->service->name ?? 'N/A',
                'Payment Method'       => $billing->payment_method ?? 'N/A',
                'Total Amount'       => $billing->grand_total,
                'Amount Paid'       => $billing->amount_paid,
                'Date Billed'      => $billing->created_at->format('Y-m-d H:i'),
                'Payment Status'       => $billing->payment_status,
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'billing_invoice.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('billing_invoice.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function summaryOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = BillingLogDetail::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('
            service_unit_id,
            COUNT(*) as total_invoices,
            SUM(amount) as total_amount,
            SUM(CASE WHEN status = "Paid" THEN amount ELSE 0 END) as amount_paid,
            SUM(CASE WHEN status = "Pending" THEN amount ELSE 0 END) as outstanding
        ')
            ->when(!empty($request['service_unit']), function ($query) use ($request) {
                $query->where('service_unit_id', $request['service_unit']);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->groupBy('service_unit_id')
            ->with('serviceUnit:id,name'); // eager load serviceUnit

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function summaryExport($records, $format)
    {
        $exportData = $records->map(function ($summary) {
            return [
                'Service'      => $summary->serviceUnit->name ?? "N/A",
                'Total Invoice'       => $summary->total_invoices,
                'Total Amount'       => $summary->total_amount,
                'Amount Paid'       => $summary->amount_paid,
                'Outstanding'       => $summary->outstanding
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'billing_summary.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('billing_summary.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function serviceOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $query = BillingLogDetail::query()
            ->where('tenant_id', $tenantId)
            ->where('service_unit_id', $request['service_unit_id'])
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('billingLog.patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('billingLog.patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('billingLog.patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('billingLog.patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when(!empty(strtolower($request['gender'])), function ($query) use ($request) {
                $query->whereRelation('billingLog.patient', 'gender', $request['gender']);
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
            ->with('billingLog.patient')->orderBy('id', 'DESC');

        // Fetch results
        $records = !empty($request['paginate']) && empty($request['export'])
            ? $query->paginate($request['limit'] ?? 15)
            : $query->get();

        // Attach consulted user from landlord DB
        $records->each(function ($item) {
            $consultation = optional(optional($item->billingLog)->visits)->consultation;

            if ($consultation && $consultation->consulted_by) {
                $item->consultedBy = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($consultation->consulted_by);
            } else {
                $item->consultedBy = null;
            }
        });

        return $records;
    }

    public function serviceStats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $query = BillingLogDetail::query()
            ->where('tenant_id', $tenantId)
            ->where('service_unit_id', $request['service_unit_id'])
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->with('billingLog');

        $numberOfPatient = (clone $query)->with('billingLog:id,patient_id')->get()->pluck('billingLog.patient_id')->unique()->count();
        $totalRevenue = (clone $query)->sum('amount');

        return [
            'numberOfPatient' => $numberOfPatient,
            'totalRevenue' => $totalRevenue
        ];
    }

    public function serviceExport($records, $format)
    {
        $exportData = $records->map(function ($service) {
            $billingLog = optional($service->billingLog);
            $patient    = optional($billingLog->patient);
            $consultedBy = optional($service->consultedBy);

            return [
                'Patient Name'   => trim(($patient->firstname ?? 'N/A') . ' ' . ($patient->lastname ?? 'N/A')),
                'Registration No' => $patient->patientno ?? 'N/A',
                'Consulted By'   => $consultedBy->first_name ?? 'N/A',
                'Age'            => $patient->age ?? 'N/A',
                // 'Gender'         => $patient->gender ?? 'N/A',
                // 'Date Joined'    => $patient->created_at ? Carbon::parse($patient->created_at)->toDateString() : 'N/A',
                'Date Billed'    => $billingLog->billing_date ? Carbon::parse($service->created_at)->toDateString() : 'N/A',
                'Amount'         => $service->amount ?? 0,
                'Payment Status' => $service->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'billing_service.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('billing_service.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function oldmakePayment($request, $billing)
    {
        try {
            if ($billing->payment_status === 'Paid') {
                throw new \Exception("This billing is already fully paid.");
            }

            $billingDetails = collect($request->billingDetails);

            $totalPaymentApplied = 0;

            foreach ($billingDetails as $detail) {
                $logDetail = $billing->billingLogDetails()
                    ->where('id', $detail['billing_log_id'])
                    ->first();

                if (!$logDetail) {
                    throw new \Exception("Billing detail not found for ID {$detail['billing_log_id']}");
                }

                // Already fully paid? Skip
                if ($logDetail->status === 'Paid') {
                    continue;
                }

                $amountPaid = $detail['amount'] ?? 0;
                $alreadyPaid = $logDetail->amount_paid ?? 0;
                $outstanding = $logDetail->amount - $alreadyPaid;

                $applied = min($amountPaid, $outstanding);

                $logDetail->amount_paid = $alreadyPaid + $applied;

                if ($logDetail->amount_paid >= $logDetail->amount) {
                    $logDetail->status = 'Paid';
                    $logDetail->amount_paid = $logDetail->amount;
                } elseif ($logDetail->amount_paid > 0) {
                    $logDetail->status = 'Part Paid';
                } else {
                    $logDetail->status = 'Pending';
                }

                $logDetail->save();

                $totalPaymentApplied += $applied;
            }

            // Update billing totals
            $billing->tax_amount = $request->tax_amount ?? $billing->tax_amount ?? 0;
            $billing->discount   = $request->discount ?? $billing->discount ?? 0;

            if (!$billing->grand_total || $billing->grand_total == 0) {
                $itemsTotal = $billing->billingLogDetails()->sum('amount');
                $billing->grand_total = max(0, ($itemsTotal - $billing->discount) + $billing->tax_amount);
            }

            $billing->amount_paid += $totalPaymentApplied;
            if ($billing->amount_paid > $billing->grand_total) {
                $billing->amount_paid = $billing->grand_total;
            }

            $billing->amount_outstanding = max(0, $billing->grand_total - $billing->amount_paid);

            if ($billing->amount_paid == 0) {
                $billing->payment_status = 'Pending';
            } elseif ($billing->amount_paid < $billing->grand_total) {
                $billing->payment_status = 'Part Paid';
            } else {
                $billing->payment_status = 'Paid';
            }

            $billing->payment_method = $request->payment_method;
            $billing->save();

            return $billing->fresh([
                'patient',
                'service',
                'billingLogDetails.treatment',
                'billingLogDetails.labInvestigation',
                'billingLogDetails.radiologyInvestigation',
                'billingLogDetails.serviceUnit'
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function makePayment($request, $billing)
    {
        try {
            if ($billing->payment_status === 'Paid') {
                throw new \Exception("This billing is already fully paid.");
            }

            $billingDetails = collect($request->billingDetails);
            $totalPaymentApplied = 0;

            foreach ($billingDetails as $detail) {
                $logDetail = $billing->billingLogDetails()
                    ->where('id', $detail['billing_log_id'])
                    ->first();

                if (!$logDetail) {
                    throw new \Exception("Billing detail not found for ID {$detail['billing_log_id']}");
                }

                // Already fully paid? Skip
                if ($logDetail->status === 'Paid') {
                    continue;
                }

                $amountPaid  = $detail['amount'] ?? 0;
                $alreadyPaid = $logDetail->amount_paid ?? 0;
                $outstanding = $logDetail->amount - $alreadyPaid;
                $applied     = min($amountPaid, $outstanding);

                $logDetail->amount_paid = $alreadyPaid + $applied;

                if ($logDetail->amount_paid >= $logDetail->amount) {
                    $logDetail->status = 'Paid';
                    $logDetail->amount_paid = $logDetail->amount;
                } elseif ($logDetail->amount_paid > 0) {
                    $logDetail->status = 'Part Paid';
                } else {
                    $logDetail->status = 'Pending';
                }

                $logDetail->save();
                $totalPaymentApplied += $applied;
            }

            // --- Tax and Discount Handling ---
            $billing->discount = $request->discount ?? $billing->discount ?? 0;

            // Calculate items total
            $itemsTotal = $billing->billingLogDetails()->sum('amount');

            $newTax = 0;
            if ($request->filled('tax_amount')) {
                // Use frontend-provided tax for this payment
                $newTax = $request->tax_amount;
            } else {
                // Auto-calculate tax for this payment if not provided
                $newTax = round($itemsTotal * 0.075, 2);
            }

            // Add to existing tax_amount in billing
            $billing->tax_amount = ($billing->tax_amount ?? 0) + $newTax;

            // Grand total excludes tax (only items - discount)
            $billing->grand_total = max(0, $itemsTotal - $billing->discount);

            // --- Payment Progression ---
            $billing->amount_paid += $totalPaymentApplied;

            // Prevent overpayment
            if ($billing->amount_paid > $billing->grand_total) {
                $billing->amount_paid = $billing->grand_total;
            }

            // Outstanding excludes tax (VAT handled separately)
            $billing->amount_outstanding = max(0, $billing->grand_total - $billing->amount_paid);

            // Determine payment status
            if ($billing->amount_paid == 0) {
                $billing->payment_status = 'Pending';
            } elseif ($billing->amount_paid < $billing->grand_total) {
                $billing->payment_status = 'Part Paid';
            } else {
                $billing->payment_status = 'Paid';
            }

            $billing->total_amount = ($billing->amount_paid ?? 0) + ($billing->tax_amount ?? 0);

            $billing->payment_method = $request->payment_method;
            $billing->save();

            return $billing->fresh([
                'patient',
                'service',
                'billingLogDetails.treatment',
                'billingLogDetails.labInvestigation',
                'billingLogDetails.radiologyInvestigation',
                'billingLogDetails.serviceUnit'
            ]);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function updateResult($data, $test)
    {

        $currentUserInstance = UserMgtHelper::userInstance();

        // Create lab result
        foreach ($data->results as $item) {
            $record = LaboratoryResult::updateOrCreate(
                [
                    'patient_visit_lab_id' => $test->id, // Unique match key
                    'test' => $item['test'],
                ],
                [
                    'visit_id'          => $test->visit_id,
                    'result'           => $item['result'],
                    'reference_range'  => $item['reference_range'],
                    'status'           => 'Ready'
                ]
            );
        }

        $test->update([
            'specimen_type' => $data->specimen_type,
            'notes'         => $data->notes,
            'user_id'    => $currentUserInstance->id,
            // 'requested_by'  => $data->requested_by,
            // 'test_status'   => 'completed'
        ]);

        return $test->refresh()->load('results');
    }

    public function updateTest($data, $test)
    {

        $test->update([
            'status'   => $data->status
        ]);

        return $test->refresh();
    }
}
