<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Triage Export</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
    </style>
</head>

<body>
    <h2>Triage Patient Export - {{ now()->format('Y-m-d') }}</h2>
    <table>
        <thead>
            <tr>
                @foreach(array_keys($patients[0]) as $heading)
                <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($patients as $row)
            <tr>
                @foreach($row as $cell)
                <td>{{ $cell }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>