<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\Vitals\VitalsIndexRequest;
use App\Http\Resources\Patient\VitalDetailResource;
use App\Http\Resources\Patient\VitalResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Vitals\PatientVitalsService;
use Throwable;

/**
 * The vitals module of the patient mobile app.
 *
 * Read only: vitals are taken by the hospital during triage, so the patient
 * looks at them and never records one.
 */
class VitalsController extends Controller
{
    public function __construct(protected PatientVitalsService $vitalsService) {}

    /**
     * GET /v1/patient/vitals
     *
     * Every sitting the patient's vitals were taken in, newest first, filtered
     * by `date` or by a `start_date`–`end_date` range.
     */
    public function index(VitalsIndexRequest $request)
    {
        try {
            $records = $this->vitalsService->index($request);

            $data = [
                'summary' => $this->vitalsService->summary(),
                // The range the period actually resolved to, so a filter landing
                // on the wrong day shows up here rather than having to be
                // inferred from the records that came back.
                'filter' => $this->vitalsService->appliedFilter($request),
                'records' => $request->paginate
                    ? VitalResource::collection($records)->response()->getData(true)
                    : VitalResource::collection($records),
            ];

            return JsonResponser::send(false, 'Vitals retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/vitals/latest
     *
     * The "Latest Readings" page reached from the home screen's health
     * snapshot.
     */
    public function latest()
    {
        try {
            $record = $this->vitalsService->latest();

            if (!$record) {
                return JsonResponser::send(false, 'No vitals have been recorded for you yet.', null, 200);
            }

            return JsonResponser::send(
                false,
                'Vitals retrieved successfully.',
                new VitalResource($record),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/vitals/{id}
     *
     * One sitting opened from the list.
     */
    public function show($id)
    {
        try {
            $record = $this->vitalsService->show($id);

            return JsonResponser::send(
                false,
                'Vitals retrieved successfully.',
                new VitalDetailResource($record),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
