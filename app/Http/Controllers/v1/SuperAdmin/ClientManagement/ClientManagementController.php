<?php

namespace App\Http\Controllers\v1\SuperAdmin\ClientManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\CreateClientRequest;
use App\Http\Requests\SuperAdmin\UpdateClientRequest;
use App\Responser\JsonResponser;
use App\Services\SuperAdmin\ClientManagement\ClientManagementService;
use Illuminate\Http\Request;

class ClientManagementController extends Controller
{
    protected ClientManagementService $clientManagementService;

    public function __construct(ClientManagementService $clientManagementService)
    {
        $this->clientManagementService = $clientManagementService;
    }

    public function index(Request $request)
    {
        try {
            $records = $this->clientManagementService->overview($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function create(CreateClientRequest $request)
    {
        try {
            $record = $this->clientManagementService->createClient($request);

            return JsonResponser::send(false, 'Client created successfully', $record, 201);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function show($id)
    {
        try {
            $record = $this->clientManagementService->showClient($id);

            if (!$record) {
                return JsonResponser::send(true, 'Client not found', 'Not Found', 404);
            }

            return JsonResponser::send(false, 'Client fetched successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function update(UpdateClientRequest $request, $id)
    {
        try {
            $record = $this->clientManagementService->updateClient($id, $request);

            return JsonResponser::send(false, 'Client updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function showVisitCharges(Request $request, $id)
    {
        try {
            $result = $this->clientManagementService->showClientVisitCharges($id, $request);

            // Export returns a BinaryFileResponse — stream it directly to the client
            if ($request->boolean('export')) {
                return $result;
            }

            return JsonResponser::send(false, 'Client visit charges fetched successfully', $result);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function updateVisitCharges(Request $request, $id)
    {
        try {
            $result = $this->clientManagementService->updateClientVisitCharges($id, $request);

            return JsonResponser::send(false, 'Client visit charges updated successfully', $result);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $record = $this->clientManagementService->toggleClientStatus($id);

            return JsonResponser::send(false, 'Client status updated successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }

    public function remove($id)
    {
        try {
            $this->clientManagementService->removeClient($id);

            return JsonResponser::send(false, 'Client removed successfully', null);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th->getMessage(), 'Internal Server Error', 500);
        }
    }
}
