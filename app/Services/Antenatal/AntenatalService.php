<?php

namespace App\Services\Antenatal;

use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\Antenatal;
use App\Models\AntenatalLabTest;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\DeliveryDetail;
use App\Models\Laboratory;
use App\Models\LabService;
use App\Models\NewBornDetail;
use App\Models\PatientVisit;

/**
 * Class AntenatalService
 * 
 * This class provides services related to User operations and acts as a 
 * layer between the controller and the UserRepository.
 */
class AntenatalService
{

    public function createAntenatalRecord($request)
    {
        $currentUser = UserMgtHelper::userInstance();
        $userId = $currentUser->id;
        $tenantId = $request->header('X-Tenant-ID');

        $data = $request->validated();
        $data['user_id'] = $userId;

        $antenatal = Antenatal::updateOrCreate(
            [
                'visit_id' => $data['visit_id'],
                'tenant_id' => $tenantId,
            ],
            $data
        );

        $dataToLog = [
            'causer_id'       => $userId,
            'action_id'       => $antenatal->id,
            'action'          => 'Create',
            'action_type'     => "Models\\Antenatal",
            'log_name'        => "Antenatal record created successfully",
            'description'     => "{$currentUser->firstname} {$currentUser->lastname} created a new antenatal record",
            'module_accessed' => ListModuleEnums::NURSE,
        ];

        GeneralHelper::storeAuditLog($dataToLog);

        return $antenatal;
    }

    public function createLabTest($request)
    {
        try {
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
            $visit->update([
                'status' => PatientVisitStatusEnums::INVESTIGATION->value,
            ]);

            return $labInvestigations;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createDeliveryDetails($request)
    {
        $currentUserInstance = UserMgtHelper::userInstance();
        $userId = $currentUserInstance->id;
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();

        $deliveryDetails = DeliveryDetail::updateOrCreate(
            [
                'visit_id' => $data['visit_id'],
                'tenant_id' => $tenantId,
            ],
            $data
        );

        $dataToLog = [
            'causer_id' => $userId,
            'action_id' => $deliveryDetails->id,
            'action' => 'Create',
            'action_type' => "Models\DeliveryDetail",
            'log_name' => "Delivery details created successfully",
            'description' => "{$currentUserInstance->firstname} {$currentUserInstance->lastname} created a new delivery record",
            'module_accessed' => ListModuleEnums::NURSE
        ];
        GeneralHelper::storeAuditLog($dataToLog);

        return $deliveryDetails;
    }

    public function createNewBorn($request)
    {
        $currentUserInstance = UserMgtHelper::userInstance();
        $userId = $currentUserInstance->id;
        $tenantId = $request->header('X-Tenant-ID');
        $data = $request->validated();

        $newBornDetail = DeliveryDetail::updateOrCreate(
            [
                'visit_id' => $data['visit_id'],
                'tenant_id' => $tenantId,
            ],
            $data
        );

        $dataToLog = [
            'causer_id' => $userId,
            'action_id' => $newBornDetail->id,
            'action' => 'Create',
            'action_type' => "Models\NewBornDetail",
            'log_name' => "New born details created successfully",
            'description' => "{$currentUserInstance->firstname} {$currentUserInstance->lastname} created a new born record",
            'module_accessed' => ListModuleEnums::NURSE
        ];
        GeneralHelper::storeAuditLog($dataToLog);

        return $newBornDetail;
    }
}
