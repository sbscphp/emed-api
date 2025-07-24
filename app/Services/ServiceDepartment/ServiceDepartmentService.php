<?php

namespace App\Services\ServiceDepartment;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\Consultation;
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

    public function main_dashboard($validated)
    {

        $bills = BillingLog::whereIn('payment_status', ['pending', 'part_paid'])->get();
        $outstanding = 0;
        foreach ($bills as $bill) {
            $ans =  $bill->grand_total - $bill->deposit_amount ?? 0;
            $outstanding = $outstanding + $ans;
        }



        // $revenue = BillingLog::whereIn('payment_status', ['paid', 'part_paid'])
        //     ->when(!empty($validated['filter_calender']), function ($query) use ($validated) {
        //         if ($validated['filter_calender'] == 'daily') {
        //             $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()]);
        //         } elseif ($validated['filter_calender'] == 'monthly') {
        //             $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()]);
        //         } elseif ($validated['filter_calender'] == 'yearly') {
        //             $query->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
        //         }
        //     })->get();

        $revenue = BillingLog::whereIn('payment_status', ['paid', 'part_paid'])
            ->when($validated['filter_calender'] === 'daily', function ($query) {
                $query->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
            })
            ->when($validated['filter_calender'] === 'monthly', function ($query) {
                $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
            })
            ->when($validated['filter_calender'] === 'yearly', function ($query) {
                $query->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
            })
            ->get();
        $revenue_outcome = 0;
        foreach ($revenue as $revenue) {
            $ans =  $bill->grand_total - $bill->deposit_amount ?? 0;
            $revenue_outcome = $outstanding + $ans;
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


        $department_revenue =  [
            [
                "name" => "registration",
                'calender' => [
                    'daily' => BillingLog::where('service_unit_id', 1)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
                    'monthly' => BillingLog::where('service_unit_id', 1)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
                    'yearly' => BillingLog::where('service_unit_id', 1)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

                ]
            ],
            [
                "name" => 'pharmacy',
                'calender' => [
                    'daily' => BillingLog::where('service_unit_id', 2)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
                    'monthly' => BillingLog::where('service_unit_id', 2)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
                    'yearly' => BillingLog::where('service_unit_id', 2)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

                ]
            ],
            [
                "name" => "laboratory",
                'calender' => [
                    'daily' => BillingLog::where('service_unit_id', 4)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
                    'monthly' => BillingLog::where('service_unit_id', 4)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
                    'yearly' => BillingLog::where('service_unit_id', 4)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

                ]
            ],

            [
                "name" => "radiology",
                'calender' => [
                    'daily' => BillingLog::where('service_unit_id', 5)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
                    'monthly' => BillingLog::where('service_unit_id', 5)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
                    'yearly' => BillingLog::where('service_unit_id', 5)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

                ]
            ],
            [
                "name" => "consultation",
                'calender' => [
                    'daily' => BillingLog::where('service_unit_id', 3)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->startOfWeek()])->count(),
                    'monthly' => BillingLog::where('service_unit_id', 3)->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->startOfMonth()])->count(),
                    'yearly' => BillingLog::where('service_unit_id', 3)->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()])->count(),

                ]
            ],

        ];


        // if($validated['filter_calender'] == 'daily' && ){

        // }
        $data = [
            "pateint" => [
                "total" => Patient::count(),
                "admitted_today" => Patient::where("created_at", Carbon::now())->count()
            ],
            "consultation" => [
                "total" => Consultation::count(),
                "missed" => PatientVisit::where('status', 'missed')->count(),
            ],

            "finance" => [
                "total" => BillingLog::where('payment_status', 'paid')->sum('grand_total'),
                'outstanding' => $outstanding
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

        return $data;
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
            $patient->where('patient_type', $validated['gender']);
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
}
