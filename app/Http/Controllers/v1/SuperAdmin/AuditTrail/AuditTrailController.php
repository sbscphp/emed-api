<?php

namespace App\Http\Controllers\v1\SuperAdmin\AuditTrail;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\AuditTrail\AuditTrailService;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    protected AuditTrailService $auditTrailService;

    public function __construct(AuditTrailService $auditTrailService)
    {
        $this->auditTrailService = $auditTrailService;
    }

    public function index(Request $request)
    {
        try {
            $records = $this->auditTrailService->overview($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
