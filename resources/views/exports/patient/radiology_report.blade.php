{{--
    The radiology report a patient downloads from the app.

    Rendered on demand by PatientReportService from the report the radiologist
    wrote, so it is always the current version. Kept to plain tables and simple
    CSS because Dompdf is what turns it into a PDF.
--}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $order->test_name ?? 'Radiology Report' }}</title>
    <style>
        @page { margin: 28px 34px; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2430;
        }

        .header { border-bottom: 2px solid #6d4aff; padding-bottom: 10px; margin-bottom: 16px; }
        .hospital { font-size: 17px; font-weight: bold; color: #2b1b6b; }
        .hospital-address { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .doc-title { font-size: 13px; font-weight: bold; margin-top: 10px; text-transform: uppercase; letter-spacing: 0.5px; }

        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { padding: 3px 0; vertical-align: top; font-size: 10.5px; }
        .meta .label { color: #6b7280; width: 110px; }
        .meta .value { font-weight: bold; }

        .section { margin-top: 14px; }
        .section-title {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #2b1b6b;
            background: #f3f0ff;
            padding: 6px 8px;
            font-weight: bold;
        }
        .section-body { padding: 8px; border: 1px solid #eef0f4; border-top: 0; line-height: 1.55; }

        .image { margin-top: 14px; text-align: center; }
        .image img { max-width: 100%; max-height: 320px; }

        .footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9098a8; }
        .empty { padding: 18px; text-align: center; color: #6b7280; font-style: italic; }
    </style>
</head>

<body>
    <div class="header">
        <div class="hospital">{{ $tenant->name }}</div>
        @if (!empty($tenant->address))
            <div class="hospital-address">{{ $tenant->address }}</div>
        @endif
        <div class="doc-title">Radiology Report</div>
    </div>

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
            <td class="label">Examination</td>
            <td class="value">{{ $order->test_name ?? '-' }}</td>
            <td class="label">Ordered</td>
            <td class="value">{{ optional($order->created_at)->format('d M Y, h:i A') ?? '-' }}</td>
        </tr>
    </table>

    @if ($results->isEmpty())
        <div class="empty">No report has been written for this examination.</div>
    @else
        @foreach ($results as $result)
            @if (!empty($result->examination_type))
                <div class="section">
                    <div class="section-title">Examination Type</div>
                    <div class="section-body">{{ $result->examination_type }}</div>
                </div>
            @endif

            @if (!empty($result->clinical_indication))
                <div class="section">
                    <div class="section-title">Clinical Indication</div>
                    <div class="section-body">{{ $result->clinical_indication }}</div>
                </div>
            @endif

            @if (!empty($result->technique))
                <div class="section">
                    <div class="section-title">Technique</div>
                    <div class="section-body">{{ $result->technique }}</div>
                </div>
            @endif

            <div class="section">
                <div class="section-title">Findings</div>
                <div class="section-body">{!! nl2br(e($result->findings ?: 'No findings were recorded.')) !!}</div>
            </div>

            @if (!empty($result->result_img))
                <div class="image">
                    {{-- Remote images are left to Dompdf, which simply skips one it
                         cannot reach; the report is still worth downloading without it. --}}
                    <img src="{{ $result->result_img }}" alt="Radiology image">
                </div>
            @endif
        @endforeach
    @endif

    <div class="footer">
        Generated {{ now()->format('d M Y, h:i A') }} - {{ $tenant->name }}.
        This report is issued to the patient named above and is not valid for anyone else.
    </div>
</body>

</html>
