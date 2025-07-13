<?php

namespace App\Services\Patient;

use App\Models\Patient;
use App\Repositories\Patient\PatientInterface;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Helpers\ExportHelper;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use App\Http\Resources\PatientResourceExport;
use App\Exports\PatientExport;
use App\Exports\PatientReportExport;
use App\Models\PatientVisit;
use Illuminate\Support\Facades\Response;

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
        $query = Patient::query();
        //with(['service', 'visits_recent']);

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
            // Get the data with necessary relationships if needed
            $query = Patient::query();

            // Apply any existing filters
            if (!empty($from) && !empty($to)) {
                $query->whereBetween('created_at', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay()
                ]);
            }

            // Get the data as a collection
            $data = $query->latest()->get();

            // Convert to array - the ExportHelper will handle the UTF-8 cleaning
            $exportData = $data->toArray();
            if ($export === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'patient_' . now()->format('Ymd_His') . '.pdf');
                // $data = Patient::all()->toArray();
                // $pdf = Pdf::loadView('reports.patient_report_log', [
                //     'data' => $data
                // ])->setPaper('a3', 'landscape');

                // return Response::make($pdf->output(), 200, [
                //     'Content-Type' => 'application/pdf',
                //     'Content-Disposition' => 'attachment; filename="patient_report_log.pdf"',
                // ]);
                // Use a simpler approach with DomPDF directly
                $data = [];

                // $patients = Patient::select([
                //     'firstname',
                //     'lastname',
                //     'dob',
                //     'age',
                //     'gender',
                //     'bloodgroup',
                //     'genotype',
                //     'email',
                //     'patient_type',
                //     'marital_status',
                //     'phoneno',
                //     'occupation',
                //     'homeaddress',
                //     'companyaddress',
                //     'religion',
                //     'stateoforigin',
                //     'lga',
                //     'tribe',
                //     'cardno',
                //     'status',
                //     'service_id',
                //     'patientno'
                // ])->get()->map(function ($patient) {
                //     return [
                //         "id" => $patient->id,
                //         "firstname" => $patient->firstname,
                //         "lastname" => $patient->lastname,
                //         "dob" => $patient->dob,
                //         "age" => $patient->age,
                //         "gender" => $patient->gender,
                //         "bloodgroup" => $patient->bloodgroup,
                //         "genotype" => $patient->genotype,
                //         "email" => $patient->email,
                //         "patient_type" => $patient->patient_type,
                //         "marital_status" => $patient->marital_status,
                //         "phoneno" => $patient->phoneno,
                //         "occupation" => $patient->occupation,
                //         "homeaddress" => $patient->homeaddress,
                //         "companyaddress" => $patient->companyaddress,
                //         "religion" => $patient->religion,
                //         "stateoforigin" => $patient->stateoforigin,
                //         "lga" => $patient->lga,
                //         "tribe" => $patient->tribe,
                //         "cardno" => $patient->cardno,
                //         "status" => $patient->status,
                //         "service_id" => $patient->service_id,
                //         "patientno" => $patient->patientno,
                //     ];
                // });




                // // Generate HTML with proper encoding
                // $html = '<!DOCTYPE html>
                // <html>
                // <head>
                //     <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                //     <title>Patient Report</title>
                //     <style>
                //         body { font-family: DejaVu Sans, sans-serif; }
                //         table { width: 100%; border-collapse: collapse; }
                //         th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                //         th { background-color: #f2f2f2; }
                //     </style>
                // </head>
                // <body>
                //     <h2>Patient Report</h2>
                //     <table>
                //     <thead>
                //         <tr>
                //             <th>firstname</th>
                //             <th>lastname</th>
                //             <th>dob</th>
                //             <th>age</th>
                //             <th>gender</th>
                //             <th>bloodgroup</th>
                //             <th>genotype</th>
                //             <th>email</th>
                //             <th>patient_type</th>
                //             <th>marital_status</th>
                //             <th>phoneno</th>
                //             <th>occupation</th>
                //             <th>homeaddress</th>
                //             <th>companyaddress</th>
                //             <th>religion</th>
                //             <th>stateoforigin</th>
                //             <th>lga</th>
                //             <th>tribe</th>
                //             <th>cardno</th>
                //             <th>status</th>
                //             <th>patientno</th>
                //             <th>Arrival Date</th>
                //             <th>Departure Date</th>
                //             <th>Status</th>
                //             <th>Visitno</th>
                //         </tr>
                //     </thead>
                //         <tbody>';


                // foreach ($patients as $patient) {

                //     $patientvisit =  PatientVisit::where('patient_id', $patient['id'])->first();
                //     $html .= '<tr>';
                //     $html .= '<td>' . htmlspecialchars($patient['firstname'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['lastname'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['dob'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['age'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['gender'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['bloodgroup'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['genotype'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['email'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['patient_type'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['marital_status'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['phoneno'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['occupation'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['homeaddress'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['companyaddress'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['religion'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['stateoforigin'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['lga'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['tribe'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['cardno'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['status'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patient['patientno'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';

                //     $html .= '<td>' . htmlspecialchars($patientvisit?->arrival_date ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patientvisit?->departure_date ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patientvisit?->status ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '<td>' . htmlspecialchars($patientvisit?->visitno ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
                //     $html .= '</tr>';
                // }


                // $html .= '</tbody></table></body></html>';

                // // Generate PDF with DomPDF directly
                // $dompdf = new \Dompdf\Dompdf([
                //     'isHtml5ParserEnabled' => true,
                //     'isRemoteEnabled' => true,
                //     'defaultFont' => 'DejaVu Sans'
                // ]);

                // $dompdf->loadHtml($html, 'UTF-8');
                // $dompdf->setPaper('A3', 'landscape');
                // $dompdf->render();

                // return $dompdf->stream('patient_report_log.pdf', [
                //     'Attachment' => 1
                // ]);
            }

            // else if ($export == 'csv') {
            //     //  return ExportHelper::streamCsv($exportData, null, 'patients_' . now()->format('Ymd_His') . '.csv');
            //     // return ExportHelper::streamCsv($data);
            //     // $csv = new Csv($data);
            //     //   PatientExport
            //     // $data = Patient::get()->toArray();
            //     //dd(json_encode([$export, $data]));
            //     // return Excel::download(new PatientExport, 'patients.csv');

            //     //return Excel::download(new PatientExport, 'patients.csv', ExcelFormat::CSV);


            //     // return ExportHelper::streamCsv($data, null, 'patient_' . now()->format('Ymd_His') . '.csv');
            //     // return Excel::download(new PatientExport, 'patients.csv', ExcelFormat::CSV);

            // }
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
