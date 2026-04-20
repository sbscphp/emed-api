<?php

namespace App\Http\Controllers\v1\SuperAdmin\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateSubscriptionRequest;
use App\Http\Requests\SuperAdmin\UpdateSubscriptionRequest;
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
            $records = $this->subscriptionService->overview($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function create(CreateSubscriptionRequest $request)
    {
        try {
            $record = $this->subscriptionService->create($request);

            return JsonResponser::send(false, 'Subscription created successfully', $record, 201);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to create subscription', 400);
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

    public function update($id, UpdateSubscriptionRequest $request)
    {
        try {
            $record = $this->subscriptionService->update($id, $request);

            return JsonResponser::send(false, 'Subscription updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to update subscription', 400);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $record = $this->subscriptionService->toggleStatus($id);

            return JsonResponser::send(false, 'Subscription status toggled successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to toggle subscription status', 400);
        }
    }

    public function remove($id)
    {
        try {
            $this->subscriptionService->delete($id);

            return JsonResponser::send(false, 'Subscription deleted successfully', null, 204);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Failed to delete subscription', 400);
        }
    }
}
