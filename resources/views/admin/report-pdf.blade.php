<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SDCC Loan Applications Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        h1 { color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #1e40af; color: #fff; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .summary { margin-top: 20px; }
        .summary p { margin: 4px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    </style>
</head>
<body>
    <h1>San Dionisio Credit Cooperative — Loan Applications Report</h1>
    <p>Generated: {{ now()->format('F d, Y g:i A') }}</p>

    @if($type === 'applications')
        <h2>Applications Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Application ID</th>
                    <th>Applicant</th>
                    <th>Loan Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>AI Score</th>
                </tr>
            </thead>
            <tbody>
                @foreach($applications as $app)
                <tr>
                    <td>{{ $app->app_id }}</td>
                    <td>{{ $app->applicant }}</td>
                    <td>{{ $app->loan_type }}</td>
                    <td>₱{{ number_format($app->amount, 2) }}</td>
                    <td>{{ $app->status }}</td>
                    <td>{{ $app->ai_score ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="summary">
            <p><strong>Total Applications:</strong> {{ $applications->count() }}</p>
            <p><strong>Total Amount Requested:</strong> ₱{{ number_format($applications->sum('amount'), 2) }}</p>
        </div>
    @elseif($type === 'disbursements')
        <h2>Disbursements Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Reference No</th>
                    <th>Applicant</th>
                    <th>Loan Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($applications as $app)
                <tr>
                    <td>DISB-{{ str_pad($app->id, 5, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $app->applicant ?? $app->full_name ?? 'Applicant' }}</td>
                    <td>{{ $app->loan_type ?? 'Personal Loan' }}</td>
                    <td>₱{{ number_format($app->amount ?? 0, 2) }}</td>
                    <td>{{ $app->status }}</td>
                    <td>{{ $app->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <h2>Report Type: {{ $type }}</h2>
        <p>Report data for {{ $type }}.</p>
    @endif

    <div class="footer">
        <p>San Dionisio Credit Cooperative — Confidential</p>
    </div>
</body>
</html>