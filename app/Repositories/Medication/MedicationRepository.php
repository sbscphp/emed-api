<?php

namespace App\Repositories\Medication;

use App\Helpers\ExportHelper;
use App\Models\Medication;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MedicationRepository implements MedicationRepositoryInterface
{

    public function all($request)
    {
        $query = Medication::with('pharmacy:id,name');
        $filters  =  $request;

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                if ($key === 'medicine_status') {
                    // Exact match for status
                    $query->where($key, $value);
                } else {
                    // Partial match for text fields
                    $query->where($key, 'like', '%' . $value . '%');
                }
            }
        }

        $query->when($request['from'] && $request['to'], function ($q) use ($request) {
            $q->whereBetween('created_at', [
                Carbon::parse($request['from'])->startOfDay(),
                Carbon::parse($request['to'])->endOfDay()
            ]);
        });

        if ($request['export']) {
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
                    'Created At' => $med->created_at,
                ];
            });

            if ($request['export'] == 'csv') {
                return ExportHelper::streamCsv($exportData->toArray(), null, 'medications.csv');
            }

            if ($request['export'] == 'pdf') {
                return ExportHelper::downloadPdf($exportData->toArray(), 'medications.pdf');
            }
            return $query->paginate(10);
            // return JsonResponser::send(true, 'Invalid export format specified', null, 400);
        }
        return $query->paginate(10);
    }


    public function create(array $data)
    {
        return Medication::create($data);
    }

    public function find($id)
    {
        return Medication::with('pharmacy')->find($id);
    }


    public function update($id, array $data)
    {
        $med = Medication::findOrFail($id);
        $med->update($data);
        return $med;
    }

    public function delete($id)
    {
        $med = Medication::findOrFail($id);
        return $med->delete();
    }
}
