<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\SyncDepartmentDoctorsRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Responser\JsonResponser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Services\Revamp\DepartmentService;
use Throwable;

class DepartmentController extends Controller
{
    protected DepartmentService $departmentService;

    public function __construct(
        DepartmentService $departmentService,
    ) {
        $this->departmentService = $departmentService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->departmentService->overview($request);

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->departmentService->export($overview, $format);
            }

            $stats = $this->departmentService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function store(StoreDepartmentRequest $request)
    {
        try {
            $record = $this->departmentService->store($request);

            return JsonResponser::send(false, 'Record created successfully.', $record, 201);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $record = $this->departmentService->show($id, $request);

            return JsonResponser::send(false, 'Record(s) found successfully.', $record, 200);
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Department not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function update(UpdateDepartmentRequest $request, $id)
    {
        try {
            $record = $this->departmentService->update($request, $id);

            return JsonResponser::send(false, 'Record updated successfully.', $record, 200);
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Department not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $record = $this->departmentService->toggleStatus($id, $request);

            return JsonResponser::send(false, 'Record status toggled successfully.', $record, 200);
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Department not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $record = $this->departmentService->destroy($id, $request);

            return JsonResponser::send(false, 'Record deleted successfully.', $record, 200);
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Department not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    /**
     * The doctors consulting in a department, plus the consultants that could be
     * added to it.
     *
     * The patient app's booking flow reads the assigned list to fill in "Select
     * your preferred doctor" once the patient has chosen a department.
     */
    public function doctors(Request $request, $id)
    {
        try {
            $record = $this->departmentService->doctors($id, $request);

            return JsonResponser::send(false, 'Record(s) found successfully.', $record, 200);
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Department not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    /**
     * Replace the doctors consulting in a department with the list posted.
     */
    public function syncDoctors(SyncDepartmentDoctorsRequest $request, $id)
    {
        try {
            $this->departmentService->syncDoctors($id, $request->validated()['doctor_ids'], $request);

            return JsonResponser::send(
                false,
                'Doctors assigned successfully.',
                $this->departmentService->doctors($id, $request),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Department not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }
}
