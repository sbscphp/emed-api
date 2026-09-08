<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentDetailResource;
use App\Http\Resources\AppointmentResource;
use App\Responser\JsonResponser;
use App\Services\Revamp\AppointmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Throwable;

class AppointmentController extends Controller
{
    protected AppointmentService $appointmentService;

    public function __construct(
        AppointmentService $appointmentService,
    ) {
        $this->appointmentService = $appointmentService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->appointmentService->overview($request);

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->appointmentService->export($overview, $format);
            }

            $stats = $this->appointmentService->stats($request);
            $records = [
                ...$stats,
                'data' => AppointmentResource::collection($overview)->response()->getData(true),
            ];

            if (!$request->paginate) {
                $records = [
                    ...$stats,
                    'data' => AppointmentResource::collection($overview),
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    public function store(StoreAppointmentRequest $request)
    {
        try {
            $record = $this->appointmentService->store($request);

            return JsonResponser::send(
                false,
                'Record created successfully.',
                new AppointmentDetailResource($record),
                201
            );
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $record = $this->appointmentService->show($id, $request);

            return JsonResponser::send(
                false,
                'Record(s) found successfully.',
                new AppointmentDetailResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Appointment not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function update(UpdateAppointmentRequest $request, $id)
    {
        try {
            $record = $this->appointmentService->update($request, $id);

            return JsonResponser::send(
                false,
                'Record updated successfully.',
                new AppointmentDetailResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Appointment not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $record = $this->appointmentService->destroy($id, $request);

            return JsonResponser::send(
                false,
                'Record deleted successfully.',
                new AppointmentResource($record),
                200
            );
        } catch (ModelNotFoundException $th) {
            return JsonResponser::send(true, 'Appointment not found.', 'Not Found', 404);
        } catch (Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }
}
