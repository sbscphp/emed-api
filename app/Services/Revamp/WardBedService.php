<?php

namespace App\Services\Revamp;

use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;

class WardBedService
{
    public function overview($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $query = Bed::query()
            ->with('ward')
            ->whereHas('ward', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $search = '%' . $request['search_param'] . '%';

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('bed_number', 'LIKE', $search)
                        ->orWhereHas('ward', function ($wardQuery) use ($search) {
                            $wardQuery->where('name', 'LIKE', $search)
                                ->orWhere('type', 'LIKE', $search)
                                ->orWhere('gender', 'LIKE', $search);
                        });
                });
            })
            ->when(!empty($request['ward_id']), function ($query) use ($request) {
                $query->where('ward_id', $request['ward_id']);
            })
            ->when(isset($request['status']), function ($query) use ($request) {
                $query->where('status', filter_var($request['status'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(isset($request['occupied']), function ($query) use ($request) {
                $query->where('occupied', filter_var($request['occupied'], FILTER_VALIDATE_BOOLEAN));
            });

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        $bedQuery = Bed::query()->whereHas('ward', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        });

        return [
            'total_beds' => (clone $bedQuery)->count(),
            'available_beds' => (clone $bedQuery)->where('occupied', false)->count(),
            'occupied_beds' => (clone $bedQuery)->where('occupied', true)->count(),
        ];
    }

    public function create(array $data, string $tenantId)
    {
        return DB::connection('tenant')->transaction(function () use ($data, $tenantId) {
            $ward = Ward::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'type' => $data['type'],
                'gender' => $data['gender'],
                'status' => $data['status'] ?? true,
                'bed_cost' => $data['bed_cost'] ?? 0,
            ]);

            $this->syncBeds($ward, $data);

            return $this->loadWardDetails($ward);
        });
    }

    public function find(int $id, string $tenantId)
    {
        $ward = Ward::where('tenant_id', $tenantId)->find($id);

        if (!$ward) {
            return null;
        }

        return $this->loadWardDetails($ward);
    }

    public function update(int $id, array $data, string $tenantId)
    {
        return DB::connection('tenant')->transaction(function () use ($id, $data, $tenantId) {
            $ward = Ward::where('tenant_id', $tenantId)->find($id);

            if (!$ward) {
                return null;
            }

            $ward->update(collect($data)
                ->only(['name', 'type', 'gender', 'status', 'bed_cost'])
                ->toArray());

            if (isset($data['beds']) || $this->requestedBedCount($data) > 0) {
                $ward->beds()->delete();
                $this->syncBeds($ward, $data);
            }

            return $this->loadWardDetails($ward);
        });
    }

    public function delete(int $id, string $tenantId): bool
    {
        return DB::connection('tenant')->transaction(function () use ($id, $tenantId) {
            $ward = Ward::where('tenant_id', $tenantId)->find($id);

            if (!$ward) {
                return false;
            }

            $ward->beds()->delete();
            $ward->delete();

            return true;
        });
    }

    private function syncBeds(Ward $ward, array $data): void
    {
        $beds = $this->bedPayload($data);

        if (empty($beds)) {
            return;
        }

        $ward->beds()->createMany($beds);
    }

    private function bedPayload(array $data): array
    {
        if (!empty($data['beds'])) {
            return collect($data['beds'])->map(function ($bed, $index) {
                $occupied = $bed['occupied'] ?? false;

                return [
                    'bed_number' => $bed['bed_number'] ?? (string) ($index + 1),
                    'number_of_available' => $bed['number_of_available'] ?? ($occupied ? 0 : 1),
                    'occupied' => $occupied,
                    'status' => $bed['status'] ?? true,
                ];
            })->toArray();
        }

        $bedCount = $this->requestedBedCount($data);

        if ($bedCount < 1) {
            return [];
        }

        return collect(range(1, $bedCount))
            ->map(fn ($bedNumber) => [
                'bed_number' => (string) $bedNumber,
                'number_of_available' => 1,
                'occupied' => false,
                'status' => true,
            ])
            ->toArray();
    }

    private function requestedBedCount(array $data): int
    {
        return (int) ($data['bed_count']
            ?? $data['number_of_beds']
            ?? $data['total_beds']
            ?? $data['bed_space']
            ?? $data['bed_spaces']
            ?? 0);
    }

    private function loadWardDetails(Ward $ward): Ward
    {
        $ward->load('beds');

        $ward->setAttribute('total_beds', $ward->beds->count());
        $ward->setAttribute('available_beds', $ward->beds->where('occupied', false)->count());
        $ward->setAttribute('occupied_beds', $ward->beds->where('occupied', true)->count());

        return $ward;
    }
}
