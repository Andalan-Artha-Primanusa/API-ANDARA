<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Slip</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
        }
        .header {
            margin-bottom: 18px;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
        }
        .subtitle {
            color: #6b7280;
            margin-top: 4px;
        }
        .grid {
            width: 100%;
            margin-bottom: 16px;
        }
        .grid td {
            vertical-align: top;
            padding: 4px 0;
        }
        .section-title {
            margin-top: 14px;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 13px;
        }
        table.line-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.line-items th,
        table.line-items td {
            border: 1px solid #e5e7eb;
            padding: 6px;
        }
        table.line-items th {
            background: #f3f4f6;
            text-align: left;
        }
        .num {
            text-align: right;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
        }
        .summary td {
            padding: 5px 4px;
            border-bottom: 1px solid #f3f4f6;
        }
        .summary .label {
            width: 65%;
        }
        .summary .value {
            width: 35%;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Payroll Slip</div>
        <div class="subtitle">Period: {{ $payload['period'] }} | Status: {{ $payload['status'] }}</div>
    </div>

    <table class="grid">
        <tr>
            <td><strong>Employee Name</strong></td>
            <td>: {{ $payload['employee']['name'] ?? '-' }}</td>
            <td><strong>Employee Code</strong></td>
            <td>: {{ $payload['employee']['employee_code'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td>: {{ $payload['employee']['email'] ?? '-' }}</td>
            <td><strong>Department</strong></td>
            <td>: {{ $payload['employee']['department'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Position</strong></td>
            <td>: {{ $payload['employee']['position'] ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <div class="section-title">Earnings</div>
    <table class="line-items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic Salary</td>
                <td class="num">{{ number_format((float) ($payload['summary']['basic_salary'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Allowance</td>
                <td class="num">{{ number_format((float) ($payload['summary']['allowance'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Bonus</td>
                <td class="num">{{ number_format((float) ($payload['summary']['bonus'] ?? 0), 2) }}</td>
            </tr>
            @foreach ($payload['earnings'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format((float) $row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Deductions</div>
    <table class="line-items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>BPJS Kesehatan</td>
                <td class="num">{{ number_format((float) ($payload['summary']['bpjs_kesehatan'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>BPJS Ketenagakerjaan</td>
                <td class="num">{{ number_format((float) ($payload['summary']['bpjs_ketenagakerjaan'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>PPH21</td>
                <td class="num">{{ number_format((float) ($payload['summary']['pph21'] ?? 0), 2) }}</td>
            </tr>
            @foreach ($payload['deductions'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format((float) $row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Summary</div>
    <table class="summary">
        <tr>
            <td class="label">Gross Pay</td>
            <td class="value">{{ number_format((float) ($payload['summary']['gross_pay'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td class="label">Total Deduction</td>
            <td class="value">{{ number_format((float) ($payload['summary']['total_deduction'] ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td class="label"><strong>Take Home Pay</strong></td>
            <td class="value"><strong>{{ number_format((float) ($payload['summary']['take_home_pay'] ?? 0), 2) }}</strong></td>
        </tr>
    </table>
</body>
</html>