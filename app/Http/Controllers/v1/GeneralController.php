<?php

namespace App\Http\Controllers\v1;

use App\Helpers\ExportHelper;
use App\Helpers\FileUploadHelper;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Inventory;
use App\Models\LabService;
use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\RadiologyService;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceUnit;
use App\Models\User;
use Illuminate\Http\Request;
use App\Responser\JsonResponser;
use Illuminate\Support\Facades\DB;

class GeneralController extends Controller
{
    // public function allLabTest(Request $request)
    // {
    //     try {
    //         $tenantId = $request->header('X-Tenant-ID');
    //         $record = LabService::where('tenant_id', $tenantId)
    //             ->when(!empty($request['search_param']), function ($query) use ($request) {
    //                 $query->where(function ($q) use ($request) {
    //                     $q->orWhere('name', 'LIKE', '%' . $request['search_param'] . '%')
    //                         ->orWhere('class', 'LIKE', '%' . $request['search_param'] . '%')
    //                         ->orWhere('price', 'LIKE', '%' . $request['search_param'] . '%')
    //                         ->orWhere('type', 'LIKE', '%' . $request['search_param'] . '%');
    //                 });
    //             })
    //             ->when(!empty($request->type), function ($query) use ($request) {
    //                 $query->where('type', $request->type);
    //             })->orderBy('id', 'DESC')->get();

    //         return JsonResponser::send(false, 'Record found successfully', $record, 200);
    //     } catch (\Throwable $th) {
    //         return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
    //     }
    // }

