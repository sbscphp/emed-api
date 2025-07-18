<?php

namespace App\Services\ServiceDepartment;

use App\Enums\ListModuleEnums;
use App\Helpers\GeneralHelper;
use App\Models\ServiceDepartment;
use App\Models\ServiceUnit;
use Carbon\Carbon;
use App\Repositories\ServiceDepartment\ServiceDepartmentInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Class ServiceDepartmentService
 * 
 * This class provides services related to ServiceDepartment operations and acts as a 
 * layer between the Controller and the ServiceDepartmentRepository.
 */
class ServiceDepartmentService
{
    protected ServiceDepartmentInterface $ServiceDepartmentInterface;
    /**
     * ServiceDepartment constructor.
     * 
     * @param ServiceDepartmentInterface $ServiceDepartmentInterface
     */
    public function __construct(ServiceDepartmentInterface $ServiceDepartmentInterface)
    {
        $this->ServiceDepartmentInterface = $ServiceDepartmentInterface;
    }

    /**
     * Retrieve all ServiceDepartment.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->ServiceDepartmentInterface->all();
    }

    /**
     * Create a new ServiceDepartment using the data provided.
     * 
     * @param array $data
     * @return \App\Models\ServiceDepartment
     */
    public function create(array $data)
    {
        return $this->ServiceDepartmentInterface->create($data);
    }


    /**
     * Update an existing ServiceDepartment with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function update(array $data, $id)
    {
        return $this->ServiceDepartmentInterface->update($data, $id);
    }


    /**
     * Delete a ServiceDepartment by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->ServiceDepartmentInterface->delete($id);
    }


    /**
     * Find a ServiceDepartment by their ID.
     * 
     * @param int $id
     * @return \App\Models\ServiceDepartment
     */
    public function find($id)
    {
        return $this->ServiceDepartmentInterface->find($id);
    }


    /**
     * Find an existing ServiceDepartment  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\ServiceDepartment
     */
    public function findByAttribute($attr, $value)
    {
        return $this->ServiceDepartmentInterface->findByAttribute($attr, $value);
    }

    public function getUnits(array $columns = ['*'], $service_units_name, $from, $to)
    {
        return ServiceUnit::select($columns)->when($service_units_name, function ($query, $service_units_name) {
            return $query->where('name', $service_units_name);
        })
            ->when(!empty($from) && !empty($to), function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [Carbon::parse($from), Carbon::parse($to)]);
            })
            ->get();
    }

    public function getTypes(array $columns = ['*'], $service_types_name, $from, $to)
    {
        return ServiceDepartment::select($columns)->when($service_types_name, function ($query, $service_types_name) {
            return $query->where('name', $service_types_name);
        })
            ->when(!empty($from) && !empty($to), function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [Carbon::parse($from), Carbon::parse($to)]);
            })
            ->get();
    }

    public function create_service($data)
    {

        $service =  ServiceDepartment::create($data);
        $user = Auth::user();
        $dataToLog = [
            'causer_id' => $user->id,
            'action_id' => $service->id,
            'action' => 'Create',
            'action_type' => "Models\ServiceDepartment",
            'log_name' => " record created successfully",
            'description' => "{$user->firstname} {$user->lastname} created a Service: {$service->name}",
            'module_accessed' => ListModuleEnums::Service
        ];
        GeneralHelper::storeAuditLog($dataToLog);
        return $service;
    }

    public function editservice($validated)
    {
        $service = ServiceDepartment::find($validated['id']);
        if ($service) {
            $user = Auth::user();
            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $service->id,
                'action' => 'Create',
                'action_type' => "Models\ServiceDepartment",
                'log_name' => " record Edited successfully",
                'description' => "{$user->firstname} {$user->lastname} Edited a Service: {$service->name}",
                'module_accessed' => ListModuleEnums::Service
            ];
            GeneralHelper::storeAuditLog($dataToLog);
            return $service->update([
                "name" => $validated['name']
            ]);
        }
    }
}
