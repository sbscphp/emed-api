@php
    $title = 'Finance Report';
@endphp

@extends('exports.layouts.pdf')

@section('content')
    <table class="pdf-table">
        <thead>
            <tr>
                <th>Department</th>
                <th class="pdf-num">Total Revenue</th>
                <th class="pdf-num">Pending Payment</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report as $item)
                <tr>
                    <td class="pdf-table__key">{{ strval($item['department']) }}</td>
                    <td class="pdf-num">{{ strval($item['total_revenue']) }}</td>
                    <td class="pdf-num">{{ strval($item['pending_payment']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="pdf-empty">No records to display.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="pdf-num">{{ $totalrevenue }}</td>
                <td class="pdf-num">{{ $totalpending }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
