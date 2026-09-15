<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RateCardItemRequest;
use App\Responser\JsonResponser;
use App\Services\RateCard\RateCardService;
use Illuminate\Http\Request;

class RateCardController extends Controller
{
    public function __construct(private RateCardService $rateCardService) {}

    public function index(Request $request)
    {
        try {
            $items = $this->rateCardService->all($request);

            return JsonResponser::send(false, 'Rate card item(s) found successfully', $items);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while fetching rate card items.', [], 500, $th);
        }
    }

    public function store(RateCardItemRequest $request)
    {
        try {
            $item = $this->rateCardService->create($request->validated());

            return JsonResponser::send(false, 'Rate card item created successfully', $item, 201);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while creating the rate card item.', [], 500, $th);
        }
    }

    public function show($id)
    {
        try {
            $item = $this->rateCardService->find($id);

            return JsonResponser::send(false, 'Rate card item found successfully', $item);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Rate card item not found.', [], 404, $th);
        }
    }

    public function update(RateCardItemRequest $request, $id)
    {
        try {
            $item = $this->rateCardService->update($id, $request->validated());

            return JsonResponser::send(false, 'Rate card item updated successfully', $item);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while updating the rate card item.', [], 500, $th);
        }
    }

    public function destroy($id)
    {
        try {
            $this->rateCardService->delete($id);

            return JsonResponser::send(false, 'Rate card item deleted successfully', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'An error occurred while deleting the rate card item.', [], 500, $th);
        }
    }
}
