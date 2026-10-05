{{--
    The laboratory report a patient downloads from the app.

    Rendered on demand by PatientReportService from the result rows the lab
    entered, so it is always the current version of the result. Styling is kept
    to inline-ish CSS and plain tables because Dompdf is what turns it into a
    PDF, and it supports little more than that.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $order->test_name ?? 'Laboratory Report' }}</title>
    <style>
        @page { margin: 28px 34px 56px; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333333;
        }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 3px 0; vertical-align: top; font-size: 10.5px; }
        .meta .label { color: #666666; width: 110px; }
        .meta .value { font-weight: bold; }

        table.results { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.results th {
            background: #6C4BF4;
            color: #ffffff;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 7px 8px;
            border-bottom: 0;
        }
        table.results td { padding: 7px 8px; border-bottom: 1px solid #eeeeee; font-size: 10.5px; }
        table.results tr td.flag-high { color: #b42318; font-weight: bold; }
        table.results tr td.flag-low { color: #b54708; font-weight: bold; }

        .notes { margin-top: 16px; padding: 10px 12px; background: #f7f5ff; border-left: 3px solid #6C4BF4; }
        .notes-title { font-size: 10px; text-transform: uppercase; color: #666666; margin-bottom: 4px; letter-spacing: 0.4px; }

        .footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #eeeeee; font-size: 9px; color: #999999; }
        .empty { padding: 18px; text-align: center; color: #666666; font-style: italic; }
    </style>
    @include('exports.partials.pdf-styles')
</head>

<body>
    @include('exports.partials.pdf-chrome', ['watermarkSize' => 22, 'orientation' => 'portrait'])

    @include('exports.partials.pdf-header', ['title' => 'Laboratory Report'])

    <table class="meta">
        <tr>
            <td class="label">Patient</td>
            <td class="value">{{ trim(($patient->firstname ?? '') . ' ' . ($patient->lastname ?? '')) ?: '-' }}</td>
            <td class="label">Patient ID</td>
            <td class="value">{{ $patient->patientno ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Gender</td>
            <td class="value">{{ $patient->gender ?? '-' }}</td>
            <td class="label">Age</td>
            <td class="value">{{ $patient->age ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Test</td>
            <td class="value">{{ $order->test_name ?? '-' }}</td>
            <td class="label">Specimen</td>
            <td class="value">{{ $order->specimen_type ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Ordered</td>
            <td class="value">{{ optional($order->created_at)->format('d M Y, h:i A') ?? '-' }}</td>
            <td class="label">Released</td>
            <td class="value">{{ optional($results->max('updated_at'))->format('d M Y, h:i A') ?? '-' }}</td>
        </tr>
    </table>

    @if ($results->isEmpty())
        <div class="empty">No result lines have been entered for this test.</div>
    @else
        <table class="results">
            <thead>
                <tr>
                    <th style="width: 40%;">Test</th>
                    <th style="width: 20%;">Result</th>
                    <th style="width: 13%;">Unit</th>
                    <th style="width: 27%;">Reference Range</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $result)
                    @php
                        $flag = strtolower((string) $result->flag);
                        $class = str_contains($flag, 'high') ? 'flag-high' : (str_contains($flag, 'low') ? 'flag-low' : '');
                    @endphp
                    <tr>
                        <td>{{ $result->test }}</td>
                        <td class="{{ $class }}">
                            {{ $result->result ?? '-' }}@if (!empty($result->flag)) <span>({{ $result->flag }})</span>@endif
                        </td>
                        <td>{{ $result->unit ?? '-' }}</td>
                        <td>{{ $result->reference_range ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (!empty($order->notes))
        <div class="notes">
            <div class="notes-title">Laboratory Notes</div>
            <div>{{ $order->notes }}</div>
        </div>
    @endif

    <div class="footer">
        This report is issued to the patient named above and is not valid for anyone else.
    </div>
</body>

</html>
