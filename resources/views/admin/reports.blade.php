<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Reports</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  @include ('admin.css')
  <style>
    /* ——————————— Report page specific enhancements ——————————— */
    /* Filter section: uniform height, single-row flex grid, aligned actions */
    .report-filter-grid {
      display: flex;
      align-items: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.8rem;
    }
    .report-filter-field {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
      min-width: 160px;
    }
    .report-filter-field label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--muted);
    }
    .report-filter-field .form-input,
    .report-filter-field .form-select {
      height: 42px;
      padding: 0 1rem;
      font-size: 0.85rem;
    }
    .report-filter-actions {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      margin-left: auto;
    }
    .report-filter-actions .btn-new {
      height: 42px;
      padding: 0 1.4rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      font-size: 0.82rem;
    }
    .report-filter-actions .btn-new:hover {
      opacity: 0.9;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    }
    .btn-reset {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted2);
      height: 42px;
      padding: 0 1.2rem;
      border-radius: 50px;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: all 0.15s ease;
    }
    .btn-reset:hover {
      border-color: var(--red);
      color: var(--red);
      background: rgba(220, 38, 38, 0.06);
    }

    /* KPI cards: two-row layout with accent border & hover elevation */
    .report-kpi-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.2rem;
      margin-bottom: 1rem;
    }
    .report-kpi-row.wide { grid-template-columns: repeat(2, 1fr); }
    .report-kpi-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1.3rem 1.5rem;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .report-kpi-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }
    .report-kpi-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 4px;
      height: 100%;
      background: var(--kpi-accent, var(--accent));
    }
    .report-kpi-label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--muted);
      margin-bottom: 0.35rem;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }
    .report-kpi-val {
      font-size: 2rem;
      font-weight: 800;
      color: var(--text);
      letter-spacing: -0.02em;
    }

    /* Table search overlay */
    .report-table-search {
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .report-table-search input {
      flex: 1;
      max-width: 360px;
      height: 38px;
      padding: 0 1rem;
      background: var(--card2);
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: 0.85rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .report-table-search input:focus { border-color: var(--accent); }

    /* Monospace / tabular-nums for identifiers & currency */
    .report-table .num-cell,
    .report-table .currency-cell {
      font-variant-numeric: tabular-nums;
    }
    .report-table .currency-cell { text-align: right; }

    /* AI Score badges */
    .ai-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 0.25rem 0.65rem;
      border-radius: 50px;
    }
    .ai-badge.good  { background: rgba(34, 197, 94, 0.12); color: var(--accent3); }
    .ai-badge.bad   { background: rgba(239, 68, 68, 0.12);  color: var(--red); }
    .ai-badge.na    { background: rgba(148, 163, 184, 0.15); color: var(--muted); }

    /* Delinquency status pills */
    .delinq-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.72rem;
      font-weight: 600;
      padding: 0.25rem 0.7rem;
      border-radius: 50px;
    }
    .delinq-pill.delinquent { background: rgba(239, 68, 68, 0.12);  color: var(--red); }
    .delinq-pill.active     { background: rgba(251, 191, 36, 0.12);  color: var(--gold); }
    .delinq-pill.current    { background: rgba(34, 197, 94, 0.12);   color: var(--accent3); }

    /* Smooth hover highlight on report table rows */
    .report-table tbody tr { transition: background 0.15s ease; }
    .report-table tbody tr:hover { background: rgba(2, 132, 199, 0.05); }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  @include ('admin.sidebar')

  <!-- MAIN WRAPPER -->
  <div class="main">
    <div class="content-body">
      <div class="page-header">
        <h2 class="page-title">Loan Reports & Analytics</h2>
        <div class="records-count">Last updated: {{ now()->format('M d, Y h:i A') }}</div>
      </div>

      <!-- FILTERS -->
      <div class="report-filter-grid">
        <div class="report-filter-field">
          <label class="form-label">Date Range</label>
          <input type="date" id="dateFrom" value="{{ $dateFrom ?? '' }}" class="form-input">
          <input type="date" id="dateTo" value="{{ $dateTo ?? '' }}" class="form-input">
        </div>
        <div class="report-filter-field">
          <label class="form-label">Status</label>
          <select id="statusFilter" class="form-select">
            <option value="all" {{ request()->input('status', 'all') === 'all' ? 'selected' : '' }}>All</option>
            <option value="approved" {{ request()->input('status') === 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="pending" {{ request()->input('status') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="rejected" {{ request()->input('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
          </select>
        </div>
        <div class="report-filter-field">
          <label class="form-label">Loan Product</label>
          <select id="productFilter" class="form-select">
            <option value="all" {{ request()->input('product', 'all') === 'all' ? 'selected' : '' }}>All Products</option>
            @foreach($loanTypes as $key => $label)
              <option value="{{ $key }}" {{ request()->input('product') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="report-filter-actions">
          <button class="btn-reset" onclick="resetFilters()" title="Clear all filters"><i class="fas fa-rotate-left"></i> Reset</button>
          <button class="btn-new" onclick="applyFilters()"><i class="fas fa-filter"></i> Apply Filters</button>
          <a href="{{ route('reports.exportPdf', request()->query()) }}" class="btn-new" style="background: var(--accent3);"><i class="fas fa-file-pdf"></i> Export PDF</a>
          <a href="{{ route('reports.exportExcel', request()->query()) }}" class="btn-new" style="background: var(--gold);"><i class="fas fa-file-excel"></i> Export Excel</a>
        </div>
      </div>

      <!-- LOAN SUMMARY CARDS -->
      <div class="report-kpi-row">
        <div class="report-kpi-card" style="--kpi-accent: var(--accent);">
          <div class="report-kpi-label"><i class="fas fa-file-alt"></i> Total Applications</div>
          <div class="report-kpi-val">{{ $totalApps }}</div>
        </div>
        <div class="report-kpi-card" style="--kpi-accent: var(--accent3);">
          <div class="report-kpi-label"><i class="fas fa-check-circle"></i> Approved</div>
          <div class="report-kpi-val">{{ $approvedCount }}</div>
        </div>
        <div class="report-kpi-card" style="--kpi-accent: var(--red);">
          <div class="report-kpi-label"><i class="fas fa-times-circle"></i> Rejected</div>
          <div class="report-kpi-val">{{ $rejectedCount }}</div>
        </div>
        <div class="report-kpi-card" style="--kpi-accent: var(--gold);">
          <div class="report-kpi-label"><i class="fas fa-hourglass-half"></i> Pending</div>
          <div class="report-kpi-val">{{ $pendingCount }}</div>
        </div>
      </div>
      <div class="report-kpi-row wide">
        <div class="report-kpi-card" style="--kpi-accent: var(--accent2);">
          <div class="report-kpi-label"><i class="fas fa-money-bill-wave"></i> Total Disbursed</div>
          <div class="report-kpi-val">₱{{ number_format($totalDisbursed, 2) }}</div>
        </div>
        <div class="report-kpi-card" style="--kpi-accent: var(--orange);">
          <div class="report-kpi-label"><i class="fas fa-brain"></i> Avg AI Score</div>
          <div class="report-kpi-val">{{ number_format($avgAiScore, 1) }}</div>
        </div>
      </div>

      <!-- APPROVED LOAN RECORDS TABLE -->
      <div class="panel" style="margin-top: 2rem;">
        <div class="page-header">
          <h3 class="panel-title">Approved Loan Records</h3>
          <p style="color: var(--muted);">List of all approved loans with borrower details and repayment information</p>
        </div>
        <div class="report-table-search">
          <i class="fas fa-search" style="color: var(--muted); font-size: 0.85rem;"></i>
          <input type="text" id="approvedSearch" class="form-input" placeholder="Search by applicant name or application ID...">
        </div>
        <div class="table-wrap">
          <table class="app-table report-table" id="approvedTable">
            <thead>
              <tr>
                <th>Application ID</th>
                <th>Applicant</th>
                <th>Loan Type</th>
                <th class="num-cell">Amount</th>
                <th class="num-cell">Term (Months)</th>
                <th>AI Score</th>
                <th class="num-cell">Monthly Income</th>
                <th>Status</th>
                <th>Date Approved</th>
              </tr>
            </thead>
            <tbody>
              @forelse($approvedLoans as $loan)
                <tr data-status="approved">
                  <td class="td-mono num-cell">{{ $loan->app_id ?? $loan->reference }}</td>
                  <td><strong>{{ $loan->full_name }}</strong></td>
                  <td><span class="loan-type-tag">{{ config('loan_matrix.loan_types.' . $loan->loan_type, $loan->loan_type) }}</span></td>
                  <td class="num-cell currency-cell">₱{{ number_format($loan->amount, 2) }}</td>
                  <td class="num-cell">{{ $loan->term_months }}</td>
                  <td class="td-mono">
                    @php
                      $score = $loan->ai_score ?? 0;
                      $aiClass = $score >= 51 ? 'good' : ($score > 0 ? 'bad' : 'na');
                    @endphp
                    <span class="ai-badge {{ $aiClass }}">
                      <i class="fas {{ $score >= 51 ? 'fa-check-circle' : ($score > 0 ? 'fa-exclamation-circle' : 'fa-minus-circle') }}"></i>
                      {{ $loan->ai_score ?? 'N/A' }}
                    </span>
                  </td>
                  <td class="num-cell currency-cell">₱{{ number_format($loan->monthly_income, 2) }}</td>
                  <td>
                    <span class="status-pill approved">
                      <span class="status-dot"></span>Approved
                    </span>
                  </td>
                  <td>{{ \Carbon\Carbon::parse($loan->reviewed_at)->format('M d, Y') }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No approved loans found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- BORROWER PERFORMANCE / DELINQUENCY TABLE -->
      <div class="panel" style="margin-top: 2rem;">
        <div class="page-header">
          <h3 class="panel-title">Borrower Performance & Delinquency</h3>
          <p style="color: var(--muted);">Overview of borrower loan performance, outstanding balances, and delinquency status</p>
        </div>
        <div class="report-table-search">
          <i class="fas fa-search" style="color: var(--muted); font-size: 0.85rem;"></i>
          <input type="text" id="borrowerSearch" class="form-input" placeholder="Search by borrower name or ID...">
        </div>
        <div class="table-wrap">
          <table class="app-table report-table" id="borrowerTable">
            <thead>
              <tr>
                <th>Borrower ID</th>
                <th>Full Name</th>
                <th class="num-cell">Active Loans</th>
                <th class="num-cell">Total Borrowed</th>
                <th class="num-cell">Paid Balance</th>
                <th class="num-cell">Remaining Balance</th>
                <th class="num-cell">Overdue Amount</th>
                <th>Delinquency Status</th>
                <th>Last Payment</th>
              </tr>
            </thead>
            <tbody>
              @forelse($borrowerPerformance as $borrower)
                <tr>
                  <td class="td-mono num-cell">{{ $borrower->borrower_id }}</td>
                  <td><strong>{{ $borrower->full_name }}</strong></td>
                  <td class="num-cell">{{ $borrower->active_loans_count }}</td>
                  <td class="num-cell currency-cell">₱{{ number_format($borrower->total_borrowed, 2) }}</td>
                  <td class="num-cell currency-cell">₱{{ number_format($borrower->paid_balance, 2) }}</td>
                  <td class="num-cell currency-cell">₱{{ number_format($borrower->remaining_balance, 2) }}</td>
                  <td class="num-cell currency-cell">
                    @php $overdueColor = $borrower->overdue_amount > 0 ? 'var(--red)' : 'var(--green)'; @endphp
                    <span class="ai-badge {{ $borrower->overdue_amount > 0 ? 'bad' : 'good' }}" style="color: {{ $overdueColor }}">
                      ₱{{ number_format($borrower->overdue_amount, 2) }}
                    </span>
                  </td>
                  <td>
                    @if ($borrower->overdue_amount > 0)
                      <span class="delinq-pill delinquent">
                        <span class="status-dot"></span>Delinquent
                      </span>
                    @elseif ($borrower->remaining_balance > 0)
                      <span class="delinq-pill active">
                        <span class="status-dot"></span>Active
                      </span>
                    @else
                      <span class="delinq-pill current">
                        <span class="status-dot"></span>Current
                      </span>
                    @endif
                  </td>
                  <td>{{ $borrower->last_payment_date ?? '—' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No borrower data found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @include('admin.footer')

  <script>
    // Lightweight toast fallback (no shared helper exists yet).
    window.toast = function (message, type) {
      type = type || 'info';
      var bg = type === 'error' ? 'rgba(220,38,38,0.95)'
             : type === 'success' ? 'rgba(5,150,105,0.95)'
             : 'rgba(15,23,42,0.9)';
      var t = document.createElement('div');
      t.style.cssText = 'position:fixed;top:24px;left:50%;transform:translateX(-50%);z-index:9999;background:' + bg + ';color:#fff;padding:0.7rem 1.3rem;border-radius:10px;font-size:0.85rem;font-weight:600;box-shadow:0 8px 20px rgba(0,0,0,0.2);opacity:0;transition:opacity 0.3s;';
      t.textContent = message;
      document.body.appendChild(t);
      setTimeout(function () { t.style.opacity = '1'; }, 30);
      setTimeout(function () { t.style.opacity = '0'; document.body.removeChild(t); }, 2400);
    };

    function applyFilters() {
      const dateFrom = document.getElementById('dateFrom').value;
      const dateTo = document.getElementById('dateTo').value;
      const status = document.getElementById('statusFilter').value;
      const product = document.getElementById('productFilter').value;

      const params = new URLSearchParams();
      if (dateFrom) params.append('date_from', dateFrom);
      if (dateTo) params.append('date_to', dateTo);
      if (status && status !== 'all') params.append('status', status);
      if (product && product !== 'all') params.append('product', product);

      window.location.href = '/reports?' + params.toString();
    }

    function resetFilters() {
      document.getElementById('dateFrom').value = '';
      document.getElementById('dateTo').value = '';
      document.getElementById('statusFilter').value = 'all';
      document.getElementById('productFilter').value = 'all';

      // Clear any live table search inputs and show all rows
      document.querySelectorAll('#approvedSearch, #borrowerSearch').forEach(function (input) {
        if (input) input.value = '';
      });
      document.querySelectorAll('.report-table tbody tr').forEach(function (row) { row.style.display = ''; });
    }

    // Enforce logical date bounds: start date cannot exceed end date.
    (function () {
      var dateFrom = document.getElementById('dateFrom');
      var dateTo = document.getElementById('dateTo');

      dateFrom.addEventListener('change', function () {
        if (dateTo.value && this.value > dateTo.value) {
          toast('Start date cannot be after the end date.', 'error');
          this.value = '';
        }
      });

      dateTo.addEventListener('change', function () {
        if (dateFrom.value && this.value < dateFrom.value) {
          toast('End date cannot be before the start date.', 'error');
          this.value = '';
        }
      });
    })();

    // Lightweight client-side live filtering for report tables.
    function filterTable(tableId) {
      var table = document.getElementById(tableId);
      if (!table) return;
      var wrap = table.closest('.panel');
      var input = wrap ? wrap.querySelector('.report-table-search input') : null;
      var term = input ? input.value.toLowerCase().trim() : '';
      var rows = table.querySelectorAll('tbody tr');
      rows.forEach(function (row) {
        if (!term) { row.style.display = ''; return; }
        var text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(term) !== -1 ? '' : 'none';
      });
    }

    document.querySelectorAll('#approvedSearch, #borrowerSearch').forEach(function (input) {
      if (!input) return;
      input.addEventListener('input', function () {
        filterTable(this.closest('.panel').querySelector('.report-table').id);
      });
    });

    // Apply filters on enter key press in date fields
    document.getElementById('dateFrom').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') applyFilters();
    });
    document.getElementById('dateTo').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') applyFilters();
    });
  </script>
</body>
</html>
