<?php

namespace App\Repositories\Pharmacy;

use App\Models\BillingLog;
use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Treatment;
use App\Models\TreatmentFulfillment;
use App\Responser\JsonResponser;
use Illuminate\Support\Facades\DB;

class PharmacyRepository implements PharmacyInterface
{
    /**
     * Retrieve a collection of Pharmacy from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Pharmacy::with(['state:id,state_name', 'pharmacist:id,fullname,email'])->paginate(10);
    }


    public function treatmentLogall($search = null)
    {


        $query = Treatment::on('tenant')->with([
            'patient:id,firstname,lastname,cardno,patient_type,patientno,status',
            'pharmacy:id,name'
        ]);


        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('drug', 'like', "%{$search}%")
                    ->orWhereHas('pharmacy', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('patient', function ($q3) use ($search) {
                        $q3->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$search}%"])
                            ->orWhere('cardno', 'like', "%{$search}%")
                            ->orWhere('patientno', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%");
                    });
            });
        }

        $treatments = $query->orderBy('created_at', 'desc')->paginate(10);

        foreach ($treatments as $treatment) {
            $billingLog = BillingLog::on('tenant')->where('patient_id', $treatment->patient->id)
                ->latest()
                ->first();

            $treatment->payment_status = $billingLog->payment_status ?? 'pending';
            $treatment->status = $treatment->receiptno ? 'Fulfilled' : 'Not Fulfilled';
        }

        return $query;
    }

    public function getPatientTreatmentDetails($patientId)
    {
        $patient = Patient::with([
            'treatments.pharmacy',
            'triage'
        ])->find($patientId);

        return $patient;
    }

    public function fulfillPrescription(array $data)
    {
        DB::connection('tenant');

        $treatment = Treatment::with('fulfillment', 'patient')->find($data['treatment_id']);

        if (!$treatment) {
            return JsonResponser::send(true, 'Treatment not found.', [], 204);
        }

        if (!is_null($treatment->receiptno)) {
            return JsonResponser::send(true, 'This treatment has already been fulfilled.', [], 422);
        }

        $receiptno = 'RCPT-' . strtoupper(uniqid());
        $treatment->receiptno = $receiptno;
        $treatment->save();

        $patient = $treatment->patient;

        $fulfillment = TreatmentFulfillment::create([
            'treatment_id' => $treatment->id,
            'dispensing_pharmacist' => $data['dispensing_pharmacist'],
            'dispensing_date' => $data['dispensing_date'],
            'quantity_dispensed' => $data['quantity_dispensed'],
            'batch_number' => $data['batch_number'],
            'expiry_date' => $data['expiry_date'],
            'prescription_status' => $data['prescription_status'],
            'payment_status' => $data['payment_status'],
        ]);

        return JsonResponser::send(false, 'Treatment prescription fulfilled successfully.', [
            'receiptno' => $receiptno,
            'fulfillment' => $fulfillment,
        ]);
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
        DB::connection('tenant')->beginTransaction();
        $record = Pharmacy::findOrFail($id);
        if ($record) {
            $record->update($data);
            DB::connection('tenant')->commit();
            return $record;
        } else {
            DB::connection('tenant')->rollBack();
        }
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
            // 'treatments.patient' => function ($query) {
            //     $query->select('id', 'firstname', 'lastname', 'patientno', 'status');
            // }
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

    protected function generateReceiptNumber()
    {
        return 'RX' . strtoupper(uniqid()); // You can customize format
    }
}
