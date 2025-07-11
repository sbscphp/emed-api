<?php

namespace App\Services\Patient;

use App\Models\Patient;
use App\Repositories\Patient\PatientInterface;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Helpers\ExportHelper;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Resources\PatientResourceExport;
use App\Exports\PatientExport;
use OzdemirBurak\JsonCsv\File\Json;
use OzdemirBurak\JsonCsv\File\Csv;

/**
 * Class PatientService
 *
 * This class provides services related to Patient operations and acts as a
 * layer between the Controller and the PatientRepository.
 */
class PatientService
{
    protected PatientInterface $PatientInterface;
    /**
     * Patient constructor.
     *
     * @param PatientInterface $PatientInterface
     */
    public function __construct(PatientInterface $PatientInterface)
    {
        $this->PatientInterface = $PatientInterface;
    }

    /**
     * Retrieve all Patient.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return $this->PatientInterface->all();
    }

    /**
     * Create a new Patient using the data provided.
     *
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create(array $data)
    {
        return $this->PatientInterface->create($data);
    }


    /**
     * Update an existing Patient with the provided data.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update(array $data, $id)
    {
        return $this->PatientInterface->update($data, $id);
    }


    /**
     * Delete a Patient by heir ID.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        return $this->PatientInterface->delete($id);
    }


    /**
     * Find a Patient by their ID.
     *
     * @param int $id
     * @return \App\Models\Patient
     */
    public function find($id)
    {
        return $this->PatientInterface->find($id);
    }


    /**
     * Find an existing Patient  by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Patient
     */
    public function findByAttribute($attr, $value)
    {
        return $this->PatientInterface->findByAttribute($attr, $value);
    }

    public function findByMultiAttributes(array $attrs)
    {
        return $this->PatientInterface->findByMultiAttributes($attrs);
    }

    public function findMultipleRecordsByMultiAttributes(array $attrs)
    {
        return $this->PatientInterface->findMultipleRecordsByMultiAttributes($attrs);
    }

    public function findUserByFirstnameAndLastname($firstname, $lastname)
    {
        return $this->PatientInterface->findUserByFirstnameAndLastname($firstname, $lastname);
    }

    /**
     * Retrieve all records
     *
     * @return \App\Models\Patient
     */
    public function getAllRecords($search, $paginate, $perPage)
    {
        return $this->PatientInterface->getAllRecords($search, $paginate, $perPage);
    }

    /*
    * Retrieve record stats
    *
    * @return \App\Models\Patient
    */
    public function getRecordStats()
    {
        return $this->PatientInterface->getRecordStats();
    }

    public function getPatientReport($request)
    {
        return $this->PatientInterface->getPatientReport($request);
    }

    public function getAllRecordFiltered($search = null, $paginate = false, $perPage = 10, $from, $to, $export, $gender, $status, $patient_type)
    {
        $query = Patient::with(['service', 'visits_recent']);

        if ($search) {
            // status
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%")
                    ->orWhere('middlename', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phoneno', 'like', "%$search%")
                    ->orWhere('patientno', 'like', "%$search%")
                    ->orWhere('cardno', 'like', "%$search%")
                    ->orWhere('occupation', 'like', "%$search%")
                    ->orWhere('homeaddress', 'like', "%$search%")
                    ->orWhere('gender', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%");
            });
        }
        if (!empty($gender)) {
            $query->where('gender', $gender);
        }

        if (!empty($status)) {
            $query->where('status',  $status);
        }

        // patient_type

        if (!empty($patient_type)) {
            $query->where('patient_type',  $patient_type);
        }

        if (!empty($export)) {
            if ($export == 'pdf') {
                return ExportHelper::downloadPdf(Patient::get()->toArray(),  'patient.pdf');
            } else if ($export == 'csv') {
                //  return ExportHelper::streamCsv(Patient::get()->toArray(), null, 'patient.csv');

                $patients = Patient::with(['service', 'visits_recent'])->get();

                $data = PatientResourceExport::collection($patients)->resolve();

                // Sanitize data to avoid malformed UTF-8 characters
                $data = array_map(function ($item) {
                    return array_map(function ($value) {
                        return is_string($value) ? mb_convert_encoding($value, 'UTF-8', 'UTF-8') : $value;
                    }, $item);
                }, $data);

                return ExportHelper::streamCsv($data, null, 'patient_' . now()->format('Ymd_His') . '.csv');

                //return Excel::download(new PatientExport($patient), 'patients.xlsx');
                // return Excel::download(Patient::get()->toArray(), 'users.xlsx');

                //return ExportHelper::streamCsv($data);
                // $csv = new Csv($data);

                // return ExportHelper::streamCsv($data, null, 'patient_' . now()->format('Ymd_His') . '.csv');
            }
        }

        $query->when($from && $to, function ($q) use ($from, $to) {
            $q->whereBetween('created_at', [Carbon::parse($from), Carbon::parse($to)]);
        });

        if ($paginate) {
            return $query->latest()->paginate($perPage);
        }

        return $query->latest()->get();
    }

    /**
     * Get patient export data.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getExportData($search = null, $startDate = null, $endDate = null): array
    {
        $query = Patient::select([
            'firstname',
            'lastname',
            'email',
            'phoneno',
            'patientno',
            'created_at',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%$search%")
                    ->orWhere('lastname', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->latest()->get()->map(function ($patient) {
            return [
                'First Name'      => $patient->firstname,
                'Last Name'       => $patient->lastname,
                'Email'           => $patient->email,
                'Phone Number'    => $patient->phoneno,
                'Patient Number'  => $patient->patientno,
                'Registered Date' => $patient->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();
    }

    public function updateNewToExisting(): void
    {
        Patient::where('patient_type', 'new')->update(['patient_type' => 'existing']);
    }
}
