<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Admission\AdmissionIndexRequest;
use App\Http\Resources\Patient\AdmissionDetailResource;
use App\Http\Resources\Patient\AdmissionResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Admission\PatientAdmissionService;
use Throwable;

/**
 * The admissions module of the patient mobile app.
 *
 * Read only — a stay is opened, moved and closed off by the ward. The patient
 * reads their own history of it, and the one they are on right now if there is
 * one.
 */
class AdmissionController extends Controller
{
    public function __construct(protected PatientAdmissionService $admissionService) {}

    /**
     * GET /v1/patient/admissions
     *
     * "Previous Admissions", filtered by `status`, `search_param`, a single
     * `date`, or a range given as a `period` or a `start_date`–`end_date` pair.
     *
     * The stay in progress rides along, because the screen opens on it when
     * there is one and on the history when there is not.
     */
    public function index(AdmissionIndexRequest $request)
    {
        try {
            $records = $this->admissionService->index($request);
            $active = $this->admissionService->active();

            $data = [
                'counts' => $this->admissionService->counts(),
                'filter' => $this->admissionService->appliedFilter($request),
                'active_admission' => $active ? new AdmissionDetailResource($active) : null,
                'records' => $request->paginate
                    ? AdmissionResource::collection($records)->response()->getData(true)
                    : AdmissionResource::collection($records),
            ];

            return JsonResponser::send(false, 'Admissions retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/admissions/{id}
     */
    public function show($id)
    {
        try {
            $record = $this->admissionService->show($id);

            return JsonResponser::send(
                false,
                'Admission retrieved successfully.',
                new AdmissionDetailResource($record),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
