<?php

namespace App\Services\HivAids;

use App\Enums\GeneralEnums;
use App\Models\CounsellingDetail;
use App\Models\ObservationRecommendation;
use App\Models\PatientVisit;
use Illuminate\Support\Facades\Auth;

/**
 * Class HivAidsService
 * 
 * This class provides services related to User operations and acts as a 
 * layer between the controller and the UserRepository.
 */
class HivAidsService
{
    public function createCouncellingDetails($request)
    {
        $currentUser = Auth::user();
        $tenantId = $request->header('X-Tenant-ID');
        $validated = $request->validated(); // ✅ Get validated data as array

        $data = CounsellingDetail::updateOrCreate(
            [
                'visit_id' => $validated['visit_id'],
                'tenant_id' => $tenantId,
            ], // Unique key
            $validated // Data to update/create
        );

        $visit = PatientVisit::find($data['visit_id']);
        $visit->update([
            'counsel_status' => GeneralEnums::COMPLETED->value,
        ]);

        return $data;
    }

    public function createObservation($request)
    {
        $currentUser = Auth::user();
        $tenantId = $request->header('X-Tenant-ID');
        $validated = $request->validated(); // ✅ Get validated data as array
        $data = ObservationRecommendation::updateOrCreate(
            [
                'visit_id' => $validated['visit_id'],
                'tenant_id' => $tenantId,
            ], // Unique key
            $validated // Data to update/create
        );

        return $data;
    }
}
