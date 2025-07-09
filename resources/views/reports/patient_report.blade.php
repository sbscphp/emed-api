<!DOCTYPE html>
<html>
<head>
    <title>Patient Report</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>Patient Report</h2>
    <table>
        <thead>
            <tr>
                <th>Department</th>
                <th>Total Patients</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $item)
                <tr>
                    <td>{{ $item['department'] }}</td>
                    <td>{{ $item['total_patients'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>TOTAL</strong></td>
                <td><strong>{{ $grandTotal }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
