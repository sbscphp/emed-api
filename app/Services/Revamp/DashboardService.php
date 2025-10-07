<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\Consultation;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Treatment;
use App\Repositories\Laboratory\LaboratoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class DashboardService
 *
 * This class provides services related to Laboratory operations and acts as a
 * layer between the Controller and the LaboratoryRepository.
 */
class DashboardService
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

        $records = Patient::query()
            ->where('tenant_id', $tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('cardno', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'alphabetically', function ($query) {
                $query->orderBy('firstname', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with('nextOfKin', 'emergencyContact', 'visits_recent');

        return $records->orderBy('id', 'DESC')->limit(5)->get();
    }

    public function stats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $patientQuery = Patient::query()->where('tenant_id', $tenantId);
        $totalPatient = (clone $patientQuery)->count();
        $totalAdmittedPatient = (clone $patientQuery)->where('status', GeneralEnums::ADMITTED->value)->count();

        $consultationQuery = Consultation::query()->where('tenant_id', $tenantId);
        $totalConsultation = (clone $consultationQuery)->count();
        $totalPendingConsultation = PatientVisit::where('status', PatientVisitStatusEnums::VISIT_INITIATED->value)
            ->where('tenant_id', $tenantId)->count();

        $billingQuery = BillingLog::query()->where('tenant_id', $tenantId);
        $totalRevenue = (clone $billingQuery)->sum('grand_total');
        $outstandingPayment = (clone $billingQuery)->sum('amount_outstanding');

        $totalReferrals = (clone $patientQuery)->whereNotNull('referral')->count();
        $totalReferralsToday = (clone $patientQuery)->whereNotNull('referral')->whereDate('created_at', Carbon::today())->count();

        $totalFilteredRevenue = (clone $billingQuery)
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->sum('grand_total');

        // Top earning department for selected date range
        $topDepartment = BillingLogDetail::whereRelation('billingLog', 'tenant_id', $tenantId)
            ->with('serviceUnit')
            ->select('service_unit_id', DB::raw('SUM(amount) as total'))
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->groupBy('service_unit_id')
            ->orderByDesc('total')
            ->first();

        $topDepartmentRevenue = [
            'name' => $topDepartment?->serviceUnit?->name ?? 'N/A',
            'amount' => round($topDepartment?->total ?? 0, 2),
        ];

        // Start Statistics
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->startOfWeek();
        $thisMonthStart = $today->copy()->startOfMonth();
        $thisYearStart = $today->copy()->startOfYear();
        $last3Days = [Carbon::today()->subDays(2), Carbon::today()];
        $statistics = [
            'today' => round((clone $billingQuery)->whereDate('billing_date', $today)->sum('grand_total'), 2),
            'this_week' => round((clone $billingQuery)->whereBetween('billing_date', [$thisWeekStart, $today])->sum('grand_total'), 2),
            'this_month' => round((clone $billingQuery)->whereBetween('billing_date', [$thisMonthStart, $today])->sum('grand_total'), 2),
            'this_year' => round((clone $billingQuery)->whereBetween('billing_date', [$thisYearStart, $today])->sum('grand_total'), 2),
            'last_3_days' => round((clone $billingQuery)->whereBetween('billing_date', $last3Days)->sum('grand_total'), 2),
        ];
        // End Statistics

        $totalDepartmentRevenue = BillingLogDetail::whereRelation('billingLog', 'tenant_id', $tenantId)
            ->when(!empty($request['department_id']), function ($query) use ($request) {
                $query->where('service_unit_id', $request['department_id']);
            })->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->sum('amount');

        // Top 5 Drugs
        $medications = Medication::where('tenant_id', $tenantId)->get();
        $topDrugs = [];

        foreach ($medications as $medication) {
            // Count treatments within the date range
            $treatmentQuery = Treatment::where('drug_id', $medication->id)
                ->where('tenant_id', $tenantId)
                ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                    $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
                })
                ->when($dateFilter, function ($query) use ($dateFilter) {
                    return $query->whereBetween('created_at', $dateFilter);
                });

            $quantity = $treatmentQuery->sum('quantity');

            $topDrugs[] = [
                'name' => $medication->medicine_name,
                'totalQuantity' => $quantity
            ];
        }
        usort($topDrugs, fn($a, $b) => $b['totalQuantity'] <=> $a['totalQuantity']);
        $max = array_slice($topDrugs, 0, 5);

        $newPatient = (clone $patientQuery)->where('status', GeneralEnums::NEW->value)->count();
        $existingPatient = (clone $patientQuery)->where('status', GeneralEnums::EXISTING->value)->count();

        $total = $newPatient + $existingPatient + $totalReferrals;
        $newPatientPercentage = $total > 0 ? round(($newPatient / $total) * 100, 2) : 0;
        $existingPatientPercentage = $total > 0 ? round(($existingPatient / $total) * 100, 2) : 0;
        $referralPatientPercentage = $total > 0 ? round(($totalReferrals / $total) * 100, 2) : 0;

        // Number of in and out patients
        $consultations = Consultation::where('tenant_id', $tenantId)
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->get();

        $numberOfInAndOutPatient = [];
        $totalInpatient = 0;
        $totalOutpatient = 0;

        $months = range(1, 12);
        $currentYear = now()->year;

        // define date boundaries
        $filterStart = $dateFilter[0] ?? Carbon::create($currentYear, 1, 1)->startOfYear();
        $filterEnd   = $dateFilter[1] ?? Carbon::create($currentYear, 12, 31)->endOfYear();

        foreach ($months as $month) {
            $startOfMonth = Carbon::create($currentYear, $month, 1)->startOfMonth();
            $endOfMonth   = Carbon::create($currentYear, $month, 1)->endOfMonth();

            // only count months within selected period
            if ($startOfMonth->lt($filterStart) || $endOfMonth->gt($filterEnd)) {
                $in = 0;
                $out = 0;
            } else {
                $monthly = $consultations->filter(function ($c) use ($startOfMonth, $endOfMonth) {
                    $created = Carbon::parse($c->created_at);
                    return $created->between($startOfMonth, $endOfMonth);
                });

                $in = $monthly->where('admitted', 1)->count();
                $out = $monthly->where('admitted', 0)->count();
            }

            $totalInpatient += $in;
            $totalOutpatient += $out;

            $numberOfInAndOutPatient[] = [
                'label' => $startOfMonth->format('M Y'),
                'inpatient' => $in,
                'outpatient' => $out,
            ];
        }

        // Gender and Age distribution
        $age_0_18 = (clone $patientQuery)->whereBetween('dob', [Carbon::now()->subYears(18), Carbon::now()])->count();
        $age_19_35 = (clone $patientQuery)->whereBetween('dob', [Carbon::now()->subYears(35), Carbon::now()->subYears(19)->subDay()])->count();
        $age_36_plus = (clone $patientQuery)->where('dob', '<', Carbon::now()->subYears(36))->count();

        $male = (clone $patientQuery)->whereIn('gender', ['male', 'Male', 'MALE'])->count();
        $female = (clone $patientQuery)->whereIn('gender', ['female', 'Female', 'FEMALE'])->count();

        // visits trends
        $visits = PatientVisit::where('tenant_id', $tenantId)
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })
            ->get();

        $visitChartData = [];
        $months = range(1, 12);

        // define boundaries
        $filterStart = $dateFilter[0] ?? now()->startOfYear();
        $filterEnd   = $dateFilter[1] ?? now()->endOfYear();
        $selectedYear = $filterStart->year;

        foreach ($months as $month) {
            $startOfMonth = Carbon::create($selectedYear, $month, 1)->startOfMonth();
            $endOfMonth   = Carbon::create($selectedYear, $month, 1)->endOfMonth();

            // only include months inside the filter range
            if ($startOfMonth->lt($filterStart) || $endOfMonth->gt($filterEnd)) {
                $count = 0;
            } else {
                $monthly = $visits->filter(function ($visit) use ($startOfMonth, $endOfMonth) {
                    $created = Carbon::parse($visit->created_at);
                    return $created->between($startOfMonth, $endOfMonth);
                });

                $count = $monthly->count();
            }

            $visitChartData[] = [
                'label' => $startOfMonth->format('M'),
                'visits' => $count,
            ];
        }

        $laboratoryQuery = Laboratory::query()->where('tenant_id', $tenantId)
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            });

        $totalLabTest = (clone $laboratoryQuery)->count();
        $totalPendingLabTest = (clone $laboratoryQuery)->where('status', GeneralEnums::NOT_READY->value)->count();
        $totalCompletedLabTest = (clone $laboratoryQuery)->where('status', GeneralEnums::READY->value)->count();

        $pendingPercentage = $totalLabTest > 0 ? round(($totalPendingLabTest / $totalLabTest) * 100, 2) : 0;
        $completedPercentage = $totalLabTest > 0 ? round(($totalCompletedLabTest / $totalLabTest) * 100, 2) : 0;

        return [
            'totalPatient' => $totalPatient,
            'totalAdmittedPatient' => $totalAdmittedPatient,
            'totalConsultation' => $totalConsultation,
            'totalPendingConsultation' => $totalPendingConsultation,
            'totalRevenue' => round($totalRevenue, 2),
            'outstandingPayment' => round($outstandingPayment, 2),
            'totalReferrals' => $totalReferrals,
            'totalReferralsToday' => $totalReferralsToday,

            'totalFilteredRevenue' => round($totalFilteredRevenue, 2),
            'topDepartmentRevenue' => $topDepartmentRevenue,
            'statistics' => $statistics,
            'totalDepartmentRevenue' => $totalDepartmentRevenue,

            'topDrugs' => $max,
            'newPatientPercentage' => $newPatientPercentage,
            'existingPatientPercentage' => $existingPatientPercentage,
            'referralPatientPercentage' => $referralPatientPercentage,

            'totalInAndOut' => [
                'inpatient' => $totalInpatient,
                'outpatient' => $totalOutpatient,
            ],
            'numberOfInAndOutPatient' => $numberOfInAndOutPatient,

            '0-18' => $totalPatient ? round(($age_0_18 / $totalPatient) * 100, 2) : 0,
            '19-35' => $totalPatient ? round(($age_19_35 / $totalPatient) * 100, 2) : 0,
            '36+' => $totalPatient ? round(($age_36_plus / $totalPatient) * 100, 2) : 0,
            'male' => $male,
            'female' => $female,

            'totalVisits' => $visits->count(),
            'visitChartData' => $visitChartData,

            'totalLabTest' => $totalLabTest,
            'totalPendingLabTest' => $totalPendingLabTest,
            'pendingPercentage' => $pendingPercentage,
            'totalCompletedLabTest' => $totalCompletedLabTest,
            'completedPercentage' => $completedPercentage
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($patient) {
            return [
                'Patient Card'      => $patient->cardno,
                'First Name'      => $patient->firstname,
                'Last Name'       => $patient->lastname,
                'Patient No'       => $patient->patientno,
                'Email'           => $patient->email,
                'Phone Number'    => $patient->phoneno,
                'Patient Status'  => $patient->status,
                'Registered Date' => $patient->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients_export.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = PDF::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients_export.pdf');
        }

        throw new \Exception("Invalid export format.");
    }
}
