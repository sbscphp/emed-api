<?php

namespace App\Services\Revamp;

use App\Models\ServiceCategory;

/**
 * Class ServiceCategoryService
 *
 * This class provides services related to Service Category operations.
 */
class ServiceCategoryService
{

    /**
     * Retrieve all Service Categories.
     */
    public function overview($request)
    {
        $query = ServiceCategory::query()
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request['search_param'] . '%');
            })
            ->when(isset($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            });

        if (!empty($request['paginate'])) {
            return $query->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $total = ServiceCategory::count();
        $active = ServiceCategory::where('status', true)->count();
        $inactive = ServiceCategory::where('status', false)->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    public function create($data)
    {
        return ServiceCategory::create($data);
    }

    public function update($id, $data)
    {
        $category = ServiceCategory::findOrFail($id);
        $category->update($data);
        return $category;
    }

    public function delete($id)
    {
        $category = ServiceCategory::findOrFail($id);
        $category->delete();
        return $category;
    }

    public function export($records, $format)
    {
        // Implement export if needed
        return $records;
    }
}
