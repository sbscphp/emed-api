<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class UsageFeeReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
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

    public function map($charge): array
    {
        return [
            $charge->tenant->name ?? 'N/A',
            $charge->usageFee->name ?? 'N/A',
            $charge->total_visits ?? 0,
            number_format($charge->fee_per_visit, 2),
            number_format($charge->total_amount, 2),
            $charge->status ?? 'Pending',
        ];
    }

    public function headings(): array
    {
        return [
            'Hospital Name',
            'Usage Fee Plan',
            'Total Visits',
            'Fee Per Visit (₦)',
            'Total Amount (₦)',
            'Status',
        ];
    }
}
