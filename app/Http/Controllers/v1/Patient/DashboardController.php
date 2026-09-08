<?php

namespace App\Http\Controllers\v1\Patient;

use App\Exceptions\PatientAppException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Patient\AppointmentDetailResource;
use App\Responser\JsonResponser;
use App\Services\Patient\Dashboard\PatientDashboardService;
use App\Responser\JsonResponser as Responser;
use Throwable;

/**
 * The home screen of the patient mobile app.
 *
 * One endpoint, because the screen is one screen: the welcome line, the health
 * snapshot, the health record tiles and the next appointment arrive together
 * rather than as four requests the app has to fan out and wait on.
 */
class DashboardController extends Controller
{
    public function __construct(protected PatientDashboardService $dashboardService) {}

    /**
     * GET /v1/patient/dashboard
     */
    public function index()
    {
        try {
            $data = $this->dashboardService->overview();

            $data['latest_appointment'] = $data['latest_appointment']
                ? new AppointmentDetailResource($data['latest_appointment'])
                : null;

            return JsonResponser::send(false, 'Dashboard retrieved successfully.', $data, 200);
        } catch (PatientAppException $th) {
            return JsonResponser::send(true, $th->getMessage(), [], $th->status());
        } catch (Throwable $th) {
            return JsonResponser::send(true, 'Internal server error.', [], 500, $th);
        }
    }
}
