<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientVistConsultationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "data" => $this->arrival_date,
            "Visit Number" => $this->visitno,
            "Service Type" => $this->billingLogsForPatient?->serviceType?->name ?? "",
            "Payment Status" => $this->billingLogsForPatient?->payment_status ?? "",
            "Payment Type" => $this->billingLogsForPatient?->payment_method ?? "",
        ];
    }
}
