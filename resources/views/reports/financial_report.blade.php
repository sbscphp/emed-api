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
    <h2>Finance Report</h2>
    <table>
        <thead>
            <tr>
                <th>Department</th>
                <th>Total Revenue</th>
                <th>Pending Payment</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $item)
                <tr>
                    <td>{{ $item['department'] }}</td>
                    <td>{{ $item['total_revenue'] }}</td>
                    <td>{{ $item['pending_payment'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>TOTALREVENUE</strong></td>
                <td><strong>{{ $totalrevenue }}</strong></td>
            </tr>
              <tr>
                <td><strong>TotalPending</strong></td>
                <td><strong>{{ $totalpending }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
