<?php

namespace App\Http\Controllers\v1\SuperAdmin\Dashboard;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\Dashboard\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {
        try {
            $records = $this->dashboardService->dashboard($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
