<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Diagnostics\DiagnosticIndexRequest;
use App\Http\Resources\Patient\LabResultDetailResource;
use App\Http\Resources\Patient\LabResultResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Laboratory\PatientLaboratoryService;
use Illuminate\Http\Request;
use Throwable;

/**
 * The laboratory module of the patient mobile app.
 *
 * Read only — results are entered by the lab. The patient browses them, opens
 * one, downloads its report, and looks back at previous reports of the same
 * test.
 */
class LaboratoryController extends Controller
{
    public function __construct(protected PatientLaboratoryService $laboratoryService) {}

    /**
     * GET /v1/patient/laboratory
     *
     * The All / Available / Pending list, filtered by `tab`, `search_param`, a
     * single `date`, or a range given as a `period` or a `start_date`–`end_date`
     * pair.
     */
    public function index(DiagnosticIndexRequest $request)
    {
        try {
            $records = $this->laboratoryService->index($request);

            $data = [
                'counts' => $this->laboratoryService->counts(),
                'filter' => $this->laboratoryService->appliedFilter($request),
                'records' => $request->paginate
                    ? LabResultResource::collection($records)->response()->getData(true)
                    : LabResultResource::collection($records),
            ];

            return JsonResponser::send(false, 'Laboratory results retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/laboratory/{id}
     */
    public function show($id)
    {
        try {
            $record = $this->laboratoryService->show($id);

            return JsonResponser::send(
                false,
                'Laboratory result retrieved successfully.',
                new LabResultDetailResource($record),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/laboratory/{testId}/history
     *
     * "View Previous Reports" — every released result this patient has for one
     * test, across every visit they have ever made.
     *
     * The id here is the test's id in the laboratory catalogue, the `test_id`
     * the list and detail payloads carry, and not the id of a single result.
     * Pass `?exclude={id}` to leave the result currently on screen out of its
     * own history.
     */
    public function history(Request $request, $testId)
    {
        try {
            $records = $this->laboratoryService->history($testId, $request->query('exclude'));

            return JsonResponser::send(
                false,
                'Previous reports retrieved successfully.',
                LabResultResource::collection($records),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/laboratory/{id}/download
     *
     * "Download PDF". Streams the report rather than answering in the JSON
     * envelope, so the app can hand it straight to the file system or a share
     * sheet.
     */
    public function download($id)
    {
        try {
            return $this->laboratoryService->download($id);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
