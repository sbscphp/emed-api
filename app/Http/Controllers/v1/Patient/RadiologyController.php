<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Diagnostics\DiagnosticIndexRequest;
use App\Http\Resources\Patient\RadiologyResultDetailResource;
use App\Http\Resources\Patient\RadiologyResultResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Radiology\PatientRadiologyService;
use Throwable;

/**
 * The radiology module of the patient mobile app.
 *
 * Read only — reports are written by the radiologist. The patient browses their
 * scans, opens one, downloads its report, and looks back at previous reports of
 * the same examination.
 */
class RadiologyController extends Controller
{
    public function __construct(protected PatientRadiologyService $radiologyService) {}

    /**
     * GET /v1/patient/radiology
     *
     * The All / Released / Pending list, filtered by `tab`, `search_param`, a
     * single `date`, or a range given as a `period` or a `start_date`–`end_date`
     * pair.
     */
    public function index(DiagnosticIndexRequest $request)
    {
        try {
            $records = $this->radiologyService->index($request);

            $data = [
                'counts' => $this->radiologyService->counts(),
                'filter' => $this->radiologyService->appliedFilter($request),
                'records' => $request->paginate
                    ? RadiologyResultResource::collection($records)->response()->getData(true)
                    : RadiologyResultResource::collection($records),
            ];

            return JsonResponser::send(false, 'Radiology reports retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/radiology/{id}
     */
    public function show($id)
    {
        try {
            $record = $this->radiologyService->show($id);

            return JsonResponser::send(
                false,
                'Radiology report retrieved successfully.',
                new RadiologyResultDetailResource($record),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/radiology/{id}/history
     *
     * "View Previous Reports" — the released reports of the same examination.
     */
    public function history($id)
    {
        try {
            $records = $this->radiologyService->history($id);

            return JsonResponser::send(
                false,
                'Previous reports retrieved successfully.',
                RadiologyResultResource::collection($records),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/radiology/{id}/download
     *
     * "Download PDF". Streams the report rather than answering in the JSON
     * envelope, so the app can hand it straight to the file system or a share
     * sheet.
     */
    public function download($id)
    {
        try {
            return $this->radiologyService->download($id);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
