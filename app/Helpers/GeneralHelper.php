<?php

namespace App\Helpers;

use App\Enums\ModuleEnums;
use App\Enums\TransactionEnums;
use App\Models\AuditLog;
use App\Models\AuditLogTransaction;
use App\Models\CustomerAccount;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GeneralHelper
{
    // Here we have all the general Helpers needed for this application

    //Get Current User Instance
    public static function userInstance()
    {
        $userInstance = Auth::user();
        return $userInstance;
    }

    //Store Audit Log
    // public static function storeAuditLog($dataToLog)
    // {
    //     if (!is_null($dataToLog)) {
    //         $auditLog = AuditLog::create([
    //             'uuid' => Str::uuid(),
    //             'causer_id' => $dataToLog['causer_id'],
    //             'action_type' => $dataToLog['action_type'],
    //             'action_module' => isset($dataToLog['action_module']) ? $dataToLog['action_module'] : ModuleEnums::GUEST->value,
    //             'action_id' => $dataToLog['action_id'],
    //             'action' => isset($dataToLog['action']) ? $dataToLog['action'] : 'Update',
    //             'log_name' => $dataToLog['log_name'],
    //             'description' => $dataToLog['description']
    //         ]);

    //         $auditLogTransaction = AuditLogTransaction::create([
    //             'uuid' => Str::uuid(),
    //             'audit_log_id' => $auditLog->id,
    //             'old_data' => isset($dataToLog['old_data']) ? json_encode($dataToLog['old_data']) : json_encode([]),
    //             'new_data' => isset($dataToLog['new_data']) ? json_encode($dataToLog['new_data']) : json_encode([]),
    //         ]);
    //     }
    // }
    public static function storeAuditLog($dataToLog)
    {
        if (!is_null($dataToLog)) {
            DB::connection('tenant')->beginTransaction();

            try {
                $auditLog = AuditLog::on('tenant')->create([
                    'uuid' => Str::uuid(),
                    'causer_id' => $dataToLog['causer_id'],
                    'action_type' => $dataToLog['action_type'],
                    'action_module' => $dataToLog['action_module'] ?? ModuleEnums::GUEST->value,
                    'action_id' => $dataToLog['action_id'],
                    'action' => $dataToLog['action'] ?? 'Update',
                    'log_name' => $dataToLog['log_name'],
                    'description' => $dataToLog['description'],
                    "module_accessed" => $dataToLog['module_accessed']
                ]);

                // Ensure the ID is available before inserting into transactions
                if ($auditLog) {
                    AuditLogTransaction::on('tenant')->create([
                        'uuid' => Str::uuid(),
                        'audit_log_id' => $auditLog->id, // Ensure this ID exists
                        'old_data' => isset($dataToLog['old_data']) ? json_encode($dataToLog['old_data']) : json_encode([]),
                        'new_data' => isset($dataToLog['new_data']) ? json_encode($dataToLog['new_data']) : json_encode([]),
                    ]);
                }

                DB::connection('tenant')->commit();
            } catch (\Exception $e) {
                DB::connection('tenant')->rollBack();
                throw $e; // Let Laravel handle the error and provide more details
            }
        }
    }

    public static function storeLandlordAuditLog($dataToLog, $guard = 'api')
    {
        if (!is_null($dataToLog)) {
            DB::connection('landlord')->beginTransaction();

            try {
                $auditLog = \App\Models\LandlordAuditLog::create([
                    'uuid' => Str::uuid(),
                    'causer_id' => $dataToLog['causer_id'] ?? Auth::guard($guard)->id(),
                    'action_type' => $dataToLog['action_type'],
                    'action_module' => $dataToLog['action_module'] ?? ModuleEnums::GUEST->value,
                    'action_id' => $dataToLog['action_id'],
                    'action' => $dataToLog['action'] ?? 'Update',
                    'log_name' => $dataToLog['log_name'],
                    'description' => $dataToLog['description'],
                    'module_accessed' => $dataToLog['module_accessed'] ?? null,
                ]);

                if ($auditLog) {
                    AuditLogTransaction::on('landlord')->create([
                        'uuid' => Str::uuid(),
                        'audit_log_id' => $auditLog->id,
                        'old_data' => isset($dataToLog['old_data']) ? json_encode($dataToLog['old_data']) : json_encode([]),
                        'new_data' => isset($dataToLog['new_data']) ? json_encode($dataToLog['new_data']) : json_encode([]),
                    ]);
                }

                DB::connection('landlord')->commit();
            } catch (\Exception $e) {
                DB::connection('landlord')->rollBack();
                throw $e;
            }
        }
    }

    public static function getModelUniqueOrderlyId($data)
    {
        $modelClass = $data['modelNamespace'];
        $modelField = $data['modelField'];
        $prefix = $data['prefix'] ?? "";
        $suffix = $data['suffix'] ?? "";
        $idLength = $data['idLength'] ?? 6;

        if (!class_exists($modelClass)) {
            return ['error' => true, 'message' => 'Model class not found'];
        }

        $record = $modelClass::latest()->first();
        $fieldId = $record->{$modelField} ?? '';

        // First ever record
        if (!$fieldId) {
            return $prefix . str_pad(1, $idLength, '0', STR_PAD_LEFT) . $suffix;
        }

        $escapedPrefix = preg_quote($prefix, '/');
        $escapedSuffix = preg_quote($suffix, '/');
        $pattern = "/^{$escapedPrefix}(\d+){$escapedSuffix}$/";

        if (preg_match($pattern, $fieldId, $matches)) {
            $currentId = (int) $matches[1];
            $incrementedId = $currentId + 1;
        } else {
            // fallback in case pattern fails
            $incrementedId = 1;
        }

        return $prefix . str_pad($incrementedId, $idLength, '0', STR_PAD_LEFT) . $suffix;
    }


    public static function getModelUniqueRandomId($data)
    {
        $modelClass = $data['modelNamespace'];
        $modelField = $data['modelField'];


        if (!class_exists($modelClass)) {
            return ['error' => true, 'message' => 'Model class not found'];
        }

        $uniqueId = self::generateUniqueRandomId($data);
        $record = $modelClass::where($modelField, $uniqueId)->first();

        if ($record) {
            return self::getModelUniqueRandomId($data);
        }

        return $uniqueId;
    }

    public static function generateUniqueRandomId($data)
    {
        $prefix = $data['prefix'] ?? "";
        $suffix = $data['suffix'] ?? "";
        $idLength = $data['idLength'] ?? 5;
        $idType = $data['idType'] ?? 'num'; //num, numalpha

        if ($idType == 'num') {
            $uniqueId = rand(0, pow(10, $idLength) - 1);
        } elseif ($idType == 'numalpha') {
            $uniqueId = strtoupper(str_random($idLength));
        } else {
            $uniqueId = str_random($idLength);
        }

        return $prefix . str_pad($uniqueId, $idLength, '0', STR_PAD_LEFT) . $suffix;
    }

    public static function dateFilter(?string $period, array $customDate = []): array|bool
    {
        if ($period === "Today") {
            $carbonDateFilter = [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === "Yesterday") {
            $carbonDateFilter = [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()];
        } elseif ($period === "This Week") {
            $carbonDateFilter = [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
        } elseif ($period === "Last Week") {
            $carbonDateFilter = [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()];
        } elseif ($period === "This Month") {
            $carbonDateFilter = [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
        } elseif ($period === "Last Month") {
            $carbonDateFilter = [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()];
        } elseif ($period === "This Year") {
            $carbonDateFilter = [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
        } elseif ($period === "Last Year") {
            $carbonDateFilter = [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear()];
        } elseif ($period === "All Time") {
            return false;
        } elseif ($period === "3 days") {
            $carbonDateFilter = [Carbon::now()->subDays(3)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === "7 days") {
            $carbonDateFilter = [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === "14 days") {
            $carbonDateFilter = [Carbon::now()->subDays(14)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === "30 days") {
            $carbonDateFilter = [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === "custom date" && !empty($customDate)) {
            $carbonDateFilter = [
                Carbon::parse($customDate[0])->startOfDay(),
                Carbon::parse($customDate[1])->endOfDay()
            ];
        } else {
            $carbonDateFilter = false;
        }

        return $carbonDateFilter;
    }
}
