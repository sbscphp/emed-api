<!DOCTYPE html>
<html>

<head>
    <title>Patient Visit Export</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px;
            text-align: left;
        }
    </style>
</head>

<body>
    <h2>Patient Visits</h2>
    <table>
        <thead>
            <tr>
                <th>Patient No</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Visit No</th>
                <th>Stage</th>
                <th>Status</th>
                <th>Arrival Date</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($visits as $visit)
            <tr>
                <td>{{ $visit->patient->patientno ?? '' }}</td>
                <td>{{ $visit->patient->firstname ?? '' }}</td>
                <td>{{ $visit->patient->lastname ?? '' }}</td>
                <td>{{ $visit->visitno }}</td>
                <td>{{ $visit->stage }}</td>
                <td>{{ $visit->status }}</td>
                <td>{{ $visit->arrival_date }}</td>
                <td>{{ $visit->created_at }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>