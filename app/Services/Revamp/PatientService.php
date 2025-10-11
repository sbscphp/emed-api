<?php

namespace App\Services\Revamp;

use App\Enums\GeneralEnums;
use App\Enums\ListModuleEnums;
use App\Enums\PatientVisitStageEnums;
use App\Enums\PatientVisitStatusEnums;
use App\Models\Patient;
use App\Repositories\Patient\PatientInterface;
use App\Helpers\ExportHelper;
use App\Helpers\GeneralHelper;
use App\Models\BillingLog;
use App\Models\BillingLogDetail;
use App\Models\EmergencyContact;
use App\Models\NextOfKin;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\ServiceUnit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Spatie\Multitenancy\Models\Tenant;

/**
 * Class PatientService
 *
 * This class provides services related to Patient operations and acts as a
 * layer between the Controller and the PatientRepository.
 */
class PatientService
{
    /**
     * Patient constructor.
     *
     * @param PatientInterface $PatientInterface
     */
    public function __construct(PatientInterface $PatientInterface) {}

    /**
     * Retrieve all Patient.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function overview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = Patient::query()
            ->where('tenant_id', $tenantId)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('firstname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('lastname', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('patientno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhere('cardno', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['status']), function ($query) use ($request) {
                $query->where('status', $request['status']);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'alphabetically', function ($query) {
                $query->orderBy('firstname', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('created_at', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('created_at', 'DESC');
            })
            ->with('nextOfKin', 'emergencyContact', 'visits_recent');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function stats($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }
        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');
        $query = Patient::query()->where('tenant_id', $tenantId);
        $total = (clone $query)->count();
        $patientLog = (clone $query)->count();
        $admitted = (clone $query)->where('status', GeneralEnums::ADMITTED->value)->count();
        $patientVisitToday = PatientVisit::where('tenant_id', $tenantId)->whereDate('created_at', now()->toDateString())->count();
        $followUpPatient = (clone $query)->where('reg_status', GeneralEnums::FOLLOWUPPATIENT->value)->count();

        return [
            'totalPatient' => $total,
            'admitted' => $admitted,
            'totalPatientVisitToday' => $patientVisitToday,
            'followUpPatient' => $followUpPatient,
            'patientLog' => $patientLog,
        ];
    }

    public function export($records, $format)
    {
        $exportData = $records->map(function ($patient) {
            return [
                'Patient Card'      => $patient->cardno,
                'First Name'      => $patient->firstname,
                'Last Name'       => $patient->lastname,
                'Patient No'       => $patient->patientno,
                'Email'           => $patient->email,
                'Phone Number'    => $patient->phoneno,
                'Patient Status'  => $patient->status,
                'Registered Date' => $patient->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients_export.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = PDF::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients_export.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    /**
     * Create a new Patient using the data provided.
     *
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create($request)
    {
        try {

            $currentUser = Auth::user();

            $tenant = Tenant::current(); //Retrieve the current tenant
            $tenantDomain = $tenant ? $tenant->domain : 'emed'; // Current tenant domain name
            $tenantAcronym = $this->generateAcronym($tenantDomain); //Acronym for the hospital name()
            $tenantId = $request->header('X-Tenant-ID');
            // Create Patient
            $patient = Patient::create([
                'tenant_id' => $tenantId,
                'created_by' => $currentUser->id,
                'patientno' => 'EMED/' . GeneralHelper::generateUniqueRandomId($request->firstname) . '/' . GeneralHelper::generateUniqueRandomId($request->lastname) . '/' . $tenantAcronym,
                'firstname' => $request->firstname,
                'lastname' => $request->lastname,
                'dob' => $request->dob,
                'phoneno' => $request->phoneno,
                'age' => $request->age,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'email' => $request->email,
                'lga' => $request->lga,
                'stateoforigin' => $request->stateoforigin,
                'homeaddress' => $request->homeaddress,
                'occupation' => $request->occupation,
                'religion' => $request->religion,
                'tribe' => $request->tribe,
                'bloodgroup' => $request->bloodgroup,
                'cardno' => $request->cardno,
                'genotype' => $request->genotype,
                'referral' => $request->referral,
            ]);

            // create next of kin
            $nextOfKin = NextOfKin::create([
                'patient_id' => $patient->id,
                'firstname' => $request->nokfirstname,
                'lastname' => $request->noklastname,
                'gender' => $request->nokgender,
                'phoneno' => $request->nokphoneno,
                'stateoforigin' => $request->nokstateoforigin,
                'lga' => $request->noklga,
                'homeaddress' => $request->nokhomeaddress,
                'relationship' => $request->nokrelationship,
            ]);

            if (isset($requestrequest->same_nok_emergency) && $request->same_nok_emergency == true) {
                // create emergency contact same as next of kin
                $emergencyyContact = EmergencyContact::create([
                    'patient_id' => $patient->id,
                    'firstname' => $request->nokfirstname,
                    'lastname' => $request->noklastname,
                    'gender' => $request->nokgender,
                    'phoneno' => $request->nokphoneno,
                    'stateoforigin' => $request->nokstateoforigin,
                    'lga' => $request->noklga,
                    'homeaddress' => $request->nokhomeaddress,
                    'relationship' => $request->nokrelationship,
                ]);
            } else {
                // create emergency contact
                $emergencyyContact = EmergencyContact::create([
                    'patient_id' => $patient->id,
                    'firstname' => $request->emgfirstname,
                    'lastname' => $request->emglastname,
                    'gender' => $request->emggender,
                    'phoneno' => $request->emgphoneno,
                    'stateoforigin' => $request->emgstateoforigin,
                    'lga' => $request->emglga,
                    'homeaddress' => $request->emghomeaddress,
                    'relationship' => $request->emgrelationship,
                ]);
            }

            return $patient;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update an existing Patient with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update($request, $id)
    {
        try {

            $currentUser = Auth::user();
            $patient = Patient::find($id);
            // Update Patient
            $patient->update([
                'updated_by' => $currentUser->id,
                'phoneno' => $request->phoneno,
                'email' => $request->email,
                'dob' => $request->dob,
                "age" =>  $request->age,
                'gender' => $request->gender,
                'genotype' => $request->genotype,
                'bloodgroup' => $request->bloodgroup,
                'marital_status' => $request->marital_status,
                'tribe' => $request->tribe,
                'homeaddress' => $request->homeaddress,
                'occupation' => $request->occupation,
                'stateoforigin' => $request->stateoforigin,
                'lga' => $request->lga,
                'referral' => $request->referral,
            ]);

            // Update next of kin
            $nextOfKin = NextOfKin::where('patient_id', $id)->first();
            $nextOfKin->update([
                'firstname' => $request->nokfirstname,
                'lastname' => $request->noklastname,
                'gender' => $request->nokgender,
                'phoneno' => $request->nokphoneno,
                'stateoforigin' => $request->nokstateoforigin,
                'lga' => $request->noklga,
                'homeaddress' => $request->nokhomeaddress,
                'relationship' => $request->nokrelationship,
            ]);

            // update emergency contact
            $emergencyyContact = EmergencyContact::where('patient_id', $id)->first();
            $emergencyyContact->update([
                'firstname' => $request->emgfirstname,
                'lastname' => $request->emglastname,
                'gender' => $request->emggender,
                'phoneno' => $request->emgphoneno,
                'stateoforigin' => $request->emgstateoforigin,
                'lga' => $request->emglga,
                'homeaddress' => $request->emghomeaddress,
                'relationship' => $request->emgrelationship,
            ]);

            return $patient->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function patientVisitOverview($request)
    {
        $customDate = [];
        if ($request->period === 'custom date' && $request->start_date && $request->end_date) {
            $customDate = [$request->start_date, $request->end_date];
        }

        $dateFilter = GeneralHelper::dateFilter($request->period, $customDate);
        $tenantId = $request->header('X-Tenant-ID');

        $records = PatientVisit::query()
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $request->patient_id)
            ->when(!empty($request['search_param']), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('visitno', 'LIKE', '%' . $request['search_param'] . '%')
                        ->orWhereRelation('service', 'name', 'LIKE', '%' . $request['search_param'] . '%');
                });
            })
            ->when(!empty($request['payment_status']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_status', $request['payment_status']);
            })
            ->when(!empty($request['payment_method']), function ($query) use ($request) {
                $query->whereRelation('patientBilling', 'payment_method', $request['payment_method']);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', $dateFilter);
            })->when(($request['sort_by'] ?? null) === 'date_ascending', function ($query) {
                $query->orderBy('arrival_date', 'ASC');
            })->when(($request['sort_by'] ?? null) === 'date_descending', function ($query) {
                $query->orderBy('arrival_date', 'DESC');
            })
            ->with('patient', 'service', 'patientBilling', 'consultation');

        if (!empty($request['paginate']) && empty($request['export'])) {
            return $records->orderBy('id', 'DESC')->paginate($request['limit'] ?? 15);
        }

        return $records->orderBy('id', 'DESC')->get();
    }

    public function patientVisitExport($records, $format)
    {
        $exportData = $records->map(function ($visit) {
            return [
                'Date'      => $visit->created_at->format('Y-m-d H:i'),
                'Visit No'       => $visit->visitno,
                'Service Type'           => $visit->service->name ?? 'N/A',
                'Payment Status'    => $visit->patientBilling->payment_status ?? 'N/A',
                'Payment Type'  => $visit->patientBilling->payment_method ?? 'N/A',
            ];
        })->toArray();

        if (empty($exportData)) {
            throw new \Exception("No records found for export.");
        }

        // Choose export format
        if (strtolower($format) === 'csv') {
            return ExportHelper::streamCsv($exportData, null, 'patients_visits.csv');
        }

        if (strtolower($format) === 'pdf') {
            $pdf = PDF::loadView('exports.patients', ['patients' => $exportData])
                ->setPaper('A1', 'landscape');

            return $pdf->download('patients_visits.pdf');
        }

        throw new \Exception("Invalid export format.");
    }

    public function initiateVisit($request)
    {
        try {

            $currentUser = Auth::user();
            $tenantId = $request->header('X-Tenant-ID');
            $service = Service::find($request->service_id);
            if (empty($service)) {
                throw new \Exception("Service not found.");
            }

            $serviceUnit = ServiceUnit::where('name', 'Registration')->first();
            if (empty($service)) {
                throw new \Exception("Registration service unit not found.");
            }
            // Find patient
            $patient = Patient::find($request->patient_id);
            // Initiate Patient visit
            $patientVisit = PatientVisit::create([
                'tenant_id' => $tenantId,
                'initiated_by' => $currentUser->id,
                'visitno' => 'VIS' . GeneralHelper::generateUniqueRandomId($patient->firstname),
                'patient_id' => $patient->id,
                'service_id' => $request->service_id,
                // 'stage' => PatientVisitStageEnums::VISIT,
                'arrival_date' => now(),
                'status' => PatientVisitStatusEnums::VISIT_INITIATED->value,
            ]);

            $invoiceNumber = GeneralHelper::getModelUniqueOrderlyId([
                'modelNamespace' => BillingLog::class,
                'modelField' => 'invoice_number',
                'prefix' => 'INV-',
                'idLength' => 6,
            ]);

            //store billing info
            $patientBilling = BillingLog::create([
                'tenant_id' => $tenantId,
                'updated_by' => $currentUser->id,
                'visit_id' => $patientVisit->id,
                'patient_id' => $patient->id,
                'invoice_number' => $invoiceNumber,
                'patient_name' => $patient->firstname . ' ' . $patient->lastname,
                'billing_date' => now(),
                'service_type_id' => $request->service_id,
                'service_unit_id' => $serviceUnit->id,
                'grand_total' => $service->price
            ]);

            //update billing log details
            BillingLogDetail::create([
                'tenant_id'        => $tenantId,
                'billing_id' => $patientBilling->id,
                'service_unit_id' => $serviceUnit->id,
                'item_name' => $service->name,
                'quantity' => 1,
                'amount' => $service->price
            ]);

            // update patient registaration staus
            // $patient->update([
            //     'reg_status' => GeneralEnums::FOLLOWUPPATIENT->value,
            // ]);

            return $patientVisit;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Delete a Patient by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete(Patient $patientExists)
    {
        // Delete related patient next of kin details
        $patientExists->nextOfKin()->delete();

        // Delete related patient emergency contacts
        $patientExists->emergencyContact()->delete();

        // Finally delete the patient record
        $patientExists->delete();
    }

    public function generateAcronym($name)
    {
        // Trim any leading or trailing spaces
        $name = trim($name);

        // Get the first two letters of the name
        $firstTwoLetters = substr($name, 0, 2);

        // Convert to uppercase and append 'H'
        $acronym = strtoupper($firstTwoLetters) . 'H';

        return $acronym;
    }
}
