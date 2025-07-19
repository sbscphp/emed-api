<?php

namespace App\Services\BillingLog;

use App\Enums\PatientVisitStageEnums;
use App\Models\BillingLog;
use App\Models\Consultation;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\Medicine_Log;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Pharmacy;
use App\Models\Radiology;
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

    public function regstration_list($validated)
    {
        $patient =  Patient::with('visits_recent.billingLogsForPatient');

        $patient->when(!empty($validated['search']), function ($query) use ($validated) {
            $query->where('firstname', $validated['search'])
                ->orWhere('lastname', $validated['search'])
                ->orWhere('patientno', $validated['search']);
        });

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $patient->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        return $patient->paginate(10);
    }

    public function pharmacy_list($validated)
    {
        $pharm =  Pharmacy::with(["pharmacist", 'treatments_one.patient.visits_recent.billingLogsForPatient']);

        // $pharm->when(!empty($validated['search']), function ($query) use ($validated) {
        //     // $query->where('firstname', $validated['search'])
        //     //     ->orWhere('lastname', $validated['search'])
        //     //     ->orWhere('patientno', $validated['search']);
        //     $query->whereHas('patient', function($q) use(){});
        // });

        $pharm->when(!empty($validated['search']), function ($query) use ($validated) {
            $query->whereHas('treatments_one.patient', function ($q) use ($validated) {
                $q->where('firstname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                    ->orwhere('patientno', 'like', '%' . $validated['search'] . '%');
            })
                ->orWhereHas('pharmacist', function ($q) use ($validated) {
                    $q->where('first_name', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('last_name', 'like', '%' . $validated['search'] . '%');
                })

                ->orWhereHas('treatments_one.patient.visits_recent.billingLogsForPatient', function ($q) use ($validated) {
                    $q->where('payment_status', 'like', '%' . $validated['search'] . '%');
                });
        });

        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $pharm->whereHas('treatments_one.patient.visits_recent.billingLogsForPatient', function ($q) use ($validated) {
                $startDate = $validated['start_date'];
                $endDate = $validated['end_date'];
                $q->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            });
        }

        return  $pharm->paginate();
    }


    public function consultation_list($validated)
    {
        $consultation = Consultation::with('patient.visits_recent.billingLogsForPatient')
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $query->whereHas('patient', function ($q) use ($validated) {
                    $q->where('firstname', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                        ->orWhere('patientno', 'like', '%' . $validated['search'] . '%')
                        ->orWhereHas('visits_recent', function ($q) use ($validated) {
                            $q->whereHas('billingLogsForPatient', function ($q) use ($validated) {
                                $q->where('payment_status', 'like', '%' . $validated['search'] . '%');
                            });
                        });
                });
            });


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $consultation->whereHas('patient.visits_recent.billingLogsForPatient', function ($q) use ($validated) {
                $startDate = $validated['start_date'];
                $endDate = $validated['end_date'];
                $q->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            });
        }

        return   $consultation->paginate();
    }

    public function laboratory_list($validated)
    {
        $laboratory = Laboratory::with('patient.visits_recent.billingLogsForPatient');


        $laboratory->when(!empty($validated['search']), function ($query) use ($validated) {
            $query->whereHas('patient', function ($q) use ($validated) {
                $q->where('firstname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('patientno', 'like', '%' . $validated['search'] . '%')
                    ->orWhereHas('visits_recent', function ($q) use ($validated) {
                        $q->whereHas('billingLogsForPatient', function ($q) use ($validated) {
                            $q->where('payment_status', 'like', '%' . $validated['search'] . '%');
                        });
                    });
            });
        });


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $laboratory->whereHas('patient.visits_recent.billingLogsForPatient', function ($q) use ($validated) {
                $startDate = $validated['start_date'];
                $endDate = $validated['end_date'];
                $q->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            });
        }

        return   $laboratory->paginate();
    }

    public function radiology_list($validated)
    {
        $radiology =  Radiology::with('patient.visits_recent.billingLogsForPatient');

        $radiology->when(!empty($validated['search']), function ($query) use ($validated) {
            $query->whereHas('patient', function ($q) use ($validated) {
                $q->where('firstname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('lastname', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('patientno', 'like', '%' . $validated['search'] . '%')
                    ->orWhereHas('visits_recent', function ($q) use ($validated) {
                        $q->whereHas('billingLogsForPatient', function ($q) use ($validated) {
                            $q->where('payment_status', 'like', '%' . $validated['search'] . '%');
                        });
                    });
            });
        });


        if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
            $radiology->whereHas('patient.visits_recent.billingLogsForPatient', function ($q) use ($validated) {
                $startDate = $validated['start_date'];
                $endDate = $validated['end_date'];
                $q->where('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
            });
        }

        return   $radiology->paginate();
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

        $pharmacies = Pharmacy::with('treatments.consultations')->get();
        $patientIds = [];
        $bill_total = 0;
        foreach ($pharmacies as $pharmacy) {

            $medication = optional(Medication::where('pharmacy_id',  $pharmacy->id)->first());
            $bill_total =  $bill_total + intval($medication->selling_price);
            foreach ($pharmacy->treatments as $treatment) {
                $patientIds[] = $treatment->patient_id;

                foreach ($treatment->consultations as $consultation) {
                    //    $consultation->patient_id;
                    //    $consultation->visitno;
                    // $patientvisit =   optional(PatientVisit::where('visitno', $consultation->visitno)->first());

                    // $billinglog =   optional(BillingLog::where('visit_id', $patientvisit->id)->first());

                    // $bill_total = $bill_total + $billinglog->grand_total;
                }
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


        return [
            'total_revenue' => (clone $query)->sum('grand_total'),
            'pending_payment' => (clone $query)->where('payment_status', 'pending')->sum('grand_total'),
            'completed_payment' => (clone $query)->where('payment_status', 'paid')->sum('grand_total'),
            'insurance_claimed' => (clone $query)->where('payment_method', 'insurance')->count(),
            "registration" => [
                'total' => Patient::count(),
                "amount" => $patient_total
            ],
            'pharmacy' => [
                "total" => Pharmacy::count(),
                "amount" => $bill_total,
            ],
            "laboratory" => [
                "total" => Laboratory::count(),
                "amount" => $lab_amount
            ],
            "radiology" => [
                "total" => Radiology::count(),
                "amount" => $radiology_amount
            ],
            "consultation" => [
                "total" => Consultation::count(),
                "amount" => $consultation_amount,
            ],


        ];
    }
}
