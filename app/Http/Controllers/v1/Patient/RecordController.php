<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Patient\Records\PatientRecordService;
use Throwable;

/**
 * The "My Record" page of the patient mobile app, reached from View All on the
 * home screen's health record strip.
 *
 * The page is an index of the record modules rather than of records: each row
 * carries its count and the module key the app navigates on, and the records
 * themselves come from that module's own endpoints.
 */
class RecordController extends Controller
{
    public function __construct(protected PatientRecordService $recordService) {}

    /**
     * GET /v1/patient/records
     */
    public function index()
    {
        try {
            $data = [
                'sections' => $this->recordService->sections(),
            ];

            return JsonResponser::send(false, 'Health records retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
