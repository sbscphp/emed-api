<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PatientReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;
    protected $grandTotal;

    public function __construct(Collection $data, int $grandTotal)
    {
        $this->data = $data;
        $this->grandTotal = $grandTotal;
    }
    public function collection(): Collection
    {
        $collection = collect($this->data);

        // Append TOTAL row
        $collection->push([
            'department' => 'TOTAL',
            'total_patients' => $this->grandTotal,
        ]);

        return $collection;
    }

    public function headings(): array
    {
        return [
            'Department',
            'Total Patients',
        ];
    }

    public function map($item): array
    {
        return [
            $item['department'] ?? '',
            $item['total_patients'] ?? 0,
        ];
    }
}
