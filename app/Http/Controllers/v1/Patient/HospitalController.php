<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Patient\HospitalDetailResource;
use App\Http\Resources\Patient\HospitalResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Hospital\PatientHospitalService;
use Throwable;

/**
 * The Linked Hospitals module of the patient mobile app.
 *
 * Read only, and the one patient module that carries no tenant header: it is
 * about the account rather than about any one hospital, and the hospital it
 * would name is the answer rather than the question.
 */
class HospitalController extends Controller
{
    public function __construct(protected PatientHospitalService $hospitalService) {}

    /**
     * GET /v1/patient/hospitals
     *
     * Every hospital that has registered this account, oldest link first. The
     * first is the patient's primary hospital.
     */
    public function index()
    {
        try {
            $hospitals = $this->hospitalService->index();

            return JsonResponser::send(
                false,
                'Hospitals retrieved successfully.',
                HospitalResource::collection($hospitals),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }

    /**
     * GET /v1/patient/hospitals/{uuid}
     *
     * One hospital's card: how to reach them, and the number they know the
     * patient by.
     */
    public function show($uuid)
    {
        try {
            $hospital = $this->hospitalService->show($uuid);

            return JsonResponser::send(
                false,
                'Hospital retrieved successfully.',
                new HospitalDetailResource($hospital),
                200
            );
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
