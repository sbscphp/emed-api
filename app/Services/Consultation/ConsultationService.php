<?php

namespace App\Services\Consultation;

use App\Repositories\Consultation\ConsultationInterface;

/**
 * Class ConsultationService
 *
 * This class provides services related to Consultation operations and acts as a
 * layer between the Controller and the ConsultationRepository.
 */
class ConsultationService
{
    protected ConsultationInterface $ConsultationInterface;
    /**
     * Consultation constructor.
     *
     * @param ConsultationInterface $ConsultationInterface
     */
    public function __construct(ConsultationInterface $ConsultationInterface)
    {
        $this->ConsultationInterface = $ConsultationInterface;
    }

    /**
     * Retrieve all Consultation.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->ConsultationInterface->all();
    }

    /**
     * Create a new Consultation using the data provided.
     *
     * @param array $data
     * @return \App\Models\Consultation
     */
    public function create(array $data)
    {
        return $this->ConsultationInterface->create($data);
    }


    /**
     * Update an existing Consultation with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function update(array $data, $id)
    {
        return $this->ConsultationInterface->update($data, $id);
    }


    /**
     * Delete a Consultation by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->ConsultationInterface->delete($id);
    }


    /**
     * Find a Consultation by their ID.
     *
     * @param int $id
     * @return \App\Models\Consultation
     */
    public function find($id)
    {
        return $this->ConsultationInterface->find($id);
    }


    /**
     * Find an existing Consultation  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Consultation
     */
    public function findByAttribute($attr, $value)
    {
        return $this->ConsultationInterface->findByAttribute($attr, $value);
    }

    public function getPatients(){
        return $this->ConsultationInterface->getPatients();
    }
}
