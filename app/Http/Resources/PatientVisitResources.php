<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVisitResources extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $patient =  $this->patient;
         $serviceId = $this->patient->service_id ?? null;
        $billingLog = $this->billingLogs()
                    ->where('service_type_id', $serviceId)
                    ->first();
        return [
                    'id'             => $this->id,
                    'patient_id'     => $patient?->id,
                    'fullname'       => "{$patient?->firstname} {$patient?->lastname}",
                    'date'           => $this->created_at->format('Y-m-d'),
                    'visit_number'   => $this->visitno,
                    'service_type'   => $patient?->service?->name ?? 'Nil',
                    'referral'       => 'Nil',
                    'payment_status' => $billingLog->payment_status ?? 'pending',
                     'payment_method' => $billingLog->payment_method ?? 'pending',
        ];

    }
}
