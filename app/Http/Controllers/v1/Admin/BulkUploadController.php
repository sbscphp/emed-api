<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Azeemade\BulkUpload\Services\BulkUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Azeemade\BulkUpload\Models\BulkUpload; // Added import

class BulkUploadController extends Controller
{
    protected $service;

    public function __construct(BulkUploadService $service)
    {
        $this->service = $service;
    }

    public function template(Request $request)
    {
        $request->validate([
            'model' => 'required|string',
            'format' => 'nullable|in:csv,xlsx',
        ]);

        try {
            $model = $request->input('model');
            $format = $request->input('format', 'csv');

            // Depending on how you want to download, you might need to use Maatwebsite/Excel
            // The service returns an export object.

            $export = $this->service->generateTemplate($model);
            $ext = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
            $filename = 'template.' . $format; // You might want to make this dynamic or derived from model name

            return \Maatwebsite\Excel\Facades\Excel::download($export, $filename, $ext);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Failed to generate template', [], 500, $e);
        }
    }

    public function show($batchId)
    {
        try {
            // $data = $this->service->getBatchStatus($batchId);
            // Re-implementing getBatchStatus logic here to fix the route parameter issue
            $bulkUpload = BulkUpload::where('batch_id', $batchId)->firstOrFail();

            $data = [
                'data' => $bulkUpload,
                'download_error_sheet_url' => $bulkUpload->error_file_path
                    ? route('bulk-upload.errors', ['batch_id' => $bulkUpload->batch_id]) // Fixed param name here
                    : null
            ];

            return JsonResponser::send(false, 'Batch status retrieved successfully', $data);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Batch not found', [], 404, $e);
        }
    }

    public function errors($batchId)
    {
        try {
            $path = $this->service->getDownloadableErrorFile($batchId);
            return response()->download($path);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Error file not found', [], 404, $e);
        }
    }
}
