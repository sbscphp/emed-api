<?php

namespace App\Repositories\MedicineType;

use App\Models\MedicineType;
use Carbon\Carbon;

class MedicineTypeRepository implements MedicineTypeInterface
{
    /**
     * Retrieve a collection of MedicineType from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($filters)
    {
        $query = MedicineType::query();

        if (!empty($filters['search'])) {
            $query->where('type_name', 'like', '%' . $filters['search'] . '%');
        }

        if (isset($filters['type']) && $filters['type'] === 'all') {
            return $query->orderByDesc('date_added')->get();
        }

        $query->when(!empty($filters['from']) && !empty($filters['to']), function ($q) use ($filters) {
            $q->whereBetween('date_added', [
                Carbon::parse($filters['from'])->startOfDay(),
                Carbon::parse($filters['to'])->endOfDay()
            ]);
        });

        return $query->orderByDesc('date_added')->paginate(10);
    }


    /**
     * Create new MedicineType in the database.
     * 
     * @param array $data
     * @return \App\Models\MedicineType
     */
    public function create(array $data)
    {
        return MedicineType::create($data);
    }


    /**
     * Update an existing MedicineType in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\MedicineType
     */
    public function update(array $data, $id)
    {
        $record = MedicineType::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing MedicineType from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = MedicineType::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing MedicineType in the database by their ID.
     * 
     * @param int $id
     * @return \App\Models\MedicineType
     */
    public function find($id)
    {
        return MedicineType::find($id);
    }


    /**
     * Find an existing MedicineType in the database by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\MedicineType
     */
    public function findByAttribute($attr, $value)
    {
        return MedicineType::where($attr, $value)->first();
    }
}
