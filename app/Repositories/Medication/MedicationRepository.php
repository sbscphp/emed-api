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
        $filters  =  $request;
        $query = Medication::on('tenant')->with('pharmacy:id,name')
            ->when(!empty($request['search']), function ($query) use ($request) {
                $query->where('generic_name', 'like', "%{$request['search']}%")
                    ->orWhere('medicine_name', 'like', "%{$request['search']}%")
                    ->orWhere('medicine_type', 'like', "%{$request['search']}%")
                    ->orWhere('medicine_status', 'like', "%{$request['search']}%")
                    ->orWhere('manufacturer', 'like', "%{$request['search']}%");
            });





        // foreach ($request as $key => $value) {
        //     if (in_array($key, ['from', 'to']) || empty($value)) {
        //         continue;
        //     }

        //     $query->where($key, 'like', "%$value%");
        // }


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
            $query->where('medicine_status',  $request['medicine_status']);
        }


        //    'generic_name' => "nullable|string",
        //         'brand_name' => "nullable|string",
        //         'medicine_name' => "nullable|string",
        //         'medicine_type' => "nullable|string",
        //         'medicine_status' => "nullable|string",
        //         'from' => "nullable|date",
        //         'to' => "nullable|date",


        if (!empty($request['from']) && !empty($request['to'])) {
            $from = Carbon::parse($request['from'])->startOfDay();
            $to = Carbon::parse($request['to'])->endOfDay();
            $query->whereBetween('created_at', [$from, $to]);
        }




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
