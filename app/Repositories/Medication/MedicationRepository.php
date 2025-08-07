<?php

namespace App\Repositories\Medication;

use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\Medication;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MedicationRepository implements MedicationRepositoryInterface
{

    public function all($request)
    {
        $filters = $request;

        $query = Medication::on('tenant')
            ->with('pharmacy:id,name', 'medicationInventories:id,medication_id,active_ingredient')
            ->when(!empty($request['search']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('generic_name', 'like', "%{$request['search']}%")
                        ->orWhere('medicine_name', 'like', "%{$request['search']}%")
                        ->orWhere('medicine_type', 'like', "%{$request['search']}%")
                        ->orWhere('medicine_status', 'like', "%{$request['search']}%")
                        ->orWhere('manufacturer', 'like', "%{$request['search']}%");
                });
            });

        // Apply specific filters
        if (!empty($request['generic_name'])) {
            $query->where('generic_name', 'like', "%{$request['generic_name']}%");
        }

        if (!empty($request['medicine_name'])) {
            $query->where('medicine_name', 'like', "%{$request['medicine_name']}%");
        }

        if (!empty($request['medicine_type'])) {
            $query->where('medicine_type', 'like', "%{$request['medicine_type']}%");
        }

        if (!empty($request['medicine_status'])) {
            $query->where('medicine_status', $request['medicine_status']);
        }

        // ✅ Step 1: Resolve custom date from request
        $customDate = [];
        if (
            ($request['period'] ?? '') === 'custom date' &&
            !empty($request['start_date']) &&
            !empty($request['end_date'])
        ) {
            $customDate = [$request['start_date'], $request['end_date']];
        }

        // ✅ Step 2: Use helper to resolve date range
        $dateFilter = GeneralHelper::dateFilter($request['period'] ?? null, $customDate);

        // ✅ Step 3: Determine effective date range
        if (!empty($customDate) && count($customDate) === 2) {
            $startDate = Carbon::parse($customDate[0])->startOfDay();
            $endDate = Carbon::parse($customDate[1])->endOfDay();
        } elseif (is_array($dateFilter) && count($dateFilter) === 2) {
            [$startDate, $endDate] = $dateFilter;
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } else {
            $startDate = null;
            $endDate = null;
        }

        // ✅ Apply date filter
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // ✅ Handle export
        if (!empty($request['export'])) {
            $medications = $query->get();

            $exportData = $medications->map(function ($med) {
                return [
                    'Generic Name' => $med->generic_name,
                    'Brand Name' => $med->brand_name,
                    'Medicine Name' => $med->medicine_name,
                    'Medicine Type' => $med->medicine_type,
                    'Cost Price' => $med->cost_price,
                    'Selling Price' => $med->selling_price,
                    'Registration No' => $med->reg_no,
                    'Manufacturer' => $med->manufacturer,
                    'Medicine Status' => $med->medicine_status,
                    'Pharmacy' => $med->pharmacy->name ?? '',
                    'Active Ingredient' => $med->active_ingredient,
                    'Created At' => $med->created_at,
                ];
            });

            if ($request['export'] === 'csv') {
                return ExportHelper::streamCsv($exportData->toArray(), null, 'medications.csv');
            }

            if ($request['export'] === 'pdf') {
                return ExportHelper::downloadPdf($exportData->toArray(), 'medications.pdf');
            }

            // If export format is invalid, fallback to paginated response
            return $query->orderBy('id', 'DESC')->paginate(10);
        }

        return $query->orderBy('id', 'DESC')->paginate(10);
    }



    public function create(array $data)
    {
        return Medication::create($data);
    }

    public function find($id)
    {
        return Medication::with('pharmacy', 'medicationInventories:id,medication_id,active_ingredient')->find($id);
    }


    public function update($id, array $data)
    {
        $med = Medication::findOrFail($id);
        if ($med) {
            $med->update($data);
            return $med;
        }
    }

    public function delete($id)
    {
        $med = Medication::findOrFail($id);
        return $med->delete();
    }
}
