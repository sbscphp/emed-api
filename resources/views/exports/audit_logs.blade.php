<h2>Audit Logs</h2>
<table border="1">
    <tr>
        <th>ID</th>
        <th>UserID</th>
        <th>User Role</th>
        <th>Action</th>
        <th>Module Accessed</th>
        <th>Date</th>
    </tr>

    @foreach($logs as $log)
    <tr>
        <td>{{ $log->id }}</td>
        <td>{{ $log->causer?->id }}</td>
        <td>{{ $log->causer?->role }}</td>
        <td>{{ $log->log_name }}</td>
        <td>{{ $log->action_type }}</td>
        <td>{{ $log->created_at }}</td>
    </tr>
    @endforeach
</table>
