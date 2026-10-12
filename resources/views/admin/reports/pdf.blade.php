<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Loan Reports</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; color: #0f172a; padding: 2rem; }
    h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
    .subtitle { color: #64748b; margin-bottom: 2rem; }
    .meta { display: flex; gap: 2rem; margin-bottom: 2rem; font-size: 0.85rem; color: #334155; }
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .kpi-card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 1rem; background: #fff; }
    .kpi-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin-bottom: 0.3rem; }
    .kpi-val { font-size: 1.5rem; font-weight: 700; color: #0f172a; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; font-size: 0.8rem; }
    th, td { border: 1px solid #cbd5e1; padding: 0.5rem; text-align: left; }
    th { background: #f1f5f9; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .status-approved { color: #059669; font-weight: 600; }
    .status-pending { color: #d97706; font-weight: 600; }
    .status-rejected { color: #dc2626; font-weight: 600; }
    @page { margin: 1.5cm; }
  </style>
</head>
<body>
  <h1>San Dionisio Credit Cooperative</h1>
  <div class="subtitle">Loan Reports & Analytics</div>

  <div class="meta">
    <div>Report Generated: {{ now()->format('M d, Y h:i A') }}</div>
    <div>Date Range: {{ $dateFrom ?? 'All' }} to {{ $dateTo ?? 'Present' }}</div>
    <div>Status Filter: {{ ucfirst(request()->input('status', 'all')) }}</div>
    <div>Product Filter: {{ request()->input('product', 'all') }}</div>
  </div>

  <!-- KPI Summary -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Total Applications</div>
      <div class="kpi-val">{{ $totalApps }}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Approved</div>
      <div class="kpi-val">{{ $approvedCount }}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Pending</div>
      <div class="kpi-val">{{ $pendingCount }}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Rejected</div>
      <div class="kpi-val">{{ $rejectedCount }}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total Disbursed</div>
      <div class="kpi-val">₱{{ number_format($totalDisbursed, 2) }}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Avg AI Score</div>
      <div class="kpi-val">{{ number_format($avgAiScore, 1) }}</div>
    </div>
  </div>

  <!-- Approved Loan Records Table -->
  <h2 style="margin-bottom: 1rem; font-size: 1.2rem;">Approved Loan Records</h2>
  <table>
    <thead>
      <tr>
        <th>Application ID</th>
        <th>Applicant</th>
        <th>Loan Type</th>
        <th>Amount</th>
        <th>Term (Months)</th>
        <th>AI Score</th>
        <th>Monthly Income</th>
        <th>Date Approved</th>
      </tr>
    </thead>
    <tbody>
      @forelse($approvedLoans as $loan)
        <tr>
          <td>{{ $loan->app_id ?? $loan->reference }}</td>
          <td>{{ $loan->full_name }}</td>
          <td>{{ config('loan_matrix.loan_types.' . $loan->loan_type, $loan->loan_type) }}</td>
          <td>₱{{ number_format($loan->amount, 2) }}</td>
          <td>{{ $loan->term_months }}</td>
          <td>{{ $loan->ai_score }}</td>
          <td>₱{{ number_format($loan->monthly_income, 2) }}</td>
          <td>{{ \Carbon\Carbon::parse($loan->reviewed_at)->format('M d, Y') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="8" style="text-align:center;color:#64748b;padding:1rem;">No approved loans found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <!-- Borrower Performance Table -->
  <h2 style="margin-bottom: 1rem; font-size: 1.2rem;">Borrower Performance & Delinquency</h2>
  <table>
    <thead>
      <tr>
        <th>Borrower ID</th>
        <th>Full Name</th>
        <th>Active Loans</th>
        <th>Total Borrowed</th>
        <th>Paid Balance</th>
        <th>Remaining Balance</th>
        <th>Overdue Amount</th>
        <th>Delinquency Status</th>
        <th>Last Payment</th>
      </tr>
    </thead>
    <tbody>
      @forelse($borrowerPerformance as $borrower)
        <tr>
          <td>{{ $borrower->borrower_id }}</td>
          <td>{{ $borrower->full_name }}</td>
          <td>{{ $borrower->active_loans_count }}</td>
          <td>₱{{ number_format($borrower->total_borrowed, 2) }}</td>
          <td>₱{{ number_format($borrower->paid_balance, 2) }}</td>
          <td>₱{{ number_format($borrower->remaining_balance, 2) }}</td>
          <td>
            @php $overdueColor = $borrower->overdue_amount > 0 ? '#dc2626' : '#059669'; @endphp
            <span style="font-weight:600; color: {{ $overdueColor }}">₱{{ number_format($borrower->overdue_amount, 2) }}</span>
          </td>
          <td>
            @if ($borrower->overdue_amount > 0)
              <span class="status-rejected">Delinquent</span>
            @elseif ($borrower->remaining_balance > 0)
              <span class="status-pending">Active</span>
            @else
              <span class="status-approved">Current</span>
            @endif
          </td>
          <td>{{ $borrower->last_payment_date ?? '—' }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="9" style="text-align:center;color:#64748b;padding:1rem;">No borrower data found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>