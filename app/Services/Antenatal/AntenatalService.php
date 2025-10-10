<?php

namespace App\Services\Antenatal;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Helpers\UserMgtHelper;
use App\Models\Antenatal;
use App\Models\AntenatalLabTest;
use App\Models\DeliveryDetail;
use App\Models\NewBornDetail;

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

    public function createAntenatalLabTest(array $request)
    {
        $currentUserInstance = UserMgtHelper::userInstance();
        $userId = $currentUserInstance->id;
        $data = $request;

        $antenatalLabTest = AntenatalLabTest::updateOrCreate(
            ['visit_id' => $data['visit_id']], // Unique key
            $data // Data to update/create
        );

        $dataToLog = [
            'causer_id' => $userId,
            'action_id' => $antenatalLabTest->id,
            'action' => 'Create',
            'action_type' => "Models\AntenatalLabTest",
            'log_name' => "Antenatal lab test created successfully",
            'description' => "{$currentUserInstance->firstname} {$currentUserInstance->lastname} created a new antenatal lab test record",
            'module_accessed' => ListModuleEnums::NURSE
        ];
        GeneralHelper::storeAuditLog($dataToLog);

        return $antenatalLabTest;
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
