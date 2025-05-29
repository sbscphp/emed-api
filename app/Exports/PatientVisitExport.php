<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PatientVisitExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data->map(function ($visit) {
            return [
                'Patient No'   => $visit->patient->patientno ?? '',
                'First Name'   => $visit->patient->firstname ?? '',
                'Last Name'    => $visit->patient->lastname ?? '',
                'Visit No'     => $visit->visitno,
                'Stage'        => $visit->stage,
                'Status'       => $visit->status,
                'Arrival Date' => $visit->arrival_date,
                'Created At'   => $visit->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Patient No',
            'First Name',
            'Last Name',
            'Visit No',
            'Stage',
            'Status',
            'Arrival Date',
            'Created At',
        ];
    }
}
