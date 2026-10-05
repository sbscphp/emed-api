@use('App\Helpers\PdfBranding')

@php
    $title = 'System Report';
@endphp

@extends('exports.layouts.pdf')

@section('content')
    <table class="pdf-table">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Description</th>
                <th>Performed At</th>
            </tr>
        </thead>
        <tbody>
            @php $printed = 0; @endphp
            @foreach ($report as $item)
                @foreach ($item['actions'] as $actions)
                    @php $printed++; @endphp
                    <tr>
                        <td class="pdf-table__key">{{ $item['full_name'] }}</td>
                        <td>{{ PdfBranding::cell($item['roles']) }}</td>
                        <td>
                            @if (filled($item['status']))
                                <span class="pdf-pill pdf-pill--{{ PdfBranding::statusTone($item['status']) }}">{{ $item['status'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $actions['description'] }}</td>
                        <td>{{ $actions['performed_at'] }}</td>
                    </tr>
                @endforeach
            @endforeach
            @if ($printed === 0)
                <tr>
                    <td colspan="5" class="pdf-empty">No records to display.</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
