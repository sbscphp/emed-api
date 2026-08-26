<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use App\Services\Revamp\DashboardService;
use Throwable;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(
        DashboardService $dashboardService,
    ) {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->dashboardService->overview($request);

            $stats = $this->dashboardService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->dashboardService->export($overview, $format);
            }

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
