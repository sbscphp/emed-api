@php
    $title = 'Audit Logs';
@endphp

@extends('exports.layouts.pdf')

@section('content')
    @if (collect($logs)->isEmpty())
        <div class="pdf-empty">No records to display.</div>
    @else
        <table class="pdf-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>User Role</th>
                    <th>Action</th>
                    <th>Module Accessed</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="pdf-table__key">{{ $log->id }}</td>
                        <td>{{ $log->causer?->id ?? '—' }}</td>
                        <td>{{ $log->causer?->role ?? '—' }}</td>
                        <td>{{ $log->log_name ?: '—' }}</td>
                        <td>{{ $log->action_type ?: '—' }}</td>
                        <td>{{ $log->created_at ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
