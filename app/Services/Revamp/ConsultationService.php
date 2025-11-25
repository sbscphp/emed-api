<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\Consultation;
use App\Models\LabService;
use App\Models\Laboratory;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\PharmacyRequest;
use App\Models\Radiology;
use App\Models\RadiologyService;
use App\Models\ServiceUnit;
use App\Models\Surgery;
use App\Models\Treatment;
use App\Repositories\Consultation\ConsultationInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Class ConsultationService
 *
 * This class provides services related to Consultation operations and acts as a
 * layer between the Controller and the ConsultationRepository.
 */
class ConsultationService
{
    protected ConsultationInterface $ConsultationInterface;
    /**
     * Consultation constructor.
     *
     * @param ConsultationInterface $ConsultationInterface
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = PatientVisit::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('con_status')
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereRelation('patient', 'cardno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('patient', 'lastname', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['patient_status']), function ($query) use ($request) {
                $query->whereRelation('patient', 'status', $request['patient_status']);
            })
            ->when(!empty($request['service']), function ($query) use ($request) {
                $query->where('service_id', $request['service']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('arrival_date', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('arrival_date', 'DESC');
            })
            ->with(['patient', 'service', 'triage:id,visit_id,severity']);

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
        $query = PatientVisit::query()->where('tenant_id', $tenantId);

        $awaitingConsultation = (clone $query)->where('con_status', GeneralEnums::PENDING->value)->count();
        $completedConsultation = (clone $query)->where('con_status', GeneralEnums::COMPLETED->value)->count();
        $awaitingInvestigation = Laboratory::where('tenant_id', $tenantId)->where('status', GeneralEnums::NOT_READY->value)->count();
        $completedInvestigation = Laboratory::where('tenant_id', $tenantId)->where('status', GeneralEnums::READY->value)->count();
        $awaitingProcedure = Radiology::where('tenant_id', $tenantId)->where('status', GeneralEnums::NOT_READY->value)->count();
        $completedProcedure = Radiology::where('tenant_id', $tenantId)->where('status', GeneralEnums::READY->value)->count();
        $pendingSurgeries = Surgery::where('tenant_id', $tenantId)->where('status', GeneralEnums::PENDING->value)->count();
        $completedSurgeries = Surgery::where('tenant_id', $tenantId)->where('status', GeneralEnums::COMPLETED->value)->count();

        return [
            'awaitingConsultation' => $awaitingConsultation,
            'completedConsultation' => $completedConsultation,
            'awaitingInvestigation' => $awaitingInvestigation,
            'completedInvestigation' => $completedInvestigation,
            'awaitingProcedure' => $awaitingProcedure,
            'completedProcedure' => $completedProcedure,
            'pendingSurgeries' => $pendingSurgeries,
            'completedSurgeries' => $completedSurgeries,
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Firstname'      => $visit->patient->firstname ?? 'N/A',
                'Lastname'       => $visit->patient->lastname ?? 'N/A',
                'Service'        => $visit->service->name ?? 'N/A',
                'Card No'        => $visit->patient->cardno ?? 'N/A',
                'Patient No'     => $visit->patient->patientno ?? 'N/A',
                'Referral'     => $visit->patient->referral ? 'Yes' : 'No',
                'Acuity'     => $visit->triage->severity ?? 'N/A',
                'Arrival Date'   => $visit->arrival_date ?? 'N/A',
                'Patient Status' => $visit->patient->status ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patient_consultations.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = Pdf::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patient_consultations.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Create a new Consultation using the data provided.
     *
     * @param array $data
     * @return \App\Models\Consultation
     */