    public function allLabTest(Request $request)
    {
        try {

            $tenantId = $request->header('X-Tenant-ID');
            $query = LabService::where('tenant_id', $tenantId)
            ->with('serviceCategory')
                ->when(!empty($request['search_param']), function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->orWhere('name', 'LIKE', '%' . $request['search_param'] . '%')
                            ->orWhere('class', 'LIKE', '%' . $request['search_param'] . '%')
                            ->orWhere('price', 'LIKE', '%' . $request['search_param'] . '%')
                            ->orWhere('type', 'LIKE', '%' . $request['search_param'] . '%');
                    });
                })
                ->when(!empty($request->type), function ($query) use ($request) {
                    $query->where('type', $request->type);
                })->orderBy('id', 'DESC');

            if (!empty($request['export'])) {
                $records = $query->get();

                $exportData = $records->map(function ($lab) {
                    return [
                        'Name'   => $lab->name ?? 'N/A',
                        'Class'       => $lab->class ?? 'N/A',
                        'Type'        => $lab->type ?? 'N/A',
                        'Price'       => $lab->price ?? 'N/A'
                    ];
                })->toArray();

                if ($request['export'] === 'csv') {
                    return ExportHelper::streamCsv($exportData, null, 'lab-tests.csv');
                }

                if ($request['export'] === 'pdf') {
                    return ExportHelper::downloadPdf($exportData, 'lab-tests.pdf');
                }
            }

            if (!empty($request['paginate'])) {
                $records = $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
                return JsonResponser::send(false, 'Record found successfully', $records, 200);
            }

            $records = $query->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), [], 500);
        }
    }

    public function allRadiologyTest(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = RadiologyService::where('tenant_id', $tenantId)->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allMedicine(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = PharmacyRequest::where('tenant_id', $tenantId)
                ->whereRelation('inventory', 'expiry_date', '>=', now())
                // ->where('pharmacy_id', $request->pharmacy_id)
                ->with('inventory', 'pharmacy')->orderBy('id', 'DESC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function medications(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = Medication::where('tenant_id', $tenantId)->orderBy('id', 'DESC')->limit($request->limit ?? 10)->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allService(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = Service::where('tenant_id', $tenantId)
                ->when(!empty($request['type']), function ($query) use ($request) {
                    $query->where('type', $request['type']);
                })->orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allServiceUnits(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = ServiceUnit::where('tenant_id', $tenantId)->orderBy('id', 'ASC')->get();
            // $record = ServiceUnit::all();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allInventoryDrugs(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = Inventory::where('tenant_id', $tenantId)->orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allMedication(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = Medication::where('tenant_id', $tenantId)->orderBy('id', 'ASC')->limit($request->limit ?? 10)->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allPharmacy(Request $request)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $record = Pharmacy::where('tenant_id', $tenantId)->orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function allLabCategory(Request $request)
    {
        try {

            $record = ServiceCategory::when(!empty($request->search_param), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search_param . '%');
            })->orderBy('id', 'ASC')->limit($request->limit ?? 10)->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function labTestByCategory(Request $request, $id)
    {
        try {
            $tenantId = $request->header('X-Tenant-ID');
            $query = LabService::where('tenant_id', $tenantId)
                ->where('service_category_id', $id)
                ->when(!empty($request['search_param']), function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->orWhere('name', 'LIKE', '%' . $request['search_param'] . '%')
                            ->orWhere('class', 'LIKE', '%' . $request['search_param'] . '%')
                            ->orWhere('price', 'LIKE', '%' . $request['search_param'] . '%')
                            ->orWhere('type', 'LIKE', '%' . $request['search_param'] . '%');
                    });
                })
                ->when(!empty($request->type), function ($query) use ($request) {
                    $query->where('type', $request->type);
                })->with('serviceCategory');

            $record = $query->orderBy('id', 'ASC')->get();

            return JsonResponser::send(false, 'Record found successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function uploadSingleFileString(Request $request)
    {
        try {
            if (isset($request->file)) {
                $file = $request->file;
                $fileKey = 'Document';
                $fileUrl = FileUploadHelper::singleStringFileUpload($file, $fileKey);
            } else {
                $fileUrl = null;
            }
            return JsonResponser::send(false, 'File Uploaded successfully', $fileUrl, 200);
        } catch (\Throwable $error) {
            logger($error);
            return JsonResponser::send(true, $error->getMessage(), [], 500);
        }
    }

    public function uploadSingleFileBinary(Request $request)
    {
        try {
            if (isset($request->file)) {
                $file = $request->file;
                $fileKey = 'Document';
                $fileUrl = FileUploadHelper::singleBinaryFileUpload($file, $fileKey);
            } else {
                $fileUrl = null;
            }

            return JsonResponser::send(false, 'File Uploaded successfully', $fileUrl, 200);
        } catch (\Throwable $error) {
            logger($error);
            return JsonResponser::send(true, 'Internal server error!', [], 500);
        }
    }

    public function uploadMultipleFileBinary(Request $request)
    {
        try {
            if (isset($request->file)) {
                $file = $request->file;
                $fileKey = 'Document';
                $fileUrl = FileUploadHelper::multipleBinaryFileUpload($file, $fileKey);
            } else {
                $fileUrl = null;
            }

            return JsonResponser::send(false, 'File Uploaded successfully', implode("|", $fileUrl), 200);
        } catch (\Throwable $error) {
            logger($error);
            return JsonResponser::send(true, 'Internal server error!', [], 500);
        }
    }

    public function uploadMultipleFileString(Request $request)
    {
        try {

            if (isset($request->file)) {
                $file = $request->file;
                $fileKey = 'Document';
                $fileUrl = FileUploadHelper::multipleStringFileUpload($file, $fileKey);
            } else {
                $fileUrl = null;
            }

            return JsonResponser::send(false, 'File Uploaded successfully', implode("|", $fileUrl), 200);
        } catch (\Throwable $error) {
            logger($error);
            return JsonResponser::send(true, $error->getMessage(), [], 500);
        }
    }

    public function viewConsultation($id)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $consultation = Consultation::where('visit_id', $id)->with(['patient', 'patientVisit', 'labTest', 'radiologyTest', 'treatment', 'surgery', 'consultedDoctor'])->first();
            if (!$consultation) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            // Manually fetch dispensed user from landlord DB
            if ($consultation->consulted_by) {
                $consultedUser = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($consultation->consulted_by);

                $consultation->setAttribute('consultedBy', $consultedUser);
            } else {
                $consultation->setAttribute('consultedBy', null);
            }

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Record found successfully', $consultation, 201);
        } catch (\Throwable $th) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500, $th);
        }
    }
}
