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
        "id"=>$this->id,
        "firstname"=>$this->firstname,
        "lastname"=>$this->lastname,
        "middlename"=>$this->middlename,
        "dob"=>$this->dob,
        "age"=>$this->age,
        "gender"=>$this->gender,
        "bloodgroup"=>$this->bloodgroup,
        "genotype"=>$this->genotype,
        "email"=>$this->email,
        "patient_type"=>$this->patient_type,
        "marital_status"=>$this->marital_status,
        "phoneno"=>$this->phoneno,
        "occupation"=>$this->occupation,
        "homeaddress"=>$this->homeaddress,
        "companyaddress"=>$this->companyaddress,
        "religion"=>$this->religion,
        "stateoforigin"=>$this->stateoforigin,
        "lga"=>$this->lga,
        "tribe"=>$this->tribe,
        "cardno"=>$this->cardno,
        "recieptno"=>$this->recieptno,
        "status"=>$this->status,
        "reg_status"=>$this->reg_status,
        "image"=>$this->image,
        "patientno"=>$this->patientno,
        "service_id"=>$this->service_id,
        "follow_up"=>$this->follow_up,
        "deleted_at"=>$this->deleted_at,
        "created_at"=>$this ->created_at,
        "updated_at"=>$this->updated_at,
        "next_of_kin_id"=> $next_of_kin?->id,
        "next_of_kin_patient_id"=>$next_of_kin?->patient_id,
        "next_of_kin_firstname"=>$next_of_kin?->firstname,
        "next_of_kin_lastname"=>$next_of_kin?->lastname,
        "next_of_kin_gender"=>$next_of_kin?->gender,
        "next_of_kin_phoneno"=>$next_of_kin?->phoneno,
        "next_of_kin_stateoforigin"=>$next_of_kin?->stateoforigin,
        "next_of_kin_lga"=>$next_of_kin?->lga,
        "next_of_kin_homeaddress"=>$next_of_kin?->homeaddress,
        "next_of_kin_relationship"=>$next_of_kin?->relationship,
        "service_id"=>$service?->id,
        "service_name"=>$service?->name,
            "emergency_contact_id"=>$emergency_contact?->id,
            "emergency_contact_patient_id"=>$emergency_contact->patient_id,
            "emergency_contact_firstname"=>$emergency_contact?->firstname,
            "emergency_contact_lastname"=>$emergency_contact?->lastname,
            "emergency_contact_gender"=>$emergency_contact?->gender,
            "emergency_contact_phoneno"=>$emergency_contact?->phoneno,
            "emergency_contact_stateoforigin"=>$emergency_contact?->stateoforigin,
            "emergency_contact_lga"=>$emergency_contact?->lga,
            "emergency_contact_homeaddress"=>$emergency_contact?->homeaddress,
            "emergency_contact_relationship"=>$emergency_contact?->relationship,
            "emergency_contact_created_at"=>$emergency_contact?->created_at,
            "emergency_contact_updated_at"=>$emergency_contact?->updated_at,
            "emergency_contact_deleted_at"=>$emergency_contact?->deleted_at,
            "visits"=>$this->visits
        ];
    }
}
