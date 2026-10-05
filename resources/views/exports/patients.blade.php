{{--
    The generic table export: every list screen's "Export PDF" renders its rows
    through this view, whatever the rows are (the name is historical).

    Takes:
      - `patients`      the rows, each an array keyed by column
      - `title`         what the document is called (defaults from `type`)
      - `headers`       optional column order; defaults to the first row's keys
      - `footerTotals`  optional totals row, keyed by column or positional

    Columns whose heading mentions a status are drawn as coloured pills, and
    the page size grows with the column count; see PdfBranding.
--}}
@use('App\Helpers\PdfBranding')

@php
    $rows = collect($patients ?? [])
        ->map(fn($row) => is_array($row) ? $row : (is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : (array) $row))
        ->values();

    $columns = !empty($headers) ? array_values($headers) : array_keys($rows->first() ?? []);

    $title = $title ?? (!empty($type) ? $type . ' Report' : 'Export');
    $paper = $paper ?? PdfBranding::paperFor(count($columns));

    $totals = !empty($footerTotals) ? (array) $footerTotals : [];
    $positionalTotals = array_is_list($totals);
@endphp

@extends('exports.layouts.pdf')

@section('content')
    @if ($rows->isEmpty() || empty($columns))
        <div class="pdf-empty">No records to display.</div>
    @else
        <table class="pdf-table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ PdfBranding::heading($column) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $position => $column)
                            @php
                                $value = PdfBranding::cell($row[$column] ?? null);
                            @endphp
                            @if (PdfBranding::isStatusColumn($column) && $value !== '—')
                                <td>
                                    <span class="pdf-pill pdf-pill--{{ PdfBranding::statusTone($value) }}">{{ $value }}</span>
                                </td>
                            @else
                                <td class="{{ $position === 0 ? 'pdf-table__key' : '' }}">{{ $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @if (!empty($totals))
                <tfoot>
                    <tr>
                        @foreach ($columns as $position => $column)
                            <td>{{ $positionalTotals ? ($totals[$position] ?? '') : ($totals[$column] ?? '') }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif
@endsection
