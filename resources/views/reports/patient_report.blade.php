@php
    $title = 'Patient Report';
@endphp

@extends('exports.layouts.pdf')

@section('content')
    <table class="pdf-table">
        <thead>
            <tr>
                <th>Department</th>
                <th class="pdf-num">Total Patients</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report as $item)
                <tr>
                    <td class="pdf-table__key">{{ $item['department'] }}</td>
                    <td class="pdf-num">{{ $item['total_patients'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="pdf-empty">No records to display.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="pdf-num">{{ $grandTotal }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
