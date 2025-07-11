<?php

namespace App\Exports;

use Maatwebsite\Excel\Facades\Excel;
use App\Models\Patient;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;

class PatientExport implements FromCollection
{

    public function collection()
    {
        return Patient::all();
    }

    // public function map($row): array
    // {
    //     return [
    //         $this->clean($row->firstname),
    //         $this->clean($row->lastname),
    //         $this->clean($row->email),
    //         $this->clean($row->gender),
    //         $this->clean($row->status),
    //         // add more fields as needed...
    //     ];
    // }

    // private function clean($value)
    // {
    //     return is_string($value)
    //         ? mb_convert_encoding($value, 'UTF-8', 'UTF-8')
    //         : $value;
    // }
}
