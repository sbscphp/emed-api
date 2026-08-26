<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WardBedRequest;
use App\Responser\JsonResponser;
use App\Services\Revamp\WardBedService;
use Illuminate\Http\Request;
use Throwable;

class WardBedController extends Controller
{
    protected WardBedService $wardBedService;

    public function __construct(WardBedService $wardBedService)
    {
        $this->wardBedService = $wardBedService;
    }

    public function index(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $overview = $this->wardBedService->overview($request);
            $stats = $this->wardBedService->stats($request);

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

    public function store(WardBedRequest $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            if (!$tenantId) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $ward = $this->wardBedService->create($request->validated(), $tenantId);

            return JsonResponser::send(false, 'Ward and bed space created successfully', $ward);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            if (!$tenantId) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $ward = $this->wardBedService->find((int) $id, $tenantId);

            if (!$ward) {
                return JsonResponser::send(true, 'Ward not found.', [], 404);
            }

            return JsonResponser::send(false, 'Record found successfully', $ward);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function update(WardBedRequest $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            if (!$tenantId) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $ward = $this->wardBedService->update((int) $id, $request->validated(), $tenantId);

            if (!$ward) {
                return JsonResponser::send(true, 'Ward not found.', [], 404);
            }

            return JsonResponser::send(false, 'Ward and bed space updated successfully', $ward);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            if (!$tenantId) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $deleted = $this->wardBedService->delete((int) $id, $tenantId);

            if (!$deleted) {
                return JsonResponser::send(true, 'Ward not found.', [], 404);
            }

            return JsonResponser::send(false, 'Ward and bed space deleted successfully');
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
