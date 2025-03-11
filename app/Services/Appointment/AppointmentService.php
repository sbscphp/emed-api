<?php

namespace App\Services\Appointment;

use App\Repositories\Appointment\AppointmentInterface;

/**
 * Class AppointmentService
 * 
 * This class provides services related to Appointment operations and acts as a 
 * layer between the Controller and the AppointmentRepository.
 */
class AppointmentService
{
    protected AppointmentInterface $AppointmentInterface;
    /**
     * Appointment constructor.
     * 
     * @param AppointmentInterface $AppointmentInterface
     */
    public function __construct(AppointmentInterface $AppointmentInterface)
    {
        $this->AppointmentInterface = $AppointmentInterface;
    }

    /**
     * Retrieve all Appointment.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->AppointmentInterface->all();
    }

    /**
     * Create a new Appointment using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Appointment
     */
    public function create(array $data)
    {
        return $this->AppointmentInterface->create($data);
    }


    /**
     * Update an existing Appointment with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Appointment
     */
    public function update(array $data, $id)
    {
        return $this->AppointmentInterface->update($data, $id);
    }


    /**
     * Delete a Appointment by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->AppointmentInterface->delete($id);
    }


    /**
     * Find a Appointment by their ID.
     * 
     * @param int $id
     * @return \App\Models\Appointment
     */
    public function find($id)
    {
        return $this->AppointmentInterface->find($id);
    }


    /**
     * Find an existing Appointment  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Appointment
     */
    public function findByAttribute($attr, $value)
    {
        return $this->AppointmentInterface->findByAttribute($attr, $value);
    }
}
