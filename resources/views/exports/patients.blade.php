<!DOCTYPE html>
<html>

<head>
    @php
        $first = $patients[0] ?? [];
    @endphp
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
        }

        /* .section {
            margin-bottom: 20px;
        } */

        h2 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
            word-wrap: break-word;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            padding: 6px;
            word-break: break-word;
            white-space: normal;
        }

        tr {
            page-break-inside: avoid;
        }

        table,
        .section {
            page-break-inside: avoid;
            page-break-after: auto;
        }
    </style>
</head>

<body>
    <div class="section">
        @if (!empty($first))
            <table>
                <thead>
                    <tr>
                        @foreach (array_keys($first) as $key)
                            <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patients as $patient)
                        <tr>
                            @foreach ($patient as $value)
                                <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No records to display.</p>
        @endif
    </div>

</body>

</html>
