<?php

namespace App\Repositories\Vendor;

use App\Enums\ListModuleEnums;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class VendorRepository implements VendorInterface
{
    /**
     * Retrieve a collection of Vendor from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */


    public function all(array $filters = [], ?string $export = null, $from, $to)
    {
        $query = Vendor::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('vendor_name', 'like', "%{$filters['search']}%")
                    ->orWhere('contact_person', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                    ->orWhere('phone_number', 'like', "%{$filters['search']}%")
                    ->orWhere('status', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['vendor_name'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('vendor_name', 'like', "%{$filters['vendor_name']}%");
            });
        }


        if (!empty($filters['contact_person'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('contact_person', 'like', "%{$filters['contact_person']}");
            });
        }

        if (!empty($filters['email'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('email', 'like', "%{$filters['email']}%");
            });
        }

        if (!empty($filters['phone_number'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('phone_number', 'like', "%{$filters['phone_number']}%");
            });
        }


        if (!empty($filters['category'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('category', 'like', "%{$filters['category']}%");
            });
        }


        // status

        if (!empty($filters['status'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        if (!empty($from) && !empty($to)) {
            $query->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay()
            ]);
        }

        $transform = fn($vendor) => [
            'id'     => $vendor->id,
            'VendorName'     => $vendor->vendor_name,
            'ContactPerson'  => $vendor->contact_person,
            'PhoneNumber'    => $vendor->phone_number,
            'EmailAddress'   => $vendor->email,
            'Address'         => $vendor->address,
            'RegistrationNo' => $vendor->reg,
            'Status'          => ucfirst($vendor->status),
            'Created At'      => $vendor->created_at->toDateTimeString(),
        ];

        // Handle export
        if ($export) {
            $data = $query->latest()->get()->map($transform);

            if ($export === 'csv') {
                return ExportHelper::streamCsv($data->toArray(), null, 'vendors.csv');
            }

            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($data->toArray(), 'vendors.pdf');
            }

            throw new \InvalidArgumentException('Invalid export format specified');
        }

        // Return all if type = all
        if (isset($filters['type']) && $filters['type'] === 'all') {
            return $query->latest()->get();
        }

        // Default paginated response
        $paginated = $query->latest()->paginate(10);
        $paginated->getCollection()->transform($transform);

        return $paginated;
    }




    /**
     * Create new Vendor in the database.
     * 
     * @param array $data
     * @return \App\Models\Vendor
     */
    public function create(array $data)
    {
        return Vendor::create($data);
    }


    /**
     * Update an existing Vendor in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Vendor
     */
    public function update(array $data, $id)
    {
        DB::connection('tenant')->beginTransaction();
        $currentUser = Auth::user();
        $record = Vendor::find(intval($id));

        if ($record) {
            $oldData = $record->toArray(); // Get the current state before update

            // Apply updates
            $record->vendor_name = $data['vendor_name'];
            $record->contact_person = $data['contact_person'];
            $record->email = $data['email'];
            $record->address = $data['address'];
            $record->phone_number = $data['phone_number'];
            $record->registration_no = $data['registration_no'];
            $record->category = $data['category'];
            $record->status = $data['status'];
            $record->category = $data['category'];
            $record->save();

            $newData = $record->toArray(); // Get the new state after update

            GeneralHelper::storeAuditLog([
                'causer_id' => $currentUser->id,
                'action_id' => $id,
                'action' => 'Update',
                'action_type' => "Models\\Vendor",
                'log_name' => "Vendor updated",
                'old_data' => $oldData,
                'new_data' => $newData,
                'description' => "{$currentUser->firstname} {$currentUser->lastname} updated vendor: {$record->vendor_name}",
                'module_accessed' => ListModuleEnums::Records
            ]);

            DB::connection('tenant')->commit();
            return $record;
        } else {
            DB::connection('tenant')->rollBack();
            return null;
        }
    }



    /**
     * Delete an existing Vendor from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Vendor::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Vendor in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Vendor
     */
    public function find($id)
    {
        return Vendor::find($id);
    }


    /**
     * Find an existing Vendor in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Vendor
     */
    public function findByAttribute($attr, $value)
    {
        return Vendor::where($attr, $value)->first();
    }

    public function  update_status($validated, $id)
    {
        DB::connection('tenant')->beginTransaction();
        $vendor = Vendor::on('tenant')->find(intval($id));
        if ($vendor) {
            $vendor->update([
                'status' => $validated['status'],
            ]);
            DB::connection('tenant')->commit();
            return $vendor;
        } else {
            DB::connection('tenant')->rollBack();
        }
    }
}