    public function createConsultation($request)
    {
        try {

            $currentUser = Auth::user();
            $visit = PatientVisit::find($request->visit_id);
            $patient = Patient::find($request->patient_id);
            $request['consulted_by'] = $currentUser->id;
            $tenantId = $request->header('X-Tenant-ID');
            // Initiate Patient Consultation
            $consultation = Consultation::updateOrCreate(
                [
                    'visit_id' => $request['visit_id'],
                    'tenant_id' => $tenantId
                ],
                $request->all()
            );

            // $visit->update([
            //     'status' => PatientVisitStatusEnums::CONSULTATION->value,
            // ]);

            $visit->update([
                'con_status' => GeneralEnums::COMPLETED->value,
            ]);

            $status =    $request['admit_patient'] == 1 ? PatientVisitStatusEnums::ADMITTED->value : PatientVisitStatusEnums::NOT_ADMITTED->value;
            $req_status =    $request['schedule_a_follow_up'] == true ? GeneralEnums::FOLLOWUPPATIENT->value : $patient->req_status;
            // update patient registaration staus
            $patient->update([
                'status' => $status,
                'req_status' => $req_status,
            ]);

            return $consultation;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createLabTest($request)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $visit = PatientVisit::findOrFail($request->visit_id);

            if (empty($request->test) || !is_array($request->test)) {
                throw new \Exception("No lab tests provided.");
            }

            // Fetch or create billing log
            $fetchBilling = BillingLog::firstOrCreate(
                [
                    'visit_id' => $visit->id,
                    'tenant_id' => $tenantId
                ],
                [
                    'grand_total' => 0,
                    'patient_id'  => $request->patient_id,
                ]
            );

            $labInvestigations = [];
            $totalPrice = 0;

            foreach ($request->test as $testItem) {
                $labService = LabService::find($testItem['test_id']);
                if (!$labService) {
                    throw new \Exception("Lab test with name {$testItem['test_name']} not found.");
                }

                // Skip deleting/recreating if already Ready
                $existingLab = Laboratory::where('visit_id', $visit->id)
                    ->where('test_id', $labService->id)
                    ->first();

                if ($existingLab && $existingLab->status === 'Ready') {
                    $labInvestigations[] = $existingLab;
                } else {
                    // delete old lab investigation if not Ready
                    if ($existingLab) {
                        $existingLab->delete();
                    }

                    $labInvestigation = Laboratory::create([
                        'tenant_id'        => $tenantId,
                        'visit_id'        => $visit->id,
                        'test_id'         => $labService->id,
                        'patient_id'      => $request->patient_id,
                        'consultation_id' => $request->consultation_id,
                        'test_name'       => $labService->name,
                        'department'      => $labService->class,
                    ]);

                    $labInvestigations[] = $labInvestigation;
                }

                // Do not touch already Paid items
                $labId = $existingLab?->id ?? $labInvestigation->id;

                // Do not touch already Paid items
                $existingBillingDetail = BillingLogDetail::where('billing_id', $fetchBilling->id)
                    ->where('lab_test_id', $labId)
                    ->first();

                if (!$existingBillingDetail || $existingBillingDetail->status !== 'Paid') {
                    // remove old unpaid billing detail if any
                    if ($existingBillingDetail) {
                        $fetchBilling->grand_total -= $existingBillingDetail->amount;
                        $existingBillingDetail->delete();
                    }

                    // create fresh billing detail
                    $billingDetail = BillingLogDetail::create([
                        'tenant_id'        => $tenantId,
                        'billing_id'      => $fetchBilling->id,
                        'lab_test_id'  => $labInvestigation->id,
                        'service_unit_id' => $labService->service_unit_id,
                        'item_name'       => $labService->name,
                        'quantity'        => 1,
                        'amount'          => $labService->price,
                    ]);

                    $totalPrice += $labService->price;
                }
            }

            // Update billing log with new total (only unpaid tests add up)
            $fetchBilling->grand_total += $totalPrice;
            if ($fetchBilling->grand_total < 0) {
                $fetchBilling->grand_total = 0;
            }
            $fetchBilling->save();

            // Update visit status
            // $visit->update([
            //     'status' => PatientVisitStatusEnums::INVESTIGATION->value,
            // ]);
            $visit->update([
                'lab_status' => GeneralEnums::PENDING->value,
            ]);

            return $labInvestigations;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createRadiologyTest($request)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $visit = PatientVisit::findOrFail($request->visit_id);

            if (empty($request->test) || !is_array($request->test)) {
                throw new \Exception("No lab tests provided.");
            }

            // Fetch or create billing log
            $fetchBilling = BillingLog::firstOrCreate(
                [
                    'visit_id' => $visit->id,
                    'tenant_id' => $tenantId
                ],
                [
                    'grand_total' => 0,
                    'patient_id'  => $request->patient_id,
                ]
            );

            $labInvestigations = [];
            $totalPrice = 0;

            foreach ($request->test as $testItem) {
                $radService = RadiologyService::find($testItem['test_id']);
                if (!$radService) {
                    throw new \Exception("Radiology test with name {$testItem['test_name']} not found.");
                }

                // Skip deleting/recreating if already Ready
                $existingLab = Radiology::where('visit_id', $visit->id)
                    ->where('test_id', $radService->id)
                    ->first();

                if ($existingLab && $existingLab->status === 'Ready') {
                    $labInvestigations[] = $existingLab;
                } else {
                    // delete old lab investigation if not Ready
                    if ($existingLab) {
                        $existingLab->delete();
                    }

                    $labInvestigation = Radiology::create([
                        'tenant_id'        => $tenantId,
                        'visit_id'        => $visit->id,
                        'test_id'         => $radService->id,
                        'patient_id'      => $request->patient_id,
                        'consultation_id' => $request->consultation_id,
                        'test_name'       => $radService->name,
                        'department'      => $testItem['department'] ?? $radService->class,
                    ]);

                    $labInvestigations[] = $labInvestigation;
                }

                // Do not touch already Paid items
                $labId = $existingLab?->id ?? $labInvestigation->id;

                // Do not touch already Paid items
                $existingBillingDetail = BillingLogDetail::where('billing_id', $fetchBilling->id)
                    ->where('radiology_test_id', $labId)
                    ->first();

                if (!$existingBillingDetail || $existingBillingDetail->status !== 'Paid') {
                    // remove old unpaid billing detail if any
                    if ($existingBillingDetail) {
                        $fetchBilling->grand_total -= $existingBillingDetail->amount;
                        $existingBillingDetail->delete();
                    }

                    // create fresh billing detail
                    $billingDetail = BillingLogDetail::create([
                        'tenant_id'        => $tenantId,
                        'billing_id'      => $fetchBilling->id,
                        'radiology_test_id'  => $labInvestigation->id,
                        'service_unit_id' => $radService->service_unit_id,
                        'item_name'       => $radService->name,
                        'quantity'        => 1,
                        'amount'          => $radService->price,
                    ]);

                    $totalPrice += $radService->price;
                }
            }

            // Update billing log with new total (only unpaid tests add up)
            $fetchBilling->grand_total += $totalPrice;
            if ($fetchBilling->grand_total < 0) {
                $fetchBilling->grand_total = 0;
            }
            $fetchBilling->save();

            // Update visit status
            // $visit->update([
            //     'status' => PatientVisitStatusEnums::INVESTIGATION->value,
            // ]);

            $visit->update([
                'rad_status' => GeneralEnums::PENDING->value,
            ]);

            return $labInvestigations;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createTreatment($request)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $visit = PatientVisit::findOrFail($request->visit_id);

            if (empty($request->medications) || !is_array($request->medications)) {
                throw new \Exception("No treatment medications provided.");
            }

            $serviceUnit = ServiceUnit::where('name', 'Pharmacy')->where('tenant_id', $tenantId)->first();
            if (empty($serviceUnit)) {
                throw new \Exception("Pharmacy service unit not found.");
            }

            // Fetch or create billing log
            $fetchBilling = BillingLog::firstOrCreate(
                [
                    'visit_id' => $visit->id,
                    'tenant_id' => $tenantId
                ],
                [
                    'grand_total' => 0,
                    'patient_id'  => $request->patient_id,
                ]
            );

            $drugTreatments = [];
            $totalPrice = 0;

            foreach ($request->medications as $drugItem) {
                $drug = PharmacyRequest::with('inventory')->find($drugItem['drug_id']);
                if (!$drug) {
                    throw new \Exception("Drug with name {$drugItem['drug']} not found.");
                }

                if (!$drug->inventory) {
                    throw new \Exception("No inventory record found for {$drugItem['drug']}.");
                }

                if ($drug->inventory->expiry_date && Carbon::parse($drug->inventory->expiry_date)->isPast()) {
                    throw new \Exception("Drug expired.");
                }

                if ($drug->stock_level == GeneralEnums::OUT_OF_STOCK->value) {
                    throw new \Exception("Drug is not available in stock.");
                }

                if ($request->quantity > $drug->quantity_available) {
                    throw new \Exception("Drug prescribed quantity is greater than quantity available");
                }

                $medication = $drug->inventory->medication;

                if (!$medication) {
                    throw new \Exception("Medication record not found for drug {$drugItem['drug']}.");
                }

                // Skip deleting/recreating if already Fullfilled
                $existingDrug = Treatment::where('visit_id', $visit->id)
                    ->where('drug_id', $drug->id)
                    ->first();

                if ($existingDrug && $existingDrug->status === 'Fulfilled') {
                    $drugTreatments[] = $existingDrug;
                } else {
                    if ($existingDrug) {
                        $existingDrug->delete();
                    }

                    $newTreatment = Treatment::create([
                        'tenant_id'        => $tenantId,
                        'pharmacy_id'        => $drugItem['pharmacy_id'],
                        'visit_id'        => $visit->id,
                        'drug_id'         => $drug->id,
                        'user_id'         => $currentUser->id,
                        'patient_id'      => $request->patient_id,
                        'consultation_id' => $request->consultation_id,
                        'drug'            => $drugItem['drug'] ?? $drug->product,
                        'qualifier'       => $drugItem['qualifier'] ?? null,
                        'quantity'        => $drugItem['quantity'] ?? 1,
                        'dosage'          => $drugItem['dosage'] ?? null,
                        'weight'          => $drugItem['weight'] ?? null,
                        'period'          => $drugItem['period'] ?? null,
                        'duration'        => $drugItem['duration'] ?? null,
                        'route'           => $drugItem['route'] ?? null,
                        'remark'           => $drugItem['remark'] ?? null,
                    ]);

                    $drugTreatments[] = $newTreatment;
                }

                // Do not touch already Paid items
                $drugID = $existingDrug?->id ?? $newTreatment->id;

                // Do not touch already Paid items
                $existingBillingDetail = BillingLogDetail::where('billing_id', $fetchBilling->id)
                    ->where('treatment_id', $drugID)
                    ->first();

                if (!$existingBillingDetail || $existingBillingDetail->status !== 'Paid') {
                    // remove old unpaid billing detail if any
                    if ($existingBillingDetail) {
                        $fetchBilling->grand_total -= $existingBillingDetail->amount * ($existingBillingDetail->quantity ?? 1);
                        $existingBillingDetail->delete();
                    }

                    $price = $drug->inventory->medication?->selling_price ?? 0;

                    // create fresh billing detail
                    $billingDetail = BillingLogDetail::create([
                        'tenant_id'        => $tenantId,
                        'billing_id'      => $fetchBilling->id,
                        'treatment_id'  => $newTreatment->id,
                        'service_unit_id' => $serviceUnit->id,
                        'item_name'       => $drugItem['drug'] ?? $drug->product,
                        'quantity'        => $drugItem['quantity'] ?? 1,
                        // 'amount'          => $drug->inventory->medication ? $drug->inventory->medication->selling_price : 0,
                        'amount'          => $price,
                    ]);

                    $totalPrice += $price * ($drugItem['quantity'] ?? 1);
                    // $totalPrice += $drug->inventory->medication->selling_price * ($drugItem['quantity'] ?? 1);
                }
            }

            // Update billing log with new total (only unpaid treatments add up)
            $fetchBilling->grand_total += $totalPrice;
            if ($fetchBilling->grand_total < 0) {
                $fetchBilling->grand_total = 0;
            }
            $fetchBilling->save();

            // Update visit status
            // $visit->update([
            //     'status' => PatientVisitStatusEnums::TREATMENT->value,
            // ]);

            $visit->update([
                'pharm_status' => GeneralEnums::PENDING->value,
            ]);

            return $drugTreatments;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createSurgery($request)
    {
        try {

            $currentUser = Auth::user();
            $visit = PatientVisit::find($request->visit_id);
            $request['user_id'] = $currentUser->id;
            $tenantId = $request->header('X-Tenant-ID');
            // Initiate Patient surgery
            $surgery = Surgery::updateOrCreate(
                [
                    'visit_id' => $request['visit_id'],
                    'tenant_id' => $tenantId
                ],
                $request->all()
            );

            // $visit->update([
            //     'status' => PatientVisitStatusEnums::CONSULTATION->value,
            // ]);

            return $surgery;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update an existing Consultation with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function update(array $data, $id)
    {
        return $this->ConsultationInterface->update($data, $id);
    }


    /**
     * Delete a Consultation by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->ConsultationInterface->delete($id);
    }


    /**
     * Find a Consultation by their ID.
     *
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function find($id)
    {
        return $this->ConsultationInterface->find($id);
    }


    /**
     * Find an existing Consultation  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Consultation
     */
    public function findByAttribute($attr, $value)
    {
        return $this->ConsultationInterface->findByAttribute($attr, $value);
    }

    public function getPatients($search, $sortBy, $stage, $status, $paginate, $perPage)
    {
        return $this->ConsultationInterface->getPatients($search, $sortBy, $stage, $status, $paginate, $perPage);
    }

    public function findByVisitNoLabOrBoth($visitno)
    {
        return $this->ConsultationInterface->findByVisitNoLabOrBoth($visitno);
    }

    public function findByVisitNoRadiologyOrBoth($visitno)
    {
        return $this->ConsultationInterface->findByVisitNoRadiologyOrBoth($visitno);
    }
}
