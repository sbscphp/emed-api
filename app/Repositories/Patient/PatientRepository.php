<?php

namespace App\Repositories\Patient;

use App\Exports\PatientReportExport;
use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Responser\JsonResponser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Barryvdh\DomPDF\Facade\Pdf;

class PatientRepository implements PatientInterface
{
    /**
     * Retrieve a collection of Patient from the database.
     *
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all()
    {
        return Patient::all();
    }


    /**
     * Create new Patient in the database.
     *
     * @param array $data
     * @return \App\Models\Patient
     */
    public function create(array $data)
    {
        return Patient::create($data);
    }


    /**
     * Update an existing Patient in the database.
     *
     * @param array $data
     * @param int $id
     * @return \App\Models\Patient
     */
    public function update(array $data, $id)
    {
        $record = Patient::findOrFail($id);
        $record->update($data);
        return $record;
    }


    /**
     * Delete an existing Patient from the database.
     *
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $record = Patient::findOrFail($id);
        $record->delete();
    }


    /**
     * Find an existing Patient in the database by their ID.
     *
     * @param int $id
     * @return \App\Models\Patient
     */
    public function find($id)
    {
        return Patient::find($id);
    }


    /**
     * Find an existing Patient in the database by their $attr.
     *
     * @param string $attr
     * @param string $value
     * @return \App\Models\Patient
     */
    public function findByAttribute($attr, $value)
    {
        return Patient::where($attr, $value)->first();
    }

    public function findByMultiAttributes(array $attrs)
    {
        $record = Patient::query();

        foreach ($attrs as $key => $value) {
            if (is_string($key)) {
                $record = $record->where($key, $value);
            } elseif (is_array($value) && count($value) === 3) {
                [$column, $operator, $conditionValue] = $value;
                $record = $record->where($column, $operator, $conditionValue);
            } elseif (is_array($value) && count($value) === 2) {
                [$column, $conditionValue] = $value;
                $record = $record->where($column, $conditionValue);
            }
        }

        return $record->first();
    }

    public function findUserByFirstnameAndLastname($firstname, $lastname)
    {
        $patient = Patient::where('firstname', $firstname)->where('lastname', $lastname)->first();
        return $patient;
    }

    public function findMultipleRecordsByMultiAttributes(array $attrs)
    {
        $record = Patient::query();

        foreach ($attrs as $attr => $value) {
            $record = $record->where($attr, $value);
        }
        return $record->get();
    }

    public function getAllRecords($search, $paginate, $perPage)
    {
        $query = Patient::query();

        if (isset($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhere('cardno', 'LIKE', "%{$search}%")
                    ->orWhere('patient_type', 'LIKE', "%{$search}%")

                    ->orWhere('phoneno', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');
        return $paginate ? $query->paginate($perPage) : $query->get();
    }

    public function getRecordStats()
    {
        //
        $currentDate = Carbon::today();
        $registeredPatients = Patient::count();
        $admittedPatients = Admission::count();
        $totalPatientsVisitedToday = PatientVisit::whereDate('created_at', $currentDate)->count();
        $totalFollowUp = Appointment::count();

        return [
            'totalRegisteredPatient' => $registeredPatients,
            'admittedPatients' => $admittedPatients,
            'totalPatientsVisitedToday' => $totalPatientsVisitedToday,
            'numberOfFollowUp' => $totalFollowUp
        ];
    }

    public function getPatientReport(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $download = $request->boolean('download', 0);
        $perPage = $request->integer('per_page', 10);
        $currentPage = $request->integer('page', 1);
        $export  = $request->export;
        $patients = Patient::with('service')->when(!empty($start) && !empty($end),  function ($query) use ($start, $end) {
            $query->whereBetween('created_at', [$start, $end]);
        })->get();

        if ($patients->isEmpty()) {
            return JsonResponser::send(false, 'No patient records found for the selected date range.', [], 200);
        }

        $grouped = $patients->groupBy(fn($p) => optional($p->service)->name ?? 'Unknown');

        $report = $grouped->map(function ($group, $dept) {
            return [
                'department' => $dept,
                'total_patients' => $group->count(),
                'patients' => $group->take(10),
            ];
        })->values();

        $grandTotal = $patients->count();
        // dd(json_encode($report));
        if (intval($download)) {
            if ($export == 'xlsx') {
                return Excel::download(new PatientReportExport($report, $grandTotal), 'patient_report_' . now()->format('Ymd_His') . '.xlsx');
            } else if ($export == 'pdf') {
                $pdf = Pdf::loadView('reports.patient_report', [
                    'report' => $report,
                    'grandTotal' => $grandTotal,
                ]);

                return $pdf->download('patient_report_' . now()->format('Ymd_His') . '.pdf');
            }
        }

        // $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
        //     $report->forPage($currentPage, $perPage),
        //     $report->count(),
        //     $perPage,
        //     $currentPage,
        //     ['path' => url()->current(), 'query' => $request->query()]
        // );

        return JsonResponser::send(false, 'Patient report generated successfully.', [
            'data' => $report,
            'grand_total' => $grandTotal,
        ]);
    }
}
