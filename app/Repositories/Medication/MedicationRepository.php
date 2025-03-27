<?php

namespace App\Repositories\Medication;

use App\Models\Medication;

class MedicationRepository implements MedicationRepositoryInterface
{
    public function all()
    {
        return Medication::with('pharmacy')->paginate(10);
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
