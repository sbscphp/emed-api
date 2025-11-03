<?php

namespace App\Http\Controllers\v1\Admin\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Exports\AuditLogExport;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FulfillTreatmentRequest;
use App\Http\Requests\Admin\PharmacyRequest;
use App\Models\Medication;
use App\Responser\JsonResponser;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest as ModelsPharmacyRequest;
use App\Models\Treatment;
use App\Services\Revamp\PharmacyService;
use Maatwebsite\Excel\Facades\Excel;

class PharmacyController extends Controller
{
    protected PharmacyService $pharmacyService;

    public function __construct(
        PharmacyService $pharmacyService,
    ) {
        $this->pharmacyService = $pharmacyService;
    }

    public function index(Request $request)
    {

        try {
            $overview = $this->pharmacyService->overview($request);

            $stats = $this->pharmacyService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->pharmacyService->export($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function patientTreatments(Request $request)
    {

        try {
            $overview = $this->pharmacyService->patientTreatmentOverview($request);

            $records = [
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->pharmacyService->patientTreatmentsExport($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function viewPatientTreatment($id)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $treatment = Treatment::with('pharmacyRequest.pharmacy', 'billingLogDetail')->find($id);
            if (!$treatment) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            // Manually fetch dispensed user from landlord DB
            if ($treatment->dispensed_by) {
                $dispensedUser = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($treatment->dispensed_by);

                $treatment->setAttribute('dispensedBy', $dispensedUser);
            } else {
                $treatment->setAttribute('dispensedBy', null);
            }

            if ($treatment->consultation->consulted_by) {
                $consultedUser = User::on('landlord')
                    ->select('id', 'first_name', 'last_name', 'email')
                    ->find($treatment->consultation->consulted_by);

                $treatment->setAttribute('consultedBy', $consultedUser);
            } else {
                $treatment->setAttribute('consultedBy', null);
            }

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Record found successfully', $treatment, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function fulfillTreatment(FulfillTreatmentRequest $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();

            $treatment = Treatment::with('billingLogDetail')->find($request->treatment_id);

            if (!$treatment) {
                return JsonResponser::send(true, 'Record not found.', null, 422);
            }

            if (!is_null($treatment->status) && $treatment->status == GeneralEnums::FULLFILLED->value) {
                return JsonResponser::send(true, 'This treatment has already been fulfilled.', [], 422);
            }

            if ($treatment->billingLogDetail && $treatment->billingLogDetail->status == GeneralEnums::PENDING->value) {
                return JsonResponser::send(true, 'This treatment has not been paid for.', [], 422);
            }

            if ($request->quantity_dispensed > $treatment->quantity) {
                return JsonResponser::send(true, 'Treatment dispensed quantity is greater than quantity prescribed.', [], 422);
            }

            $drug = ModelsPharmacyRequest::with('inventory')->find($treatment->drug_id);
            if (!$drug) {
                return JsonResponser::send(true, 'Drug not found.', [], 422);
            }

            if ($drug->inventory->expiry_date && Carbon::parse($drug->inventory->expiry_date)->isPast()) {
                return JsonResponser::send(true, 'Drug expired', [], 422);
            }

            if ($drug->stock_level == GeneralEnums::OUT_OF_STOCK->value) {
                return JsonResponser::send(true, 'Drug is not available in stock.', [], 422);
            }

            if ($request->quantity_dispensed > $drug->quantity_available) {
                return JsonResponser::send(true, 'Drug dispense quantity is greater than quantity available', [], 422);
            }

            $record = $this->pharmacyService->fulfillTreatment($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $record->id,
                'action' => 'Create',
                'action_type' => "Models\Treatment",
                'log_name' => "Treatment fulfilled successfully",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} fulfilled a new treatment: {$record->drug}",
                'module_accessed' => ListModuleEnums::PHARMACY
            ];
            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Treatment fulfilled successfully', $record, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function allPharmacies(Request $request)
    {
        try {
            DB::connection('tenant')->beginTransaction();
            $overview = $this->pharmacyService->pharmacyOverview($request);

            $records = [
                'data' => $overview
            ];

            if (isset($request->export)) {
                $format = $request->export;
                return $this->pharmacyService->pharmacyExport($overview, $format);
            }
            if (!$request->paginate) {
                $records = $overview;
            }
            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function createPharmacy(PharmacyRequest $request)
    {

        try {
            DB::connection('tenant')->beginTransaction();
            $currentUser = Auth::user();
            $pharmacy = $this->pharmacyService->createPharmacy($request);

            $dataToLog = [
                'causer_id' => $currentUser->id,
                'action_id' => $pharmacy->id,
                'action' => 'Create',
                'action_type' => "Models\Pharmacy",
                'log_name' => "Pharmacy created successfully",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} created a new pharmacy: {$pharmacy->name}",
                'module_accessed' => ListModuleEnums::Inventory
            ];
            GeneralHelper::storeAuditLog($dataToLog);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Pharmacy created successfully', $pharmacy, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function viewPharmacy($id)
    {

        try {
            DB::connection('tenant')->beginTransaction();

            $pharmacy = Pharmacy::find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Record found successfully', $pharmacy, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function updatePharmacy(Request $request, $id)
    {
        try {
            config(['database.default' => 'tenant']);
            $data = $request->all();
            $pharmacy = Pharmacy::find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $updatedPharmacy = $this->pharmacyService->updatePharmacy($data, $id);

            return JsonResponser::send(false, 'Pharmacy updated successfully', $updatedPharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function togglePharmacyStatus($id)
    {
        try {
            config(['database.default' => 'tenant']);
            $pharmacy = Pharmacy::find($id);
            if (!$pharmacy) {
                return JsonResponser::send(true, 'Record not found.', null, 200);
            }

            $newStatus = $pharmacy->active ? 0 : 1;
            $pharmacy->update(['active' => $newStatus]);

            return JsonResponser::send(false, 'Pharmacy status updated successfully', $pharmacy, 200);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroyPharmacy($id)
    {
        config(['database.default' => 'tenant']);
        $pharmacy = Pharmacy::find($id);
        if (!$pharmacy) {
            return JsonResponser::send(true, 'Record not found.', null, 200);
        }
        // delete the pharmacy
        $pharmacy->delete();
        return JsonResponser::send(false, 'Pharmacy deleted successfully', null, 200);
    }
}
