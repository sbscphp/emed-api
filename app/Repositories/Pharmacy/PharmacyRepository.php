<?php

namespace App\Repositories\Pharmacy;

use App\Models\Pharmacy;

class PharmacyRepository implements PharmacyInterface
{
    /**
     * Retrieve a collection of Pharmacy from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    // public function all()
    // {
    //     return Pharmacy::with(['state:id,state_name', 'pharmacist:id,fullname,email'])->paginate(10);
    // }

    public function all($request = null)
    {
        $export = false;
        $filters = [];

        if ($request && method_exists($request, 'has')) {
            $export = $request->has('export');
            $filters = $request->only([
                'pharmacy_name',
                'patient_name',
                'drug',
                'patient_status',
            ]);
        }

        $query = Pharmacy::with([
            'state:id,state_name',
            'pharmacist:id,fullname,email',
            'treatments' => function ($q) use ($filters) {
                $q->with('patient');

                if (!empty($filters['drug'])) {
                    $q->where('drug', 'like', '%' . $filters['drug'] . '%');
                }
            }
        ]);

        if (!empty($filters['pharmacy_name'])) {
            $query->where('name', 'like', '%' . $filters['pharmacy_name'] . '%');
        }

        if (!empty($filters['patient_name']) || !empty($filters['patient_status'])) {
            $query->whereHas('treatments.patient', function ($q) use ($filters) {
                if (!empty($filters['patient_name'])) {
                    $q->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ['%' . $filters['patient_name'] . '%']);
                }
                if (!empty($filters['patient_status'])) {
                    $q->where('status', $filters['patient_status']);
                }
            });
        }

        return $export ? $query->get() : $query->paginate(10);
    }


    /**
     * Create new Pharmacy in the database.
     * 
     * @param array $data
     * @return \App\Models\Pharmacy
     */
    public function create(array $data)
    {
        return Pharmacy::create($data);
    }


    /**
     * Update an existing Pharmacy in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function update(array $data, $id)
    {
        $record = Pharmacy::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Pharmacy from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Pharmacy::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Pharmacy in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function find($id)
    {
        return Pharmacy::with([
            'state:id,state_name',
            'pharmacist:id,fullname,email',
            'treatments.patient' => function ($query) {
                $query->select('id', 'firstname', 'lastname', 'patientno', 'status');
            }
        ])->find($id);
    }



    /**
     * Find an existing Pharmacy in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Pharmacy
     */
    public function findByAttribute($attr, $value)
    {
        return Pharmacy::where($attr, $value)->first();
    }
}
