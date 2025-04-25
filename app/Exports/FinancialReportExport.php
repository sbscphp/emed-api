<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class FinancialReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $data;
    protected $totalRevenue;
    protected $totalPending;

    public function __construct($data)
    {
        $this->data = collect($data);
        $this->totalRevenue = $this->data->sum('total_revenue');
        $this->totalPending = $this->data->sum('pending_payment');
    }

    public function collection()
    {
        return $this->data->push([
            'department' => 'SUB TOTAL',
            'total_revenue' => $this->totalRevenue,
            'pending_payment' => $this->totalPending,
        ]);
    }

    public function headings(): array
    {
        return [
            'Department',
            'Total Revenue',
            'Pending Payments',
        ];
    }

    public function map($item): array
    {
        return [
            $item['department'],
            $item['total_revenue'],
            $item['pending_payment'],
        ];
    }
}
