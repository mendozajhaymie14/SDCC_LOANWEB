<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Amortization Schedule — {{ $application->app_id ?? 'Application' }}</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DM Sans', sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; padding: 20mm; }
    .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #0891b2; padding-bottom: 16px; }
    .header h1 { font-size: 24px; font-weight: 700; color: #0891b2; }
    .header p { color: #6b7280; margin-top: 4px; }
    .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
    .info-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
    .info-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; font-weight: 600; }
    .info-value { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; }
    th { background: #0891b2; color: white; font-weight: 600; padding: 10px 8px; text-align: center; }
    td { border: 1px solid #e2e8f0; padding: 8px; text-align: center; }
    tr:nth-child(even) td { background: #f8fafc; }
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px; }
    .summary-item { background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 8px; padding: 16px; text-align: center; }
    .summary-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #0e7490; font-weight: 600; }
    .summary-value { font-size: 18px; font-weight: 700; color: #0891b2; margin-top: 6px; }
    .risk-section { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
    .risk-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .risk-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
    .risk-high { background: #ffe4e6; color: #9f1239; }
    .risk-medium { background: #fef3c7; color: #92400e; }
    .risk-low { background: #d1fae5; color: #065f46; }
    .override-box { margin-top: 16px; padding: 16px; background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; }
    .override-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #92400e; font-weight: 600; margin-bottom: 8px; }
    .override-text { font-size: 12px; color: #78350f; }
    @media print {
      .no-print { display: none; }
      body { padding: 0; }
    }
  </style>
</head>

<body>
  <div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button onclick="window.print()" style="background: #0891b2; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;">
      🖨️ Print / Save as PDF
    </button>
  </div>

  <div class="header">
    <h1>San Dionisio Credit Cooperative</h1>
    <p>Loan Amortization Schedule</p>
  </div>

  <div class="info-grid">
    <div class="info-item">
      <div class="info-label">Application ID</div>
      <div class="info-value">{{ $application->app_id ?? $application->id }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">Applicant</div>
      <div class="info-value">{{ $application->applicant ?? 'N/A' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">Loan Type</div>
      <div class="info-value">{{ $loanApp?->loan_type ?? 'Educational' }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">Generated</div>
      <div class="info-value">{{ now()->format('M d, Y') }}</div>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-item">
      <div class="info-label">Principal Amount</div>
      <div class="info-value">₱{{ number_format($application->amount ?? 0, 2) }}</div>
    </div>
    <div class="info-item">
      <div class="info-label">Term</div>
      <div class="info-value">{{ $loanApp?->term_months ?? 12 }} months</div>
    </div>
    <div class="info-item">
      <div class="info-label">Interest Rate</div>
      <div class="info-value">{{ config('loan_matrix.rate', 0.12) * 100 }}% p.a.</div>
    </div>
    <div class="info-item">
      <div class="info-label">Monthly EMI</div>
      <div class="info-value">₱{{ number_format($summary['emi'], 2) }}</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Month</th>
        <th>Due Date</th>
        <th>Payment (₱)</th>
        <th>Principal (₱)</th>
        <th>Interest (₱)</th>
        <th>Balance (₱)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($schedule as $row)
        <tr>
          <td>{{ $row['month'] }}</td>
          <td>{{ \Carbon\Carbon::parse($row['due_date'])->format('M d, Y') }}</td>
          <td>{{ number_format($row['payment'], 2) }}</td>
          <td>{{ number_format($row['principal'], 2) }}</td>
          <td>{{ number_format($row['interest'], 2) }}</td>
          <td>{{ number_format($row['remaining_balance'], 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div class="summary-grid">
    <div class="summary-item">
      <div class="summary-label">Monthly EMI</div>
      <div class="summary-value">₱{{ number_format($summary['emi'], 2) }}</div>
    </div>
    <div class="summary-item">
      <div class="summary-label">Total Interest</div>
      <div class="summary-value">₱{{ number_format($summary['total_interest'], 2) }}</div>
    </div>
    <div class="summary-item">
      <div class="summary-label">Total Paid</div>
      <div class="summary-value">₱{{ number_format($summary['total_paid'], 2) }}</div>
    </div>
  </div>

  <!-- Risk Metrics Section -->
  @if(isset($riskMetrics))
    <div class="risk-section">
      <h3 style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em;">
        AI Risk Breakdown
      </h3>
      <div class="risk-grid">
        <div>
          <div class="info-label">Debt-to-Income Ratio</div>
          <div style="margin-top: 4px;">
            @if($riskMetrics['dti'] !== null)
              <span class="risk-badge @if($riskMetrics['dti'] > 40) risk-high @elseif($riskMetrics['dti'] > 25) risk-medium @else risk-low @endif">
                {{ $riskMetrics['dti'] }}%
              </span>
            @else
              <span style="color: #6b7280;">N/A (no income data)</span>
            @endif
          </div>
        </div>
        <div>
          <div class="info-label">Savings-to-Loan Ratio</div>
          <div style="margin-top: 4px;">
            @if($riskMetrics['savings_to_loan'] !== null)
              <span class="risk-badge @if($riskMetrics['savings_to_loan'] < 20) risk-high @elseif($riskMetrics['savings_to_loan'] < 50) risk-medium @else risk-low @endif">
                {{ $riskMetrics['savings_to_loan'] }}%
              </span>
            @else
              <span style="color: #6b7280;">N/A</span>
            @endif
          </div>
        </div>
        <div>
          <div class="info-label">Payment History</div>
          <div style="margin-top: 4px;">
            <span class="risk-badge @if($riskMetrics['delinquency_flagged']) risk-high @else risk-low @endif">
              {{ $riskMetrics['delinquency_status'] }}
            </span>
          </div>
        </div>
        <div>
          <div class="info-label">AI Score</div>
          <div style="margin-top: 4px;">
            <span class="risk-badge @if(($application->ai_score ?? 0) < 50) risk-high @elseif(($application->ai_score ?? 0) < 80) risk-medium @else risk-low @endif">
              {{ $application->ai_score ?? 'N/A' }}/100
            </span>
          </div>
        </div>
      </div>

      @if($riskMetrics['override_reason'])
        <div class="override-box">
          <div class="override-label">Manager Override Reason</div>
          <div class="override-text">{{ $riskMetrics['override_reason'] }}</div>
        </div>
      @endif
    </div>
  @endif

</body>
</html>