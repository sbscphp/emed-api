<?php

namespace App\Services\BillingLog;

use App\Enums\PatientVisitStageEnums;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\Consultation_service;
use App\Models\LabService;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\Medicine_Log;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Pharmacy;
use App\Models\PharmacyService;
use App\Models\Radiology;
use App\Models\RadiologyService;
use App\Models\Registartion_Service;
use App\Models\Service;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use App\Models\User;
use App\Repositories\BillingLog\BillingLogRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class BillingLogService
{
    protected $repo;

    public function __construct(BillingLogRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function create(array $data)
    {
        $unitPrice = $data['unit_price'];
        $quantity = $data['quantity'];
        $subTotal = $unitPrice * $quantity;

        $taxAmount = $data['tax_amount'] ?? 0;

        $grandTotal = $subTotal + $taxAmount;

        if ($data['payment_status'] === 'part_paid') {
            if (isset($data['deposit_amount'])) {
                if ($data['deposit_amount'] > $grandTotal) {
                    throw new \Exception('Deposit amount cannot exceed the grand total.');
                }
            } else {
                $data['deposit_amount'] = 0;
            }
        } else {
            $data['deposit_amount'] = null;
        }
        $data['invoice_number'] = $this->generateInvoiceNumber();
        $data['sub_total'] = $subTotal;
        $data['tax_amount'] = $taxAmount;
        $data['grand_total'] = $grandTotal;
        $patientVisit = optional(PatientVisit::where('id', intval($data['visit_id']))->first());



        $medicine_Log = Medicine_Log::where(['visitno' => $patientVisit->visitno, 'patient_id' => $patientVisit->patient_id])->first();

        if ($medicine_Log) {
            $medicine_Log->update([
                // 'medication_id' => $med['drug_id'],
                // 'pharmacy_id' => $med['pharmacy_id'],
                // 'presscribed_drug' => $med['drug'],
                'patient_status' => PatientVisitStageEnums::DISCHARGED,
                // 'status' => 'Fulfilled',
                // 'action' => null
            ]);
        }
        return $this->repo->create($data);
    }


    public function all($request)
    {
        return $this->repo->all($request);
    }

    public function find($id)
    {
        return $this->repo->find($id);
    }

    public function update($id, $data)
    {
        $unitPrice = $data['unit_price'];
        $quantity = $data['quantity'];
        $subTotal = $unitPrice * $quantity;

        $taxAmount = $data['tax_amount'] ?? 0;

        $grandTotal = $subTotal + $taxAmount;

        if ($data['payment_status'] === 'part_paid') {
            if (isset($data['deposit_amount'])) {
                if ($data['deposit_amount'] > $grandTotal) {
                    throw new \Exception('Deposit amount cannot exceed the grand total.');
                }
            } else {
                $data['deposit_amount'] = 0;
            }
        } else {
            $data['deposit_amount'] = null;
        }

        $data['sub_total'] = $subTotal;
        $data['tax_amount'] = $taxAmount;
        $data['grand_total'] = $grandTotal;

        return $this->repo->update($id, $data);
    }

    public function delete($id)
    {
        return $this->repo->delete($id);
    }

    private function generateInvoiceNumber()
    {
        $lastBilling = $this->repo->getLatest();

        if ($lastBilling && $lastBilling->invoice_number) {
            $lastNumber = (int) str_replace('INV-', '', $lastBilling->invoice_number);
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return 'INV-' . $nextNumber;
    }
    public function getMonthlyRevenue()
    {
        return $this->repo->getMonthlyRevenue();
    }
    public function getPendingPayment()
    {
        return $this->repo->getPendingPayment();
    }
    public function getCompletedPayment()
    {
        return $this->repo->getCompletedPayment();
    }

    public function getFinancialReport($request)
    {
        return $this->repo->getFinancialReport($request);
    }

    public function getByServiceUnit(int $serviceUnitId, int $perPage = 10): LengthAwarePaginator
    {
        return BillingLog::with(['patient', 'serviceType', 'serviceUnit'])
            ->where('service_unit_id', $serviceUnitId)
            ->latest()
            ->paginate($perPage);
    }

    public function getByServiceType(?int $serviceTypeId, int $perPage = 10): LengthAwarePaginator
    {
        $query = BillingLog::with(['patient', 'serviceType', 'serviceUnit']);

        if ($serviceTypeId && $serviceTypeId !== 'all') {
            $query->where('service_type_id', $serviceTypeId);
        }

        return $query->paginate($perPage);
    }

    public function sumByServiceUnit(int $serviceUnitId, string $column)
    {
        return BillingLog::where('service_unit_id', $serviceUnitId)->sum($column);
    }

    public function registration_list($validated)
    {
        // $patient =  Patient::with('visits_recent.billingLogsForPatient');
        $patient =  BillingLog::with(['serviceUnit', 'patient', 'visits_recent.consultation.pharmacist', 'visits_recent.billingLogsForPatient'])->where('service_unit_id', 3)
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->whereHas('patient', function ($q) use ($validated) {
                    $q->where('firstname',  'like',  "%{$validated['search']}%")
                        ->orWhere('lastname',  'like',  "%{$validated['search']}%")
                        ->orWhere('patientno',  'like', "%{$validated['search']}%")
                        ->orWhereRaw('LOWER(gender) = ?', [strtolower($validated['search'])]);
                });
            });

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $patient->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        if (!empty($validated['gender'])) {
            $patient->whereHas('patient', function ($q) use ($validated) {
                $q->whereRaw('LOWER(gender) = ?', [strtolower($validated['gender'])]);
            });
        }
        return $patient->paginate(10);
    }

    public function pharmacy_list($validated)
    {
        // $pharm =  BillingLog::with(['serviceUnit', 'patient', 'visits_recent.consultation.pharmacist'])->where('service_unit_id', 3)
        //     ->when(!empty($validated['search']), function ($query) use ($validated) {
        //         $query->where('payment_status', 'like', '%' . $validated['search'] . '%')
        //             ->whereHas('patient', function ($q) use ($validated) {
        //                 $q->where('firstname', 'like', '%' . $validated['search'] . '%')
        //                     ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
        //                     ->orwhere('patientno', 'like', '%' . $validated['search'] . '%');
        //             })
        //             ->orWhereHas('visits_recent.consultation.pharmacist', function ($q) use ($validated) {
        //                 $q->where('first_name', 'like', '%' . $validated['search'] . '%')
        //                     ->orWhere('last_name', 'like', '%' . $validated['search'] . '%');
        //             });
        //     });
        $pharm = BillingLog::with([
            'serviceUnit',
            'patient',
            'visits_recent.consultation.pharmacist'
        ])
            ->where('service_unit_id', 3)
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $searchTerm = '%' . $validated['search'] . '%';

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('payment_status', 'like', $searchTerm)
                        ->orWhere('patient_name', 'like', $searchTerm)
                        ->orWhereHas('patient', function ($q2) use ($searchTerm) {
                            $q2->where('firstname', 'like', $searchTerm)
                                ->orWhere('lastname', 'like', $searchTerm)
                                ->orWhere('patientno', 'like', $searchTerm);
                        })
                        ->orWhereHas('visits_recent.consultation.pharmacist', function ($q3) use ($searchTerm) {
                            $q3->where('first_name', 'like', $searchTerm)
                                ->orWhere('last_name', 'like', $searchTerm);
                        });
                });
            });



        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {

            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $pharm->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        return  $pharm->paginate(10);
    }


    public function consultation_list($validated)
    {


        $radiology = BillingLog::with(['serviceUnit', 'patient'])
            ->where('service_unit_id', 3)
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $searchTerm = '%' . $validated['search'] . '%';

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('payment_status', 'like', $searchTerm)
                        ->orWhere('patient_name', 'like', $searchTerm)
                        ->orWhere('payment_method', 'like', $searchTerm)
                        ->orWhereHas('patient', function ($q2) use ($searchTerm) {
                            $q2->where('firstname', 'like', $searchTerm)
                                ->orWhere('lastname', 'like', $searchTerm)
                                ->orWhere('patientno', 'like', $searchTerm);
                        });
                });
            });



        if (!empty($validated['payment_method'])) {

            $radiology->where('payment_method', $validated['payment_method']);
        }


        if (!empty($validated['payment_status'])) {

            $radiology->where('payment_status', $validated['payment_status']);
        }


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {

            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $radiology->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        return   $radiology->paginate(10);
    }

    public function laboratory_list($validated)
    {
        // $laboratory = BillingLog::with(['serviceUnit', 'patient.laboratory'])->where('service_unit_id', 4)
        //     ->when(!empty($validated['search']), function ($query) use ($validated) {
        //         $query->where('payment_status', 'like', '%' . $validated['search'] . '%')
        //             ->orWhere('patient_name', 'like', '%' . $validated['search'] . '%')
        //             ->orWhere('payment_method', 'like', '%' . $validated['search'] . '%')
        //             ->orWhereHas('patient', function ($q) use ($validated) {
        //                 $q->where('firstname', 'like', '%' . $validated['search'] . '%')
        //                     ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
        //                     ->orWhere('patientno', 'like', '%' . $validated['search'] . '%');
        //             });
        //     });

        $laboratory = BillingLog::with(['serviceUnit', 'patient.laboratory'])
            ->where('service_unit_id', 4)
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('payment_status', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('patient_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('payment_method', 'like', '%' . $validated['search'] . '%')
                        ->orWhereHas('patient', function ($q2) use ($validated) {
                            $q2->where('firstname', 'like', '%' . $validated['search'] . '%')
                                ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                                ->orWhere('patientno', 'like', '%' . $validated['search'] . '%');
                        });
                });
            });


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $laboratory->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        return   $laboratory->paginate(10);
    }

    public function radiology_list($validated)
    {
        //$radiology =  Radiology::with('patient.visits_recent.billingLogsForPatient');
        $radiology = BillingLog::with(['serviceUnit', 'patient'])
            ->where('service_unit_id', 5)
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('payment_status', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('patient_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('payment_method', 'like', '%' . $validated['search'] . '%')
                        ->orWhereHas('patient', function ($q2) use ($validated) {
                            $q2->where('firstname', 'like', '%' . $validated['search'] . '%')
                                ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                                ->orWhere('patientno', 'like', '%' . $validated['search'] . '%');
                        });
                });
            });


        if (!empty($validated['payment_method'])) {

            $radiology->where('payment_method', $validated['payment_method']);
        }


        if (!empty($validated['payment_status'])) {

            $radiology->where('payment_status', $validated['payment_status']);
        }


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {

            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $radiology->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        return   $radiology->paginate(10);
    }


    public function billingLog($validated)
    {
        $billingLog = BillingLog::with(['serviceType', 'patient'])
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->whereHas('patient', function ($q) use ($validated) {
                    $q->where('firstname', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('patientno', 'like', '%' . $validated['search'] . '%');
                })
                    ->orWhereHas('serviceType', function ($q) use ($validated) {
                        $q->where('name', 'like', '%' . $validated['search'] . '%');
                    });
            });


        if (!empty($validated['paid_type'])) {
            $billingLog->where("payment_status", $validated['paid_type']);
        }

        if (!empty($validated['service_type'])) {
            $billingLog->whereHas('serviceType', function ($q) use ($validated) {
                $q->where('name', $validated['service_type']);
            });
        }

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $billingLog->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        return $billingLog->paginate(10);
    }


    public function billingmgt()
    {

        $data = [
            ["name" => "registration", "total" => Service::count()],
            ["name" => "pharmacy", "total" => PharmacyService::count()],
            ["name" => "laboratory", "total" => LabService::count()],
            ["name" => "Radiology", "total" => RadiologyService::count()],
            ["name" => "Consultation", "total" => Consultation_service::count()]
        ];

        return $data;
    }


    public function billingmgt_pharmacy($validated)
    {
        $med = Pharmacy::with('medication')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('type', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('pharmacy_id', 'like', '%' . $validated['search'] . '%');
                });
            });

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();
            $med->whereBetween('created_at', [$startDate, $endDate]);
        }

        $med->when(!empty($validated['search']), function ($query) use ($validated) {
            $query->orWhereHas('medication', function ($q) use ($validated) {
                $q->where('generic_name', 'like', "%{$validated['search']}%")
                    ->orWhere('brand_name', 'like', "%{$validated['search']}%")
                    ->orWhere('medicine_name', 'like', "%{$validated['search']}%")
                    ->orWhere('medicine_type', 'like', "%{$validated['search']}%");
            });
        });

        return $med->paginate(10);
    }

    public function regstration_billingmgt($validated)
    {
        // ServiceDepartment
        $service = ServiceDepartment::with('patients.visits_recent.billingLogsForPatient')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['search'] . '%')
                        ->orWhereHas('patients', function ($q2) use ($validated) {
                            $q2->where('firstname', 'like', '%' . $validated['search'] . '%')
                                ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                                ->orWhere('patientno', 'like', '%' . $validated['search'] . '%');
                        });
                });
            });

        return $service->paginate(10);
    }


    public function laboratory_billingmgt($validated)
    {
        $laboratory = Laboratory::with('patient.visits_recent.billingLogsForPatient')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('lab_dept', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('test_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('ordered_test', 'like', '%' . $validated['search'] . '%');
                });
            });

        return $laboratory->paginate(10);
    }


    public function radiology_billingmgt($validated)
    {
        $radiology = Radiology::with('patient.visits_recent.billingLogsForPatient')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->where(function ($q) use ($validated) {
                    $q->where('lab_dept', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('test_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('ordered_test', 'like', '%' . $validated['search'] . '%');
                });
            });

        return $radiology->paginate(10);
    }

    public function consultation_billingmgt($validated)
    {
        $consultation =  Consultation::with(['patient.billingLogsForPatient', 'patient.service'])
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->whereHas("patient.service", function ($q2) use ($validated) {
                    $q2->where('name', 'like', '%' . $validated['search'] . '%');
                });
            });

        return $consultation->paginate(10);
    }

    public function getStatistics(): array
    {
        $query = BillingLog::query();

        $patients = Patient::with('billingLogs')->get();
        $patient_total = 0;

        // Pharmacy
        foreach ($patients as $patient) {
            $patientvisit =   optional(PatientVisit::where('patient_id', $patient->id)->first());
            $billinglog =   optional(BillingLog::where('visit_id', $patientvisit->id)->first());
            $patient_total = $patient_total + $billinglog->grand_total;
            // foreach ($patient->billingLogs as $billingLog) {
            //     $patient_total += intval($billingLog->grand_total);
            // }
        }

        $pharmacies = Pharmacy::with('treatments')->get();
        $patientIds = [];
        $bill_total = 0;
        foreach ($pharmacies as $pharmacy) {

            $medication = optional(Medication::where('pharmacy_id',  $pharmacy->id)->first());
            $bill_total =  $bill_total + intval($medication->selling_price);
            foreach ($pharmacy->treatments as $treatment) {
                $patientIds[] = $treatment->patient_id;
            }
        }

        // $uniquePatientIds = array_unique($patientIds);
        // $pharm_patient = count($uniquePatientIds);

        $uniquePatientIds = $patientIds;
        $pharm_patient = count($uniquePatientIds);

        $laboratory =  Laboratory::all();
        $lab_amount = 0;
        foreach ($laboratory as $lab) {
            $patientvisit =  PatientVisit::where('visitno', $lab->visitno)->first();

            // $billinglog =   optional(BillingLog::where('visit_id', $patientvisit->id)->first());
            $billinglog = $patientvisit ? BillingLog::where('visit_id', $patientvisit->id)->first() : null;

            $lab_amount  += $billinglog?->grand_total ?? 0;
        }


        $radiology = Radiology::get();

        $radiology_amount = 0;
        foreach ($radiology as $radio) {
            $patientvisit =   PatientVisit::where('visitno', $radio->visitno)->first();

            // $billinglog =   optional(BillingLog::where('visit_id', $patientvisit->id)->first());
            $billinglog = $patientvisit ? BillingLog::where('visit_id', $patientvisit->id)->first() : null;

            // $radiology_amount = $radiology_amount + $billinglog->grand_total;
            $radiology_amount += $billinglog?->grand_total ?? 0;
        }

        $consultation =  Consultation::get();
        $consultation_amount = 0;
        foreach ($consultation as $consult) {
            // patient_id

            $patientvisit =   PatientVisit::where('visitno', $consult->visitno)->first();

            // $billinglog =   BillingLog::where('visit_id', $patientvisit->id)->first() ?? "";
            // $consultation_amount = $consultation_amount + $billinglog->grand_total;

            $billinglog = $patientvisit ? BillingLog::where('visit_id', $patientvisit->id)->first() : null;

            $consultation_amount += $billinglog?->grand_total ?? 0;
        }


        $services = ServiceUnit::all();
        $depart = [];
        foreach ($services as $service) {
            $count = BillingLog::where('service_unit_id', intval($service->id))->count();
            $depart[] = [
                'name' => $service->name,
                'count' => $count
            ];
        }


        return [
            'total_revenue' => (clone $query)->sum('grand_total'),
            'pending_payment' => (clone $query)->where('payment_status', 'pending')->sum('grand_total'),
            'completed_payment' => (clone $query)->where('payment_status', 'paid')->sum('grand_total'),
            'insurance_claimed' => (clone $query)->where('payment_method', 'insurance')->count(),
            "dynamic" => $depart,
            "registration" => [
                'total' => BillingLog::where('service_unit_id', 1)->count(),
                "amount" => BillingLog::where('service_unit_id', 1)->pluck('grand_total')->sum()
            ],
            'pharmacy' => [
                "total" =>  BillingLog::where('service_unit_id', 2)->count(),
                "amount" =>  BillingLog::where('service_unit_id', 2)->pluck('grand_total')->sum(),
            ],
            "laboratory" => [
                "total" => BillingLog::where('service_unit_id', 4)->count(),
                "amount" =>  BillingLog::where('service_unit_id', 4)->pluck('grand_total')->sum()
            ],
            "radiology" => [
                "total" => BillingLog::where('service_unit_id', 5)->count(),
                "amount" => BillingLog::where('service_unit_id', 5)->pluck('grand_total')->sum()
            ],
            "consultation" => [
                "total" => BillingLog::where('service_unit_id', 3)->count(),
                "amount" => BillingLog::where('service_unit_id', 3)->pluck('grand_total')->sum(),
            ],


        ];
    }
}
