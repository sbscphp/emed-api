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
    <h2>System Report</h2>
    <table>
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Roles</th>
                <th>Status</th>
                 <th>Description</th>
               <th>Performed_at</th>

            </tr>
        </thead>
        <tbody>
            @foreach($report as $item)
              @foreach ($item['actions'] as $actions)
                    <tr>
                    <td>{{ $item['full_name'] }}</td>
                    <td>{{ $item['roles'] }}</td>
                    <td>{{ $item['status'] }}</td>
                    <td>{{ $actions['description'] }}</td>
                    <td>{{ $actions['performed_at'] }}</td>
                </tr>
              @endforeach
               
            @endforeach
            {{-- <tr>
                <td><strong>TOTAL</strong></td>
                <td><strong>{{ $grandTotal }}</strong></td>
            </tr> --}}
        </tbody>
    </table>
</body>
</html>
