@php
$first = $patients[0] ?? [];
@endphp

@if (!empty($first))
<table>
    <thead>
        <tr>
            @foreach(array_keys($first) as $key)
            <th>{{ ucwords(str_replace('_', ' ', $key)) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($patients as $patient)
        <tr>
            @foreach($patient as $value)
            <td>{{ is_scalar($value) ? $value : json_encode($value) }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p>No patient records to display.</p>
@endif