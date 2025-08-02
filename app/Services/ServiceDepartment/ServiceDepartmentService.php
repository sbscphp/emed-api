<?php

namespace App\Services\ServiceDepartment;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use App\Repositories\ServiceDepartment\ServiceDepartmentInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Class ServiceDepartmentService
 * 
 * This class provides services related to ServiceDepartment operations and acts as a 
 * layer between the Controller and the ServiceDepartmentRepository.
 */
class ServiceDepartmentService
{
    protected ServiceDepartmentInterface $ServiceDepartmentInterface;
    /**
     * ServiceDepartment constructor.
     * 
     * @param ServiceDepartmentInterface $ServiceDepartmentInterface
     */
    public function __construct(ServiceDepartmentInterface $ServiceDepartmentInterface)
    {
        $this->ServiceDepartmentInterface = $ServiceDepartmentInterface;
    }

    /**
     * Retrieve all ServiceDepartment.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->ServiceDepartmentInterface->all();
    }

    /**
     * Create a new ServiceDepartment using the data provided.
     * 
     * @param array $data
     * @return \App\Models\ServiceDepartment
     */
    public function create(array $data)
    {
        return $this->ServiceDepartmentInterface->create($data);
    }


    /**
     * Update an existing ServiceDepartment with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function update(array $data, $id)
    {
        return $this->ServiceDepartmentInterface->update($data, $id);
    }


    /**
     * Delete a ServiceDepartment by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->ServiceDepartmentInterface->delete($id);
    }


    /**
     * Find a ServiceDepartment by their ID.
     * 
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function find($id)
    {
        return $this->ServiceDepartmentInterface->find($id);
    }


    /**
     * Find an existing ServiceDepartment  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\ServiceDepartment
     */
    public function findByAttribute($attr, $value)
    {
        return $this->ServiceDepartmentInterface->findByAttribute($attr, $value);
    }

