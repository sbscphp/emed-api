@include('exports.patients', [
    'title' => 'Patient Visits',
    'patients' => collect($visits)->map(fn($visit) => [
        'Patient No' => $visit->patient->patientno ?? null,
        'First Name' => $visit->patient->firstname ?? null,
        'Last Name' => $visit->patient->lastname ?? null,
        'Visit No' => $visit->visitno,
        'Stage' => $visit->stage,
        'Status' => $visit->status,
        'Arrival Date' => $visit->arrival_date,
        'Created At' => $visit->created_at,
    ])->all(),
])
