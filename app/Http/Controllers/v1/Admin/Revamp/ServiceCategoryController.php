<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Responser\JsonResponser;
use App\Services\Revamp\ServiceCategoryService;
use Illuminate\Http\Request;
use Throwable;

class ServiceCategoryController extends Controller
{
    protected ServiceCategoryService $serviceCategoryService;

    public function __construct(
        ServiceCategoryService $serviceCategoryService,
    ) {
        $this->serviceCategoryService = $serviceCategoryService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->serviceCategoryService->overview($request);

            $stats = $this->serviceCategoryService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->serviceCategoryService->export($overview, $format);
            }

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'status' => 'boolean'
            ]);

            $checkCategory = ServiceCategory::where('name', $validated['name'])->where('deleted_at', null)->first();

            if ($checkCategory) {
                return JsonResponser::send(true, 'Service category with this name already exists', 'Bad Request', 400);
            }

            $category = $this->serviceCategoryService->create($validated);

            return JsonResponser::send(false, 'Service category created successfully', $category);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show($id)
    {
        try {
            $category = $this->serviceCategoryService->findById($id);

            return JsonResponser::send(false, 'Record found successfully', $category);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string' . $id,
                'status' => 'boolean'
            ]);

            $category = $this->serviceCategoryService->update($id, $validated);

            return JsonResponser::send(false, 'Service category updated successfully', $category);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $this->serviceCategoryService->delete($id);

            return JsonResponser::send(false, 'Service category deleted successfully');
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
