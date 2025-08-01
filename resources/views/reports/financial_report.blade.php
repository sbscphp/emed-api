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
                @php
                    $department = strval($item['department']);
                    $total_revenue = strval($item['total_revenue']);
                   $pending_payment = strval($item['pending_payment']);
                @endphp
                <tr>
                    <td>{{ $department }}</td>
                    <td>{{ $total_revenue }}</td>
                    <td>{{  $pending_payment }}</td>
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
