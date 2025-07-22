<?php

namespace App\Http\Controllers;

use App\Services\ServiceDepartment\ServiceDepartmentService;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;

class MainDashBoardStatsController extends Controller
{
    public $service_department_service;

    public function __construct(ServiceDepartmentService $service_department_service)
    {
        $this->service_department_service = $service_department_service;
    }
    public function index(Request $request)
    {

        try {
            $validated = $request->validate([
                "filter_calender" => 'nullable|string|in:daily,monthly, yearly'
            ]);

            $data = $this->service_department_service->main_dashboard($validated);
            return JsonResponser::send(false, 'Billing stats fetched successfully.', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error fetching billing stats.', [], 500, $e);
        }
    }
}
