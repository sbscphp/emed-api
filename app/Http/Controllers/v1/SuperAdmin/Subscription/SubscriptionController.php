<?php

namespace App\Http\Controllers\v1\SuperAdmin\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AssignClientUsageFeeRequest;
use App\Http\Requests\SuperAdmin\SubscriptionPlanRequest;
use App\Http\Requests\SuperAdmin\UpdateSubscriptionPlanRequest;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\Subscription\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function index(Request $request)
    {
        try {
            $overview = $this->subscriptionService->overview($request);
            $plans = $this->subscriptionService->getPlans($request);
            $stats = $this->subscriptionService->stats($request);

            $records = [
                ...$stats,
                'data' => $overview,
                'plans' => $plans,
            ];

            if ($request['export']) {
                return $this->subscriptionService->export($overview, $request['export']);
            }

            if (isset($request['paginate']) && !filter_var($request['paginate'], FILTER_VALIDATE_BOOLEAN)) {
                $records = [
                    ...$stats,
                    'data' => $overview,
                    'plans' => $plans,
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function plans(Request $request)
    {
        try {
            $overview = $this->subscriptionService->getPlans($request);
            $stats = $this->subscriptionService->planStats($request);

            $records = [
                ...$stats,
                'data' => $overview,
            ];

            if ($request['export']) {
                return $this->subscriptionService->planExport($overview, $request['export']);
            }

            if (isset($request['paginate']) && !filter_var($request['paginate'], FILTER_VALIDATE_BOOLEAN)) {
                $records = [
                    ...$stats,
                    'data' => $overview,
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function create(SubscriptionPlanRequest $request)
    {
        try {
            $record = $this->subscriptionService->create($request);

            return JsonResponser::send(false, 'Subscription plan created successfully', $record, 201);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to create subscription plan', 400);
        }
    }

    public function assignClientUsageFee(AssignClientUsageFeeRequest $request)
    {
        try {
            $record = $this->subscriptionService->assignClientUsageFee($request);

            return JsonResponser::send(false, 'Usage fee assigned to hospital subscription successfully', $record, 200);
        } catch (\Throwable $th) {
            report($th);
            return JsonResponser::send(true, 'Failed to assign usage fee', $th->getMessage(), 400);
        }
    }

    public function show($id)
    {
        try {
            $record = $this->subscriptionService->show($id);

            return JsonResponser::send(false, 'Subscription found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Subscription not found', 404);
        }
    }

    public function showPlan($id)
    {
        try {
            $record = $this->subscriptionService->showPlan($id);

            return JsonResponser::send(false, 'Subscription plan found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Subscription plan not found', 404);
        }
    }

    public function update($id, UpdateSubscriptionPlanRequest $request)
    {
        try {
            $record = $this->subscriptionService->update($id, $request);

            return JsonResponser::send(false, 'Subscription plan updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to update subscription plan', 400);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $record = $this->subscriptionService->toggleStatus($id);

            return JsonResponser::send(false, 'Subscription plan status toggled successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to toggle subscription plan status', 400);
        }
    }

    public function remove($id)
    {
        try {
            $this->subscriptionService->delete($id);

            return JsonResponser::send(false, 'Subscription plan deleted successfully', null, 204);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to delete subscription plan', 400);
        }
    }
}
