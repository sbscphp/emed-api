<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Patient General Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
        }

        .section {
            margin-bottom: 20px;
        }

        h2 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }
    </style>
</head>

<body>


    <div class="section">
        @if (count($data) != 0)

        @php
            //   'firstname' => $this->firstname,
            // 'lastname' => $this->lastname,
            // 'dob' => $this->dob,
            // 'age' => $this->age,
            // 'gender' => $this->gender,
            // 'bloodgroup' => $this->bloodgroup,
            // 'genotype' => $this->genotype,
            // 'email' => $this->email,
            // 'patient_type' => $this->patient_type,
            // 'marital_status' => $this->marital_status,
            // 'phoneno' => $this->phoneno,
            // 'visitno' => $this->visitno,
            // 'occupation' => $this->occupation,
            // 'homeaddress' => $this->homeaddress,
            // 'companyaddress' => $this->companyaddress,
            // 'religion' => $this->companyaddress,
            // 'stateoforigin' => $this->stateoforigin,
            // 'lga' => $this->lga,
            // 'tribe' => $this->tribe,
            // 'cardno' => $this->cardno,
            // 'receiptno' => $this->receiptno,
            // 'status' => $this->status,
            // 'service_id' => $this->service_id,
            // 'arrival_time' => $this->arrival_time,
            // 'depature_time' => $this->depature_time,
            // 'patientno' => $this->patientno
        @endphp
            <table>
                <thead>
                    <tr>
                        <th>firstname</th>
                        <th>lastname</th>
                        <th>dob</th>
                        <th>age</th>
                        <th>gender</th>
                        <th>bloodgroup</th>
                        <th>genotype</th>
                        <th>email</th>
                        <th>patient_type</th>
                        <th>marital_status</th>
                        <th>phoneno</th>
                         <th>visitno</th>
                          <th>occupation</th>
                           <th>homeaddress</th>
                            <th>companyaddress</th>
                             <th>religion</th>
                              <th>stateoforigin</th>
                               <th>lga</th>
                                    <th>tribe</th>
                             <th>cardno</th>
                              <th>receiptno</th>
                               <th>status</th>
                               <th>service_id</th>
                               <th>arrival_time</th>
                               <th>depature_time</th>
                               <th>patientno</th>

                    </tr>
                </thead>
                <tbody>

                    @foreach ($data as $dat)
                 
                        <tr>
                            <td>{{ $dat['firstname'] ?? '' }} </td>
                            <td>{{ $dat['lastname'] }}</td>
                            <td>{{ $dat['dob'] }}</td>
                            <td>{{ $dat['age'] }}</td>
                            <td>{{ $dat['gender'] ?? '' }} </td>
                            <td>{{ $dat['bloodgroup'] ?? '' }}</td>
                            <th>{{$dat['genotype'] ?? '' }}</th>
                            <th>{{ $dat['email'] ?? '' }}</th>
                            <th>{{ $dat['patient_type'] ?? '' }}</th>
                            <th>{{$dat['marital_status'] ?? '' }}</th>
                              <td>{{ $dat['phoneno'] ?? '' }} </td>
                            <td>{{ $dat['visitno'] ?? '' }}</td>
                            <th>{{$dat['occupation'] ?? '' }}</th>
                            <th>{{ $dat['homeaddress'] ?? '' }}</th>
                            <th>{{ $dat['companyaddress'] ?? '' }}</th>
                            <th>{{$dat['religion'] ?? '' }}</th>
                              <td>{{ $dat['stateoforigin'] ?? '' }} </td>
                            <td>{{ $dat['lga'] ?? '' }}</td>
                            <th>{{$dat['tribe'] ?? '' }}</th>
                            <th>{{ $dat['cardno'] ?? '' }}</th>
                            <th>{{ $dat['receiptno'] ?? '' }}</th>
                            <th>{{$dat['status'] ?? '' }}</th>
                             <th>{{ $dat['service_id'] ?? '' }}</th>
                            <th>{{$dat['arrival_time'] ?? '' }}</th>
                             <th>{{ $dat['depature_time'] ?? '' }}</th>
                            <th>{{$dat['patientno'] ?? '' }}</th>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No data</p>
        @endif
    </div>

</body>

</html>
