<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditResources extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $causer = optional($this->causer);
        $audit_log_transactions = optional($this->audit_log_transactions);
        return [
            "uuid" => $this->uuid,
            "action_type" => $this->action_type,
            "action_module" => $this->action_module,
            "log_name" => $this->log_name,
            "description" => $this->description,
            "user_uuid" => $causer->uuid,
            "user_fullname" => $causer->fullname,
            "user_email" => $causer->email,
            "user_phone_number" => $causer->phone_number,
            "user_role" => $causer->role,
            "user_status" => $causer->status,
            "audit_log_transactions_uuid" => $audit_log_transactions->uuid,
            //"audit_log_transactions_uuid"
        ];
    }
}