    public function getUnits(array $columns = ['*'], $service_units_name, $from, $to)
    {
        return ServiceUnit::select($columns)->when($service_units_name, function ($query, $service_units_name) {
            return $query->where('name', $service_units_name);
        })
            ->when(!empty($from) && !empty($to), function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [Carbon::parse($from), Carbon::parse($to)]);
            })
            ->get();
    }

    public function getTypes(array $columns = ['*'], $service_types_name, $from, $to)
    {
        return ServiceDepartment::select($columns)->when($service_types_name, function ($query, $service_types_name) {
            return $query->where('name', $service_types_name);
        })
            ->when(!empty($from) && !empty($to), function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [Carbon::parse($from), Carbon::parse($to)]);
            })
            ->get();
    }

    public function create_service($validated)
    {

        $service =  ServiceUnit::create($validated);
        $user = Auth::user();
        $tenantUser = User::on('tenant')->where('email', $user->email)->first();
        $dataToLog = [
            'causer_id' => $tenantUser ? $tenantUser->id : null,
            'action_id' => $service->id,
            'action' => 'Create',
            'action_type' => "Models\ServiceDepartment",
            'log_name' => " record created successfully",
            'description' => "{$tenantUser->firstname} {$tenantUser->firstname} created a Service: {$service->name}",
            'module_accessed' => ListModuleEnums::Service
        ];
        return  GeneralHelper::storeAuditLog($dataToLog);
    }

    public function editservice($validated)
    {

        $service = ServiceUnit::find($validated['id']);
        if ($service) {
            $user = Auth::user();
            $tenantUser = User::on('tenant')->where('email', $user->email)->first();
            $dataToLog = [
                'causer_id' => $tenantUser ? $tenantUser->id : null,
                'action_id' => $service->id,
                'action' => 'Update',
                'action_type' => "Models\ServiceDepartment",
                'log_name' => " record Edited successfully",
                'description' => "{$tenantUser->firstname} {$tenantUser->lastname} Edited a Service: {$service->name}",
                'module_accessed' => ListModuleEnums::Service
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            $service->update([
                "name" => $validated['name']
            ]);
            return $service;
        }
    }

    // public function main_dashboard($validated)
    // {

    //     $bills = BillingLog::whereIn('payment_status', ['pending', 'part_paid'])->get();
    //     $outstanding = 0;
    //     foreach ($bills as $bill) {
    //         $ans =  $bill->grand_total - $bill->deposit_amount ?? 0;
    //         $outstanding = $outstanding + $ans;
    //     }



    //     // $revenue = BillingLog::whereIn('payment_status', ['paid', 'part_paid'])
    //     //     ->when(!empty($validated['filter_calender']), function ($query) use ($validated) {
    //     //         if ($validated['filter_calender'] == 'daily') {
    //     //             $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()]);
    //     //         } elseif ($validated['filter_calender'] == 'monthly') {
    //     //             $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()]);
    //     //         } elseif ($validated['filter_calender'] == 'yearly') {
    //     //             $query->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
    //     //         }
    //     //     })->get();

    //     $revenue = BillingLog::whereIn('payment_status', ['paid', 'part_paid'])
    //         ->when($validated['filter_calender'] === 'daily', function ($query) {
    //             $query->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
    //         })
    //         ->when($validated['filter_calender'] === 'monthly', function ($query) {
    //             $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
    //         })
    //         ->when($validated['filter_calender'] === 'yearly', function ($query) {
    //             $query->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
    //         })
    //         ->get();
    //     $revenue_outcome = 0;
    //     foreach ($revenue as $revenue) {
    //         $ans =  $bill->grand_total - $bill->deposit_amount ?? 0;
    //         $revenue_outcome = $outstanding + $ans;
    //     }

    //     $new = Patient::where('patient_type', 'new')->count();
    //     $existing = Patient::where('patient_type', 'existing')->count();
    //     $referal = Patient::where('patient_type', 'referal')->count();

    //     $services = ServiceUnit::all();
    //     $depart = [];
    //     foreach ($services as $service) {
    //         $count = BillingLog::where('service_unit_id', intval($service->id))->count();
    //         $depart[] = [
    //             'name' => $service->name,
    //             'count' => $count
    //         ];
    //     }


    //     $department_revenue =  [
    //         [
    //             "name" => "registration",
    //             'calender' => [
    //                 'daily' => BillingLog::where('service_unit_id', 1)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
    //                 'monthly' => BillingLog::where('service_unit_id', 1)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
    //                 'yearly' => BillingLog::where('service_unit_id', 1)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

    //             ]
    //         ],
    //         [
    //             "name" => 'pharmacy',
    //             'calender' => [
    //                 'daily' => BillingLog::where('service_unit_id', 2)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
    //                 'monthly' => BillingLog::where('service_unit_id', 2)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
    //                 'yearly' => BillingLog::where('service_unit_id', 2)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

    //             ]
    //         ],
    //         [
    //             "name" => "laboratory",
    //             'calender' => [
    //                 'daily' => BillingLog::where('service_unit_id', 4)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
    //                 'monthly' => BillingLog::where('service_unit_id', 4)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
    //                 'yearly' => BillingLog::where('service_unit_id', 4)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

    //             ]
    //         ],

    //         [
    //             "name" => "radiology",
    //             'calender' => [
    //                 'daily' => BillingLog::where('service_unit_id', 5)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
    //                 'monthly' => BillingLog::where('service_unit_id', 5)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
    //                 'yearly' => BillingLog::where('service_unit_id', 5)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

    //             ]
    //         ],
    //         [
    //             "name" => "consultation",
    //             'calender' => [
    //                 'daily' => BillingLog::where('service_unit_id', 3)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
    //                 'monthly' => BillingLog::where('service_unit_id', 3)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
    //                 'yearly' => BillingLog::where('service_unit_id', 3)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

    //             ]
    //         ],

    //     ];


    //     // if($validated['filter_calender'] == 'daily' && ){

    //     // }
    //     $data = [
    //         "pateint" => [
    //             "total" => Patient::count(),
    //             "admitted_today" => Patient::where("created_at", Carbon::now())->count()
    //         ],
    //         "consultation" => [
    //             "total" => Consultation::count(),
    //             "missed" => PatientVisit::where('status', 'missed')->count(),
    //         ],

    //         "finance" => [
    //             "total" => BillingLog::where('payment_status', 'paid')->sum('grand_total'),
    //             'outstanding' => $outstanding
    //         ],

    //         "revenue_stat" => $revenue_outcome,
    //         "department_revenue" => $department_revenue,

    //         "patint_type" => [
    //             'new' => $new,
    //             'existing' => $existing,
    //             "referal" => $referal
    //         ],
    //         'staff_log' => $depart
    //     ];

    //     return $data;
    // }


    public function main_dashboard($validated)
    {
        // Calculate outstanding
        $bills = BillingLog::whereIn('payment_status', ['pending', 'part_paid'])->get();
        $outstanding = 0;
        foreach ($bills as $bill) {
            $ans = ($bill->grand_total ?? 0) - ($bill->deposit_amount ?? 0);
            $outstanding += $ans;
        }



        $revenue = BillingLog::whereIn('payment_status', ['paid', 'part_paid'])
            ->when(!empty($validated['filter_calender'])  && $validated['filter_calender'] === 'daily', function ($query) {
                $query->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
            })
            ->when(!empty($validated['filter_calender']) && $validated['filter_calender'] === 'monthly', function ($query) {
                $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
            })
            ->when(!empty($validated['filter_calender']) && $validated['filter_calender'] === 'yearly', function ($query) {
                $query->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
            })->when(!empty($validated['state_date']) && !empty($validated['end_date']), function ($query) use ($validated) {
                // state_date, end_date
                $query->whereBetween('created_at', [Carbon::parse($validated['state_data']), Carbon::parse($validated['end_data'])]);
            })
            ->get();

        $revenue_outcome = 0;
        foreach ($revenue as $rev) {
            $ans = ($rev->grand_total ?? 0) - ($rev->deposit_amount ?? 0);
            $revenue_outcome += $ans;
        }

        $new = Patient::where('patient_type', 'new')->count();
        $existing = Patient::where('patient_type', 'existing')->count();
        $referal = Patient::where('patient_type', 'referal')->count();

        $services = ServiceUnit::all();
        $depart = [];
        foreach ($services as $service) {
            $count = BillingLog::where('service_unit_id', intval($service->id))->count();
            $depart[] = [
                'name' => $service->name,
                'count' => $count
            ];
        }

        // Prepare custom date range if provided
        $customDate = [];
        if (
            ($validated['period'] ?? '') === 'custom date' &&
            !empty($validated['start_date']) &&
            !empty($validated['end_date'])
        ) {
            $customDate = [$validated['start_date'], $validated['end_date']];
        }

        // Use shared date filter logic
        $dateFilter = GeneralHelper::dateFilter($validated['period'] ?? null, $customDate);

        // Determine actual date range
        if (!empty($customDate) && count($customDate) === 2) {
            $startDate = Carbon::parse($customDate[0])->startOfDay();
            $endDate = Carbon::parse($customDate[1])->endOfDay();
        } elseif (is_array($dateFilter) && count($dateFilter) === 2) {
            [$startDate, $endDate] = $dateFilter;
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } else {
            // Default to last 7 days
            $endDate = Carbon::today()->endOfDay();
            $startDate = $endDate->copy()->subDays(6)->startOfDay();
        }

        // Fetch and filter revenue data
        $query = BillingLog::whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($validated['department_id'])) {
            $query->where('service_unit_id', $validated['department_id']);
        }

        $revenueData = $query->get()->groupBy(function ($log) {
            return Carbon::parse($log->created_at)->format('D');
        })->map(function ($group) {
            return round($group->sum('amount'), 2);
        });

        // Build chart data for week
        $weekdays = collect(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']);
        $department_revenue = $weekdays->map(function ($day) use ($revenueData) {
            return [
                'day' => $day,
                'revenue' => $revenueData->get($day, 0),
            ];
        });


        // Final data
        return [
            "pateint" => [
                "total" => Patient::count(),
                "admitted_today" => Patient::whereDate("created_at", Carbon::now()->toDateString())->count()
            ],
            "consultation" => [
                "total" => Consultation::count(),
                "missed" => PatientVisit::where('status', 'missed')->count(),
            ],
            "finance" => [
                "total" => BillingLog::where('payment_status', 'paid')->sum('grand_total'),
                'outstanding' => $outstanding
            ],
            "lab" => [
                "pending" => Laboratory::where('test_status', 'pending')->count(),
                'completed' => Laboratory::where('test_status', 'completed')->count()
            ],
            "revenue_stat" => $revenue_outcome,
            "department_revenue" => $department_revenue,
            "patint_type" => [
                'new' => $new,
                'existing' => $existing,
                "referal" => $referal
            ],
            'staff_log' => $depart
        ];
    }



    public function top_drugs()
    {
        $medications = Medication::all();
        $arr = [];
        foreach ($medications as  $medication) {
            $medication->medicine_name;
            $medication->cost_price;
            $count = Treatment::where("drug_id", $medication->id)->count();
            $ans = intval($medication->cost_price) * $count;

            $arr[] = [
                "name" => $medication->medicine_name,
                'total' => $ans
            ];
        }

        rsort($arr);
        $max = array_slice($arr, 0, 5);

        return $max;
    }

    public function patient_diagnosis()
    {
        // 	diagnosis
        $topDiagnosis = Consultation::select('diagnosis', DB::raw('count(*) as count'))
            ->groupBy('diagnosis')
            ->orderByDesc('count')
            ->take(5)
            ->get();

        return  $topDiagnosis;
    }

    public function recent_patient($validated)
    {

        $patient = Patient::with('patient_visits_latest');
        // ->where(!empty($validated['search']), function ($query) use ($validated) {
        //     $query->where('firstname', $validated['search'])
        //         ->orWhere('lastname', $validated['search'])
        //         ->orWhere('patientno', $validated['search'])
        //         ->orWhere('status', $validated['search'])
        //         ->orWhere('patient_type', $validated['search'])
        //         ->orWhere('gender', $validated['search'])
        //         ->orWhere('marital_status', $validated['search'])
        //         ->orWhereHas('patient_visits_latest', function ($qu) use ($validated) {
        //             $qu->where('status', $validated['search']);
        //         });
        // });
        if (!empty($validated['search'])) {
            $patient->where(function ($query) use ($validated) {
                $query->where('firstname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('patientno', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('status', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('patient_type', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('gender', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('marital_status', 'like', '%' . $validated['search'] . '%')
                    ->orWhereHas('patient_visits_latest', function ($qu) use ($validated) {
                        $qu->where('status', 'like', '%' . $validated['search'] . '%');
                    });
            });
        }


        if (!empty($validated['status'])) {
            $patient->orWhereHas('patient_visits_latest', function ($qu) use ($validated) {
                $qu->where('status', $validated['search']);
            });
        }


        if (!empty($validated['patient_type'])) {
            $patient->where('patient_type', $validated['patient_type']);
        }

        if (!empty($validated['gender'])) {
            $patient->where('gender', $validated['gender']);
        }

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $patient->whereHas('patient_visits_latest', function ($qu) use ($validated) {
                $startDate = $validated['start_date'];
                $endDate = $validated['end_date'];
                $qu->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            });
        }
        return $patient->paginate(10);
        // where('firstname', $firstname)->where('lastname', $lastname)

    }

    public function revenue($request)
    {
        // Extract custom date range if explicitly provided
        $customDate = [];
        if (
            ($request['period'] ?? '') === 'custom date' &&
            !empty($request['start_date']) &&
            !empty($request['end_date'])
        ) {
            $customDate = [$request['start_date'], $request['end_date']];
        }

        // Use helper to resolve date range
        $dateFilter = GeneralHelper::dateFilter($request['period'] ?? null, $customDate);

        // Determine effective date range
        if (!empty($customDate) && count($customDate) === 2) {
            $startDate = Carbon::parse($customDate[0])->startOfDay();
            $endDate = Carbon::parse($customDate[1])->endOfDay();
        } elseif (is_array($dateFilter) && count($dateFilter) === 2) {
            [$startDate, $endDate] = $dateFilter;
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } else {
            $startDate = null;
            $endDate = null;
        }

        // Static time ranges
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->startOfWeek();
        $thisMonthStart = $today->copy()->startOfMonth();
        $thisYearStart = $today->copy()->startOfYear();
        $last3Days = [Carbon::today()->subDays(2), Carbon::today()];

        // Predefined statistics
        $baseQuery = BillingLog::query();
        $statistics = [
            'today' => round((clone $baseQuery)->whereDate('billing_date', $today)->sum('grand_total'), 2),
            'this_week' => round((clone $baseQuery)->whereBetween('billing_date', [$thisWeekStart, $today])->sum('grand_total'), 2),
            'this_month' => round((clone $baseQuery)->whereBetween('billing_date', [$thisMonthStart, $today])->sum('grand_total'), 2),
            'this_year' => round((clone $baseQuery)->whereBetween('billing_date', [$thisYearStart, $today])->sum('grand_total'), 2),
            'last_3_days' => round((clone $baseQuery)->whereBetween('billing_date', $last3Days)->sum('grand_total'), 2),
        ];

        // Total department revenue (without date filter)
        $query = BillingLog::query();
        if (!empty($request['department_id'])) {
            $query->where('service_unit_id', $request['department_id']);
        }
        if ($startDate && $endDate) {
            $query->whereBetween('billing_date', [$startDate, $endDate]);
        }
        $totalDepartmentRevenue = round($query->sum('grand_total'), 2);

        // Revenue for selected date range
        $filteredQuery = BillingLog::query();
        if ($startDate && $endDate) {
            $filteredQuery->whereBetween('billing_date', [$startDate, $endDate]);
        }

        $totalRevenue = round($filteredQuery->sum('grand_total'), 2);

        // Top earning department for selected date range
        $topDepartment = BillingLog::with('serviceUnit')
            ->select('service_unit_id', DB::raw('SUM(grand_total) as total'))
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('billing_date', [$startDate, $endDate]);
            })
            ->groupBy('service_unit_id')
            ->orderByDesc('total')
            ->first();

        $topDepartmentRevenue = [
            'name' => $topDepartment?->serviceUnit?->name ?? 'N/A',
            'amount' => round($topDepartment?->total ?? 0, 2),
        ];

        return [
            'totalRevenue' => $totalRevenue,
            'topDepartmentRevenue' => $topDepartmentRevenue,
            'statistics' => $statistics,
            'totalDepartmentRevenue' => $totalDepartmentRevenue,
        ];
    }


    public function in_and_out_patient($request)
    {
        $period = $request->input('period');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $customDate = [];
        if ($period === 'custom date' && $startDateInput && $endDateInput) {
            $customDate = [$startDateInput, $endDateInput];
        }

        // Use shared logic or fallback to current year
        $dateFilter = GeneralHelper::dateFilter($period, $customDate);

        if (!empty($customDate)) {
            $startDate = Carbon::parse($customDate[0])->startOfDay();
            $endDate = Carbon::parse($customDate[1])->endOfDay();
        } elseif ($dateFilter) {
            $startDate = $dateFilter[0];
            $endDate = $dateFilter[1];
        } else {
            $endDate = Carbon::now()->endOfDay();
            $startDate = $endDate->copy()->startOfYear();
        }

        // Fetch all consultations within the date range
        $consultations = Consultation::whereBetween('created_at', [$startDate, $endDate])->get();

        $chartData = [];
        $totalInpatient = 0;
        $totalOutpatient = 0;

        $months = range(1, 12);
        $currentYear = now()->year;

        foreach ($months as $month) {
            $startOfMonth = Carbon::create($currentYear, $month, 1)->startOfMonth();
            $endOfMonth = Carbon::create($currentYear, $month, 1)->endOfMonth();

            if ($startOfMonth->lt($startDate) || $startOfMonth->gt($endDate)) {
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

            $chartData[] = [
                'label' => $startOfMonth->format('M Y'),
                'inpatient' => $in,
                'outpatient' => $out,
            ];
        }

        return [
            'total_inpatient' => $totalInpatient,
            'total_outpatient' => $totalOutpatient,
            'chart_data' => $chartData,
        ];
    }

    public function appointments($request)
    {
        $period = $request->input('period');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $customDate = [];
        if ($period === 'custom date' && $startDateInput && $endDateInput) {
            $customDate = [$startDateInput, $endDateInput];
        }

        $dateFilter = GeneralHelper::dateFilter($period, $customDate);

        if (!empty($customDate)) {
            $startDate = Carbon::parse($customDate[0])->startOfDay();
            $endDate = Carbon::parse($customDate[1])->endOfDay();
        } elseif ($dateFilter) {
            $startDate = $dateFilter[0];
            $endDate = $dateFilter[1];
        } else {
            $endDate = Carbon::now()->endOfDay();
            $startDate = $endDate->copy()->startOfYear();
        }

        // Fetch appointments within date range
        $appointments = PatientVisit::whereBetween('created_at', [$startDate, $endDate])->get();

        $chartData = [];
        $months = range(1, 12);
        $selectedYear = $startDate->year;

        foreach ($months as $month) {
            $startOfMonth = Carbon::create($selectedYear, $month, 1)->startOfMonth();
            $endOfMonth = Carbon::create($selectedYear, $month, 1)->endOfMonth();

            if ($startOfMonth->lt($startDate) || $startOfMonth->gt($endDate)) {
                $count = 0;
            } else {
                $monthly = $appointments->filter(function ($a) use ($startOfMonth, $endOfMonth) {
                    $created = Carbon::parse($a->created_at);
                    return $created->between($startOfMonth, $endOfMonth);
                });

                $count = $monthly->count();
            }

            $chartData[] = [
                'label' => $startOfMonth->format('M'),
                'appointments' => $count
            ];
        }

        return [
            'totalAppointments' => $appointments->count(),
            'appointmentData' => $chartData,
        ];
    }

    public function departments()
    {
        $departments = ServiceUnit::all();
        return $departments;
    }
}
