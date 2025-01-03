<!DOCTYPE html>
<html>

<head>
    <title>Vehicle Service History</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .vehicle-info {
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #f4f4f4;
        }

        .total {
            margin-top: 20px;
            text-align: right;
            font-weight: bold;
        }

        .section-header {
            text-decoration: underline;
            padding-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>Vehicle Service History</h1>
    </div>

    <div class="vehicle-info">
        <h2 class="section-header">Vehicle Details</h2>
        <p><strong>Model:</strong> {{ $vehicle->model }}</p>
        <p><strong>Registration Number:</strong> {{ $vehicle->registration_number }}</p>
        <p><strong>Year:</strong> {{ $vehicle->year }}</p>
    </div>

    <h2 class="section-header">Service History</h2>
    @if(count($serviceRecords) > 0)
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Service Place</th>
                    <th>Description</th>
                    <th>Cost (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($serviceRecords as $index => $record)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ date('d/m/Y', strtotime($record->service_date)) }}</td>
                        <td>{{ $record->service_place }}</td>
                        <td>{{ $record->description }}</td>
                        <td>{{ number_format($record->service_cost, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            <p>Total Service Cost: RM {{ number_format($totalCost, 2) }}</p>
        </div>
    @else
        <p>No service records found.</p>
    @endif
</body>

</html>