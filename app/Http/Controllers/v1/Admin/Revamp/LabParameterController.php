<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLabTestParametersRequest;
use App\Http\Requests\LabParameterRequest;
use App\Http\Requests\UpdateLabTestParameterRequest;
use App\Models\LabService;
use App\Responser\JsonResponser;
use App\Services\Revamp\LabParameterService;
use Illuminate\Http\Request;
use Throwable;

class LabParameterController extends Controller
{
    protected LabParameterService $labParameterService;

    public function __construct(LabParameterService $labParameterService)
    {
        $this->labParameterService = $labParameterService;
    }

    public function index(Request $request)
    {
        try {
            $overview = $this->labParameterService->overview($request);
            $stats = $this->labParameterService->stats($request);

            $records = [
                ...$stats,
                'data' => $overview,
            ];

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(LabParameterRequest $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $parameter = $this->labParameterService->create($request->validated(), $tenantId);

            return JsonResponser::send(false, 'Lab parameter created successfully', $parameter);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $parameter = \App\Models\LabParameter::where('tenant_id', $tenantId)
                ->with(['serviceCategory', 'labTest'])
                ->find($id);

            if (!$parameter) {
                return JsonResponser::send(true, 'Lab parameter not found.', [], 404);
            }

            return JsonResponser::send(false, 'Record found successfully', $parameter);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function update(LabParameterRequest $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $parameter = $this->labParameterService->update((int) $id, $request->validated(), $tenantId);

            return JsonResponser::send(false, 'Lab parameter updated successfully', $parameter);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $this->labParameterService->delete((int) $id, $tenantId);

            return JsonResponser::send(false, 'Lab parameter deleted successfully');
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function assignToLabTest(AssignLabTestParametersRequest $request, $labTestId)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $labTest = LabService::where('tenant_id', $tenantId)->find($labTestId);

            if (!$labTest) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            $parameters = $this->labParameterService->assignToLabTest(
                $labTest,
                $request->validated()['parameters'],
                $tenantId
            );

            return JsonResponser::send(false, 'Lab parameters assigned successfully', [
                'lab_test' => $labTest->load(['serviceCategory']),
                'parameters' => $parameters,
            ]);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function showLabTestParameters(Request $request, $labTestId)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $labTest = LabService::where('tenant_id', $tenantId)->find($labTestId);

            if (!$labTest) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            $parameters = $this->labParameterService->showLabTestParameters($labTest, $request, $tenantId);

            return JsonResponser::send(false, 'Lab test parameters found successfully', [
                'lab_test' => $labTest->load(['serviceCategory']),
                'parameters' => $parameters,
            ]);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateLabTestParameter(UpdateLabTestParameterRequest $request, $labTestId, $parameterId)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $labTest = LabService::where('tenant_id', $tenantId)->find($labTestId);

            if (!$labTest) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            $parameter = $this->labParameterService->updateLabTestParameter(
                $labTest,
                (int) $parameterId,
                $request->validated(),
                $tenantId
            );

            return JsonResponser::send(false, 'Lab test parameter updated successfully', [
                'lab_test' => $labTest->load(['serviceCategory']),
                'parameter' => $parameter,
            ]);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function destroyLabTestParameter(Request $request, $labTestId, $parameterId)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $labTest = LabService::where('tenant_id', $tenantId)->find($labTestId);

            if (!$labTest) {
                return JsonResponser::send(true, 'Lab test not found.', [], 404);
            }

            $this->labParameterService->deleteLabTestParameter($labTest, (int) $parameterId, $tenantId);

            return JsonResponser::send(false, 'Lab test parameter deleted successfully');
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
