<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ClientReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
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

    public function map($client): array
    {
        return [
            $client->name ?? 'N/A',
            $client->status ?? 'Inactive',
            $client->subscription ? $client->subscription->usageFee->name ?? 'N/A' : 'N/A',
            number_format($client->subscription ? $client->subscription->license_fee : 0, 2),
        ];
    }

    public function headings(): array
    {
        return [
            'Hospital Name',
            'Status',
            'Usage Fee Plan',
            'License Fee (₦)',
        ];
    }
}
