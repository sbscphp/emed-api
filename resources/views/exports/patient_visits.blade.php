<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Visit Number</th>
            <th>Service Type</th>
            <th>Referral</th>
            <th>Payment Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($visits as $visit)
        <tr>
            <td>{{ $visit['date'] }}</td>
            <td>{{ $visit['visit_number'] }}</td>
            <td>{{ $visit['service_type'] }}</td>
            <td>{{ $visit['referral'] }}</td>
            <td>{{ $visit['payment_status'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>