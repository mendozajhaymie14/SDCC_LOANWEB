<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SDCC {{ ucwords(str_replace('_', ' ', $type)) }} Report</title>
    <style>
        @font-face {
            font-family: 'DejaVu Sans';
            src: url('file:///{{ str_replace('\\', '/', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf')) }}');
        }
        @font-face {
            font-family: 'DejaVu Sans';
            font-weight: bold;
            src: url('file:///{{ str_replace('\\', '/', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf')) }}');
        }
        @font-face {
            font-family: 'DejaVu Sans';
            font-style: italic;
            src: url('file:///{{ str_replace('\\', '/', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Oblique.ttf')) }}');
        }
        @font-face {
            font-family: 'DejaVu Sans';
            font-weight: bold;
            font-style: italic;
            src: url('file:///{{ str_replace('\\', '/', base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-BoldOblique.ttf')) }}');
        }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 20px; color: #333; }
        h1 { color: #1e40af; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #1e40af; margin-top: 25px; }
        h3 { color: #374151; margin-top: 18px; }
        .filters { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 15px; font-size: 13px; }
        .filters strong { color: #374151; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #1e40af; color: #fff; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    </style>
</head>
<body>
    <h1>San Dionisio Credit Cooperative — {{ ucwords(str_replace('_', ' ', $type)) }} Report</h1>
    <p>Generated: {{ now()->format('F d, Y g:i A') }}</p>

    <div class="filters">
        <strong>Applied Filters:</strong>
        @php
            $filterParts = [];
            if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
                $start = $filters['start_date'] ? \Carbon\Carbon::parse($filters['start_date'])->format('M d, Y') : 'Beginning';
                $end = $filters['end_date'] ? \Carbon\Carbon::parse($filters['end_date'])->format('M d, Y') : 'Present';
                $filterParts[] = "Date Range: {$start} to {$end}";
            }
            if (!empty($filters['status'])) {
                $filterParts[] = "Status: " . ucfirst($filters['status']);
            }
            if (!empty($filters['risk_level'])) {
                $filterParts[] = "Risk Level: " . $filters['risk_level'];
            }
        @endphp
        @if(!empty($filterParts))
            <span> {{ implode(' | ', $filterParts) }}</span>
        @else
            <span style="color: #9ca3af;"> None</span>
        @endif
    </div>

    @if($type === 'loan_summary')
        <h2>Loan Summary</h2>
        <h3>By Status</h3>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loanSummary['status_totals'] as $status => $total)
                <tr>
                    <td>{{ ucfirst($status) }}</td>
                    <td class="text-center">{{ $loanSummary['by_status_counts'][$status] ?? 0 }}</td>
                    <td class="text-right">₱{{ number_format($total, 2) }}</td>
                </tr>
                @endforeach
                <tr style="font-weight: bold; background-color: #f0f4f8;">
                    <td>Total</td>
                    <td class="text-center">{{ $loanSummary['total_count'] }}</td>
                    <td class="text-right">₱{{ number_format($loanSummary['total_amount'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <h3>By Loan Type</h3>
        <table>
            <thead>
                <tr>
                    <th>Loan Type</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loanSummary['by_type'] as $type => $total)
                <tr>
                    <td>{{ ucfirst($type) }}</td>
                    <td class="text-center">{{ $loanSummary['by_type_counts'][$type] ?? 0 }}</td>
                    <td class="text-right">₱{{ number_format($total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <h3>Risk Distribution</h3>
        <table>
            <thead>
                <tr>
                    <th>Risk Level</th>
                    <th>Count</th>
                    <th>% of Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach(['Low', 'Medium', 'High'] as $level)
                @php $count = $loanSummary['risk_distribution'][$level] ?? 0; @endphp
                <tr>
                    <td>{{ $level }}</td>
                    <td class="text-center">{{ $count }}</td>
                    <td class="text-center">{{ $loanSummary['total_count'] > 0 ? round($count / $loanSummary['total_count'] * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'approved_records')
        <h2>Approved Loan Records</h2>
        <table>
            <thead>
                <tr>
                    <th>App ID</th>
                    <th>Member</th>
                    <th>Loan Type</th>
                    <th>Approved Amount</th>
                    <th>Term (mo)</th>
                    <th>Risk</th>
                    <th>Recommended</th>
                    <th>Approved Date</th>
                    <th>Approved By</th>
                </tr>
            </thead>
            <tbody>
                @foreach($approvedRecs as $row)
                <tr>
                    <td>{{ $row->app_id }}</td>
                    <td>{{ $row->member_name }}</td>
                    <td>{{ $row->loan_type_label }}</td>
                    <td class="text-right">{{ $row->approved_amount }}</td>
                    <td class="text-center">{{ $row->term_months }}</td>
                    <td>{{ $row->risk_level }}</td>
                    <td class="text-right">{{ $row->recommended_amt }}</td>
                    <td>{{ $row->approved_date }}</td>
                    <td>{{ $row->approved_by }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'borrower_performance')
        <h2>Borrower Performance</h2>
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Total Loans</th>
                    <th>Total Paid</th>
                    <th>Outstanding</th>
                    <th>Late Payments</th>
                </tr>
            </thead>
            <tbody>
                @foreach($borrowerPerf as $row)
                <tr>
                    <td>{{ $row->member_name }}</td>
                    <td class="text-center">{{ $row->total_loans }}</td>
                    <td class="text-right">{{ $row->total_paid }}</td>
                    <td class="text-right">{{ $row->outstanding }}</td>
                    <td class="text-center">{{ $row->late_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif($type === 'overdue_payments')
        <h2>Overdue Payments</h2>
        <table>
            <thead>
                <tr>
                    <th>App ID</th>
                    <th>Member</th>
                    <th>Loan Type</th>
                    <th>Overdue Amount</th>
                    <th>Due Date</th>
                    <th>Overdue Days</th>
                </tr>
            </thead>
            <tbody>
                @foreach($overduePayments as $row)
                <tr>
                    <td>{{ $row->app_id }}</td>
                    <td>{{ $row->member_name }}</td>
                    <td>{{ $row->loan_type_label }}</td>
                    <td class="text-right">{{ $row->amount }}</td>
                    <td>{{ $row->due_date }}</td>
                    <td class="text-center">{{ $row->overdue_days }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <p>San Dionisio Credit Cooperative — Confidential</p>
    </div>
</body>
</html>