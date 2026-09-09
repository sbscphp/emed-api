<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\BillingServiceResource;
use App\Http\Resources\ServiceResource;
use App\Responser\JsonResponser;
use App\Services\Revamp\ServiceCatalogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Throwable;

/**
 * The services and pricing catalogue: the hospital's own services, each of
 * which owns the priced sub-services handled by BillingServiceController.
 */
class ServiceController extends Controller
{
    protected ServiceCatalogService $serviceCatalogService;

    public function __construct(
        ServiceCatalogService $serviceCatalogService,
    ) {
        $this->serviceCatalogService = $serviceCatalogService;
    }

    public function index(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $overview = $this->serviceCatalogService->overview($request);

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->serviceCatalogService->export($overview, $format);
            }

            $stats = $this->serviceCatalogService->stats($request);

            $records = [
                ...$stats,
                'data' => $request->paginate
                    ? ServiceResource::collection($overview)->response()->getData(true)
                    : ServiceResource::collection($overview),
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function store(StoreServiceRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $record = $this->serviceCatalogService->store($request);

            return JsonResponser::send(
                false,
                'Record created successfully.',
                new ServiceResource($record),
                201
            );
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $record = $this->serviceCatalogService->show($id, $request);

            return JsonResponser::send(
                false,
                'Record(s) found successfully.',
                new ServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    /**
     * The sub-services listed on the service details screen.
     */
    public function subServices(Request $request, $id)
    {
        try {
            $service = $this->serviceCatalogService->show($id, $request);
            $subServices = $this->serviceCatalogService->subServices($id, $request);

            $records = [
                'service' => new ServiceResource($service),
                'data' => $request->paginate
                    ? BillingServiceResource::collection($subServices)->response()->getData(true)
                    : BillingServiceResource::collection($subServices),
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function update(UpdateServiceRequest $request, $id)
    {
        try {
            $record = $this->serviceCatalogService->update($request, $id);

            return JsonResponser::send(
                false,
                'Record updated successfully.',
                new ServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $record = $this->serviceCatalogService->toggleStatus($id, $request);

            return JsonResponser::send(
                false,
                'Record updated successfully.',
                new ServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $record = $this->serviceCatalogService->destroy($id, $request);

            return JsonResponser::send(
                false,
                'Record deleted successfully.',
                new ServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }
}
