<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBillingServiceRequest;
use App\Http\Requests\UpdateBillingServiceRequest;
use App\Http\Resources\BillingServiceResource;
use App\Responser\JsonResponser;
use App\Services\Revamp\BillingChargeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Throwable;

class BillingServiceController extends Controller
{
    protected BillingChargeService $billingChargeService;

    public function __construct(
        BillingChargeService $billingChargeService,
    ) {
        $this->billingChargeService = $billingChargeService;
    }

    public function index(Request $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $overview = $this->billingChargeService->overview($request);

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->billingChargeService->export($overview, $format);
            }

            $stats = $this->billingChargeService->stats($request);

            $records = [
                ...$stats,
                'data' => $request->paginate
                    ? BillingServiceResource::collection($overview)->response()->getData(true)
                    : BillingServiceResource::collection($overview),
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function store(StoreBillingServiceRequest $request)
    {
        try {
            if (!$request->header('X-Tenant-ID')) {
                return JsonResponser::send(true, 'X-Tenant-ID header is required.', [], 422);
            }

            $record = $this->billingChargeService->store($request);

            return JsonResponser::send(
                false,
                'Record created successfully.',
                new BillingServiceResource($record),
                201
            );
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $record = $this->billingChargeService->show($id, $request);

            return JsonResponser::send(
                false,
                'Record(s) found successfully.',
                new BillingServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Billing service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function update(UpdateBillingServiceRequest $request, $id)
    {
        try {
            $record = $this->billingChargeService->update($request, $id);

            return JsonResponser::send(
                false,
                'Record updated successfully.',
                new BillingServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Billing service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $record = $this->billingChargeService->destroy($id, $request);

            return JsonResponser::send(
                false,
                'Record deleted successfully.',
                new BillingServiceResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Billing service not found.', [], 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }
}
