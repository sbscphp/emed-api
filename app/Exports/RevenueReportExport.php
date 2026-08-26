<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RevenueReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records;
    }

    public function map($row): array
    {
        return [
            $row['hospital_name'] ?? 'N/A',
            number_format($row['revenue'], 2),
            number_format($row['paid'], 2),
            $row['renewal_date'] ?? 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'Hospital Name',
            'Revenue (₦)',
            'Paid (₦)',
            'Renewal Date',
        ];
    }
}
