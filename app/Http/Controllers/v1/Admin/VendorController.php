<?php

namespace App\Http\Controllers\v1\Admin;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Admin\StoreVendorRequest;
use App\Http\Requests\Admin\UpdateVendorRequest;
use App\Http\Requests\Admin\UpdateStatusVendorRequest;
use App\Services\Vendor\VendorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    protected $service;

    public function __construct(VendorService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            config(['database.default' => 'tenant']);
            $filters = $request->only(['search', 'type', 'export', 'vendor_name', 'contact_person', 'email', 'phone_number']);
            // vendor_name,  contact_person, email,  phone_number

            $from = $request->from;
            $to = $request->to;

            $data = $this->service->all($filters, $filters['export'] ?? null, $from, $to);

            if ($data instanceof \Symfony\Component\HttpFoundation\Response) {
                return $data;
            }

            if ($data->isEmpty()) {
                return JsonResponser::send(true, 'No vendors found.', null, 200);
            }

            return JsonResponser::send(false, 'Vendors fetched successfully.', $data);
        } catch (\InvalidArgumentException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error.', [], 500);
        }
    }


    public function show($id)
    {

        try {
            config(['database.default' => 'tenant']);
            $vendor = $this->service->find($id);

            if (!$vendor) {
                return JsonResponser::send(true, 'Vendor not found.', null, 200);
            }

            return JsonResponser::send(false, 'Vendor details fetched successfully', $vendor);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function store(StoreVendorRequest $request)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $validated = array_merge($request->validated(), [
                'created_by' => $currentUser->id,
            ]);

            $vendor = $this->service->create($validated);

            GeneralHelper::storeAuditLog([
                'causer_id' => $currentUser->id,
                'action_id' => $vendor->id,
                'action' => 'Create',
                'action_type' => "Models\\Vendor",
                'log_name' => "Vendor created",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} created vendor: {$vendor->name}",
            ]);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Vendor created successfully', $vendor, 201);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function update(UpdateVendorRequest $request, $id)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $currentUser = Auth::user();
            $validated = array_merge($request->validated(), [
                'updated_by' => $currentUser->id,
            ]);

            $vendor = $this->service->update($validated, $id);

            GeneralHelper::storeAuditLog([
                'causer_id' => $currentUser->id,
                'action_id' => $vendor->id,
                'action' => 'Update',
                'action_type' => "Models\\Vendor",
                'log_name' => "Vendor updated",
                'description' => "{$currentUser->firstname} {$currentUser->lastname} updated vendor: {$vendor->name}",
            ]);

            DB::connection('tenant')->commit();
            return JsonResponser::send(false, 'Vendor updated successfully', $vendor);
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function destroy($id)
    {
        try {
            config(['database.default' => 'tenant']);

            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return JsonResponser::send(true, 'Vendor not found.', null, 200);
            }

            return JsonResponser::send(false, 'Vendor deleted successfully', $deleted);
        } catch (\Exception $e) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        }
    }

    public function getVendorStats()
    {

        // try {
        DB::connection('landlord')->beginTransaction();
        $stats = $this->service->getVendorStats();
        DB::connection('landlord')->commit();
        return JsonResponser::send(false, 'Vendor stats fetched successfully', $stats);
        // } catch (\Exception $e) {
        //     return JsonResponser::send(true, 'Internal server error', [], 500, $e);
        // }
    }

    public function update_status(UpdateStatusVendorRequest $request, $id)
    {
        try {
            DB::connection('landlord')->beginTransaction();
            $validated = $request->validated();
            $result = $this->service->update_status($validated, $id);
            //    DB::connection('landlord')->commit();
            return JsonResponser::send(false, 'Vendor stats fetched successfully', $result);
        } catch (\Throwable $th) {
            DB::connection('landlord')->rollBack();
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }
}
