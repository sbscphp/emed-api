<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientDetailResoures extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       $next_of_kin = $this->next_of_kin;
       $service = $this->service;
       $emergency_contact = $this->emergency_contact;
        return [
        "id"=>$this->id??null,
        "firstname"=>$this->firstname??null,
        "lastname"=>$this->lastname??null,
        "middlename"=>$this->middlename??null,
        "dob"=>$this->dob??null,
        "age"=>$this->age??null,
        "gender"=>$this->gender??null,
        "bloodgroup"=>$this->bloodgroup??null,
        "genotype"=>$this->genotype??null,
        "email"=>$this->email??null,
        "patient_type"=>$this->patient_type??null,
        "marital_status"=>$this->marital_status??null,
        "phoneno"=>$this->phoneno??null,
        "occupation"=>$this->occupation??null,
        "homeaddress"=>$this->homeaddress??null,
        "companyaddress"=>$this->companyaddress??null,
        "religion"=>$this->religion??null,
        "stateoforigin"=>$this->stateoforigin??null,
        "lga"=>$this->lga??null,
        "tribe"=>$this->tribe??null,
        "cardno"=>$this->cardno??null,
        "recieptno"=>$this->recieptno??null,
        "status"=>$this->status??null,
        "reg_status"=>$this->reg_status??null,
        "image"=>$this->image??null,
        "patientno"=>$this->patientno??null,
        "service_id"=>$this->service_id??null,
        "follow_up"=>$this->follow_up??null,
        "deleted_at"=>$this->deleted_at??null,
        "created_at"=>$this ->created_at??null,
        "updated_at"=>$this->updated_at??null,
        "next_of_kin_id"=> $next_of_kin?->id??null,
        "next_of_kin_patient_id"=>$next_of_kin?->patient_id??null,
        "next_of_kin_firstname"=>$next_of_kin?->firstname??null,
        "next_of_kin_lastname"=>$next_of_kin?->lastname??null,
        "next_of_kin_gender"=>$next_of_kin?->gender??null,
        "next_of_kin_phoneno"=>$next_of_kin?->phoneno??null,
        "next_of_kin_stateoforigin"=>$next_of_kin?->stateoforigin??null,
        "next_of_kin_lga"=>$next_of_kin?->lga??null,
        "next_of_kin_homeaddress"=>$next_of_kin?->homeaddress??null,
        "next_of_kin_relationship"=>$next_of_kin?->relationship??null,
        "service_id"=>$service?->id??null,
        "service_name"=>$service?->name??null,
            "emergency_contact_id"=>$emergency_contact?->id??null,
            "emergency_contact_patient_id"=>$emergency_contact->patient_id??null,
            "emergency_contact_firstname"=>$emergency_contact?->firstname??null,
            "emergency_contact_lastname"=>$emergency_contact?->lastname??null,
            "emergency_contact_gender"=>$emergency_contact?->gender??null,
            "emergency_contact_phoneno"=>$emergency_contact?->phoneno??null,
            "emergency_contact_stateoforigin"=>$emergency_contact?->stateoforigin??null,
            "emergency_contact_lga"=>$emergency_contact?->lga??null,
            "emergency_contact_homeaddress"=>$emergency_contact?->homeaddress??null,
            "emergency_contact_relationship"=>$emergency_contact?->relationship??null,
            "emergency_contact_created_at"=>$emergency_contact?->created_at??null,
            "emergency_contact_updated_at"=>$emergency_contact?->updated_at??null,
            "emergency_contact_deleted_at"=>$emergency_contact?->deleted_at??null,
            "visits"=>$this->visits??null
            
        ];
    }
}
