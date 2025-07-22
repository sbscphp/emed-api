<?php

namespace App\Http\Controllers;

use App\Services\ServiceDepartment\ServiceDepartmentService;
use Illuminate\Http\Request;

class MainDashBoardStatsController extends Controller
{
    public $service_department_service;

    public function __construct(ServiceDepartmentService $service_department_service)
    {
        $this->service_department_service = $service_department_service;
    }
    public function index(Request $request)
    {
        // $validated = $request->validate([]);

        $data = $this->service_department_service->main_dashboard();
    }
}
