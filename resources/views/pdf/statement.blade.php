<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fee Statement - {{ $month }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .info { margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #f0f0f0; }
        .total { font-weight: bold; font-size: 1.2em; }
        .qr { text-align: center; margin: 30px 0; }
        .footer { margin-top: 50px; font-size: 0.9em; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $school->name }}</h1>
        <p>{{ $school->address }} • {{ $school->phone }}</p>
        <h2>Monthly Fee Statement - {{ $month }}</h2>
    </div>

    <div class="info">
        <p><strong>Student:</strong> {{ $student->name }}</p>
        <p><strong>Class:</strong> {{ $student->studentRecord?->classSection?->name }}</p>
        <p><strong>Admission No:</strong> {{ $student->studentRecord?->admission_number }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fee Type</th>
                <th>Due Date</th>
                <th>Amount Due</th>
                <th>Paid</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assignments as $assign)
            <tr>
                <td>{{ $assign->fee->feeType->name }}</td>
                <td>{{ $assign->due_date->format('d M Y') }}</td>
                <td>₦{{ number_format($assign->amount_due, 2) }}</td>
                <td>₦{{ number_format($assign->amount_paid, 2) }}</td>
                <td style="color: {{ $assign->balance > 0 ? 'red' : 'green' }}">
                    ₦{{ number_format($assign->balance, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="4"><strong>Total Outstanding</strong></td>
                <td><strong>₦{{ number_format($totalDue, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="qr">
        <p><strong>Scan to Pay Instantly</strong></p>
        <img src="{{ $qrCode }}" alt="Payment QR Code">
        <p><small>Or click: <a href="{{ $paymentLink }}">{{ $paymentLink }}</a></small></p>
    </div>

    <div class="footer">
        <p>Thank you for choosing {{ $school->name }}. For inquiries: {{ $school->email }}</p>
    </div>
</body>
</html>
