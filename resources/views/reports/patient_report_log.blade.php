@php
    $columns = [
        'firstname', 'lastname', 'dob', 'age', 'gender', 'bloodgroup', 'genotype', 'email', 'patient_type',
        'marital_status', 'phoneno', 'visitno', 'occupation', 'homeaddress', 'companyaddress', 'religion',
        'stateoforigin', 'lga', 'tribe', 'cardno', 'receiptno', 'status', 'service_id', 'arrival_time',
        'depature_time', 'patientno',
    ];
@endphp

@include('exports.patients', [
    'title' => 'Patient General Report',
    'headers' => $columns,
    'patients' => collect($data)->map(fn($row) => collect($columns)->mapWithKeys(fn($column) => [$column => $row[$column] ?? null])->all())->all(),
])
