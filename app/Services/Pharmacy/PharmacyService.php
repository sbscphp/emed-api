<?php

namespace App\Services\Pharmacy;

use App\Models\Medication;
use App\Models\MedicationInventory;
use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\PharmacySupply;
use App\Models\Treatment;
use App\Repositories\Pharmacy\PharmacyInterface;

/**
 * Class PharmacyService
 * 
 * This class provides services related to Pharmacy operations and acts as a 
 * layer between the Controller and the PharmacyRepository.
 */
class PharmacyService
{
    protected PharmacyInterface $PharmacyInterface;
    /**
     * Pharmacy constructor.
     * 
     * @param PharmacyInterface $PharmacyInterface
     */
    public function __construct(PharmacyInterface $PharmacyInterface)
    {
        $this->PharmacyInterface = $PharmacyInterface;
    }

    /**
     * Retrieve all Pharmacy.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->PharmacyInterface->all();
    }

    public function treatmentLogall()
    {
        return $this->PharmacyInterface->treatmentLogall();
    }

    public function getPatientTreatmentWithDetails($patientId)
    {
        return $this->PharmacyInterface->getPatientTreatmentDetails($patientId);
    }

    public function fulfillPrescription(array $data)
    {
        return $this->PharmacyInterface->fulfillPrescription($data);
    }



    /**
     * Create a new Pharmacy using the data provided.
     * 
     * @param array $data
     * @return \App\Models\Pharmacy
     */
    public function create(array $data)
    {
        return $this->PharmacyInterface->create($data);
    }


    /**
     * Update an existing Pharmacy with the provided data.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function update(array $data, $id)
    {
        return $this->PharmacyInterface->update($data, $id);
    }


    /**
     * Delete a Pharmacy by heir ID.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PharmacyInterface->delete($id);
    }


    /**
     * Find a Pharmacy by their ID.
     * 
     * @param int $id
     * @return \App\Models\Pharmacy
     */
    public function find($id)
    {
        return $this->PharmacyInterface->find($id);
    }


    /**
     * Find an existing Pharmacy  by their $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\Pharmacy
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PharmacyInterface->findByAttribute($attr, $value);
    }

    public function getDashboardStats(): array
    {
        return [
            'total_medications' => Medication::count(),
            'available_medications' => Medication::where('medicine_status', 'available')->count(),
            'prescriptions_fulfilled_today' => Treatment::whereDate('updated_at', now())
                ->whereNotNull('receiptno')
                ->count(),
            'total_supply' => PharmacySupply::count(),
            'total_request' => PharmacyRequest::count(),
            'total_pharmacies' => Pharmacy::count(),
        ];
    }

    public function getMedicineDashboardStats(): array
    {
        return [
            'total_medications' => Medication::count(),
            'total_supply' => PharmacySupply::count(),
            'near_expiry_medications' => MedicationInventory::whereBetween('expiry_date', [now(), now()->addDays(30)])
                ->distinct('medication_id')
                ->count('medication_id'),
            'low_stock_alert' => MedicationInventory::where('current_stock', '<', 10)->count(),
        ];
    }

    public function generatePharmacyId(): string
    {
        $latest = Pharmacy::latest('id')->first();
        $nextNumber = $latest ? $latest->id + 1 : 1;
        return 'PHA-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
