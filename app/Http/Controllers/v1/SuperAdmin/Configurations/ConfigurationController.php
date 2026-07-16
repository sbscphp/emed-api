<?php

namespace App\Http\Controllers\v1\SuperAdmin\Configurations;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateUsageFeeRequest;
use App\Http\Requests\SuperAdmin\UpdateUsageFeeRequest;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\Configurations\ConfigurationService;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    protected ConfigurationService $configurationService;

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    public function usageFees(Request $request)
    {
        try {
            $overview = $this->configurationService->usageFees($request);
            $stats = $this->configurationService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview,
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createUsageFee(CreateUsageFeeRequest $request)
    {
        try {
            $record = $this->configurationService->createUsageFee($request);

            return JsonResponser::send(false, 'Usage fee created successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function showUsageFee($id)
    {
        try {
            $record = $this->configurationService->showUsageFee($id);

            if (!$record) {
                return JsonResponser::send(true, 'Usage fee not found.', null, 404);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateUsageFee($id, UpdateUsageFeeRequest $request)
    {
        try {
            $record = $this->configurationService->updateUsageFee($id, $request);

            return JsonResponser::send(false, 'Usage fee updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function toggleUsageFeeStatus($id)
    {
        try {
            $record = $this->configurationService->toggleUsageFeeStatus($id);

            return JsonResponser::send(false, 'Usage fee status updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function removeUsageFee($id)
    {
        try {
            $this->configurationService->removeUsageFee($id);

            return JsonResponser::send(false, 'Usage fee removed successfully');
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
