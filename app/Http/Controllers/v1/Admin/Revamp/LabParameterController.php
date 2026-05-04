<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabParameterRequest;
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
                ->with('serviceCategory')
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
}
