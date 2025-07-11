<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResourceExport extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'dob' => $this->dob,
            'age' => $this->age,
            'gender' => $this->gender,
            'bloodgroup' => $this->bloodgroup,
            'genotype' => $this->genotype,
            'email' => $this->email,
            'patient_type' => $this->patient_type,
            'marital_status' => $this->marital_status,
            'phoneno' => $this->phoneno,
            'visitno' => $this->visitno,
            'occupation' => $this->occupation,
            'homeaddress' => $this->homeaddress,
            'companyaddress' => $this->companyaddress,
            'religion' => $this->companyaddress,
            'stateoforigin' => $this->stateoforigin,
            'lga' => $this->lga,
            'tribe' => $this->tribe,
            'cardno' => $this->cardno,
            'receiptno' => $this->receiptno,
            'status' => $this->status,
            'service_id' => $this->service_id,
            'arrival_time' => $this->arrival_time,
            'depature_time' => $this->depature_time,
            'patientno' => $this->patientno
        ];
    }
}
