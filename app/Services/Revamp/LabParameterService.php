<?php

namespace App\Services\Revamp;

use App\Models\LabParameter;

class LabParameterService
{
    public function overview($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $query = LabParameter::query()
            // ->where('tenant_id', $tenantId)
            ->with('serviceCategory')
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($subQuery) use ($request) {
                    $subQuery->where('name', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('code', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('unit', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['service_category_id']), function ($query) use ($request) {
                $query->where('service_category_id', $request['service_category_id']);
            })
            ->when(isset($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            });

        if (!empty($request['paginate'])) {
            return $query->orderBy('display_order')->orderBy('id', 'DESC')
                ->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('display_order')->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        return [
            'total' => LabParameter::count(),
            'active' => LabParameter::where('status', true)->count(),
            'inactive' => LabParameter::where('status', false)->count(),
        ];
    }

    public function create(array $data, string $tenantId)
    {
        $parameter = LabParameter::create([
            'tenant_id' => $tenantId,
            ...$data,
        ]);

        return $parameter->load('serviceCategory');
    }

    public function update(int $id, array $data, string $tenantId)
    {
        $parameter = LabParameter::findOrFail($id);
        $parameter->update($data);
        return $parameter->load('serviceCategory');
    }

    public function delete(int $id, string $tenantId)
    {
        $parameter = LabParameter::findOrFail($id);
        $parameter->delete();
        return $parameter;
    }
}
