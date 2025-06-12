<?php

namespace App\Repositories\Vendor;

use App\Helpers\ExportHelper;
use App\Models\Vendor;

class VendorRepository implements VendorInterface
{
    /**
     * Retrieve a collection of Vendor from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */


    public function all(array $filters = [], ?string $export = null)
    {
        $query = Vendor::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('vendor_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('contact_person', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone_number', 'like', '%' . $filters['search'] . '%');
            });
        }

        $transform = fn($vendor) => [
            'Vendor Name'     => $vendor->vendor_name,
            'Contact Person'  => $vendor->contact_person,
            'Phone Number'    => $vendor->phone_number,
            'Email Address'   => $vendor->email,
            'Address'         => $vendor->address,
            'Registration No' => $vendor->reg,
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
        $record = Vendor::findOrFail($id);
        $record->update($data);
        return $record;
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
}
