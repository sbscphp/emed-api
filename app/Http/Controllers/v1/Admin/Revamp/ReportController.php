<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use App\Services\Revamp\ReportService;
use Throwable;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(
        ReportService $reportService,
    ) {
        $this->reportService = $reportService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->reportService->overview($request);

            $stats = $this->reportService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                return $this->reportService->export($overview, $format);
            }

            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function fetchAllReports(Request $request)
    {

        try {
            $overview = $this->reportService->fetchAllReportOverview($request);

            if ($request->has('export') && !empty($request->query('export'))) {
                $format = $request->query('export');
                $type = $request->query('type');
                return $this->reportService->fetchAllReportExport($overview, $format, $type);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $overview);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
