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
            'total_beds' => (clone $bedQuery)->sum('bed_number'),
            'available_beds' => (clone $bedQuery)->sum('available_bed_number'),
            'occupied_beds' => (clone $bedQuery)->sum('bed_number') - (clone $bedQuery)->sum('available_bed_number'),
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
                'cost' => $data['cost'] ?? 0,
            ]);

            if (isset($data['bed_number'])) {
                $bed = Bed::where('ward_id', $ward->id)->first();
                if (!$bed) {
                    Bed::create([
                        'ward_id' => $ward->id,
                        'bed_number' => $data['bed_number'] ?? '1',
                        'available_bed_number' => $data['bed_number'] ?? 1,
                    ]);
                } else {
                    $oldBedNumber = (int) $bed->bed_number;
                    $newBedNumber = isset($data['bed_number']) ? (int) $data['bed_number'] : $oldBedNumber;
                    $difference = $newBedNumber - $oldBedNumber;
                    $newAvailableBedNumber = max(0, $bed->available_bed_number + $difference);

                    $bed->update([
                        'bed_number' => $newBedNumber,
                        'available_bed_number' => $newAvailableBedNumber,
                    ]);
                }
            }

            return $ward->fresh();
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
            $ward = Ward::where('tenant_id', $tenantId)->with('bed')->find($id);

            if (!$ward) {
                return null;
            }

            $ward->update(collect($data)
                ->only(['name', 'type', 'gender', 'cost'])
                ->toArray());

            if (isset($data['bed_number'])) {
                $bed = Bed::where('ward_id', $ward->id)->first();
                if (!$bed) {
                    Bed::create([
                        'ward_id' => $ward->id,
                        'bed_number' => $data['bed_number'] ?? '1',
                        'available_bed_number' => $data['bed_number'] ?? 1,
                    ]);
                } else {
                    $oldBedNumber = (int) $bed->bed_number;
                    $newBedNumber = isset($data['bed_number']) ? (int) $data['bed_number'] : $oldBedNumber;
                    $difference = $newBedNumber - $oldBedNumber;
                    $newAvailableBedNumber = max(0, $bed->available_bed_number + $difference);

                    $bed->update([
                        'bed_number' => $newBedNumber,
                        'available_bed_number' => $newAvailableBedNumber,
                    ]);
                }
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

            $ward->bed()->delete();
            $ward->delete();

            return true;
        });
    }

    private function loadWardDetails(Ward $ward): Ward
    {
        $ward->load('bed');

        $ward->setAttribute('total_beds', $ward->bed->bed_number ?? 0);
        $ward->setAttribute('available_beds', $ward->bed->available_bed_number ?? 0);
        $ward->setAttribute('occupied_beds', $ward->bed ? $ward->bed->bed_number - ($ward->bed->available_bed_number ?? 0) : 0);

        return $ward;
    }
}
