<?php

namespace App\Services\Revamp;

use App\Models\LabService;
use App\Models\LabParameter;
use Illuminate\Support\Collection;

class LabParameterService
{
    public function overview($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $query = LabParameter::query()
            ->where('tenant_id', $tenantId)
            ->with(['serviceCategory', 'labTest'])
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
            ->when(isset($request['lab_test_id']), function ($query) use ($request) {
                $query->where('lab_test_id', $request['lab_test_id']);
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

        return $parameter->load(['serviceCategory', 'labTest']);
    }

    public function update(int $id, array $data, string $tenantId)
    {
        $parameter = LabParameter::findOrFail($id);
        $parameter->update($data);
        return $parameter->load(['serviceCategory', 'labTest']);
    }

    public function delete(int $id, string $tenantId)
    {
        $parameter = LabParameter::findOrFail($id);
        $parameter->delete();
        return $parameter;
    }

    public function assignToLabTest(LabService $labTest, array $parameters, string $tenantId): Collection
    {
        $assigned = collect();

        foreach ($parameters as $index => $parameterData) {
            $parameter = LabParameter::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'lab_test_id' => $labTest->id,
                    'name' => $parameterData['name'],
                ],
                [
                    'service_category_id' => $labTest->service_category_id,
                    'code' => $parameterData['code'] ?? null,
                    'unit' => $parameterData['unit'] ?? null,
                    'reference_range' => $parameterData['reference_range'] ?? null,
                    'input_type' => $parameterData['input_type'] ?? 'text',
                    'display_order' => $parameterData['display_order'] ?? $index,
                    'is_required' => (bool) ($parameterData['is_required'] ?? false),
                    'status' => array_key_exists('status', $parameterData) ? (bool) $parameterData['status'] : true,
                ]
            );

            $assigned->push($parameter);
        }

        return LabParameter::whereIn('id', $assigned->pluck('id')->all())
            ->with(['serviceCategory', 'labTest'])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    public function getEffectiveParametersForLabTest(?LabService $labTest): Collection
    {
        if (!$labTest) {
            return collect();
        }

        $testParameters = $labTest->labParameters()
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        if ($testParameters->isNotEmpty()) {
            return $testParameters;
        }

        return $labTest->serviceCategory?->labParameters()
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get() ?? collect();
    }
}
