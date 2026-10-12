<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  @include ('admin.css')
</head>

<body>

<!-- SIDEBAR -->
@include ('admin.sidebar')

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">Repayment & Collection Management <span>/ Payment Management</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="repaymentSearchInput" placeholder="Search repayments…" oninput="handleRepaymentSearch(this.value)" onfocus="showRepaymentSearchResults()" onblur="setTimeout(hideRepaymentSearchResults,200)"/>
      </div>
      <div class="search-results" id="repaymentSearchResults"></div>
    </div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="content-body">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Payment Encoding</h2>
        </div>
      </div>

      <div class="filter-row">
        <button class="filter-btn active" onclick="filterRepayments('all',this)">All</button>
        <button class="filter-btn" onclick="filterRepayments('today',this)">Today</button>
        <button class="filter-btn" onclick="filterRepayments('this-month',this)">This Month</button>
      </div>

      <!-- Payment Encoding Card / Form -->
      <div class="panel encoding-card">
        <div class="panel-title" style="margin-bottom:1.2rem;">Encode Payment</div>
        <form id="encodePaymentForm" action="{{ route('repayments.store') }}" method="POST">
          @csrf
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Select Active Loan</label>
              <select class="form-select" id="activeLoanSelect" name="loan_application_id" onchange="loadLoanInfo()">
                <option value="">Select Active Loan</option>
                @foreach($activeLoans as $loan)
                  <option value="{{ $loan->id }}" data-borrower="{{ $loan->full_name ?? 'Applicant' }}" data-amount="{{ $loan->amount ?? 0 }}">
                    {{ $loan->full_name ?? 'Applicant' }} ({{ $loan->reference ?? ('#' . $loan->id) }})
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Borrower</label>
              <input type="text" class="form-input" id="borrowerName" placeholder="Auto-filled from loan selection" readonly/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Amount Paid (₱)</label>
              <input type="number" step="0.01" class="form-input" id="amountPaid" name="amount_paid" placeholder="0.00" required/>
            </div>
            <div class="form-group">
              <label class="form-label">Reference / OR No.</label>
              <input type="text" class="form-input" id="officialReceipt" name="reference_no" placeholder="OR-XXXXX"/>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Payment Date</label>
            <input type="date" class="form-input" id="paymentDate" name="payment_date" value="{{ date('Y-m-d') }}"/>
          </div>
          <div style="display:flex;gap:1rem;margin-top:1.5rem;">
            <button class="btn-submit" type="submit">Save Payment</button>
            <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
          </div>
        </form>
      </div>

      <!-- Payment History Table -->
      <div class="panel">
        <div class="panel-title" style="margin-bottom:1.2rem;">Payment History</div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Payment ID</th>
                <th>Borrower</th>
                <th>Loan ID</th>
                <th>Amount Paid</th>
                <th>Remaining Balance</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody id="paymentHistoryTbody">
              @forelse($payments as $pay)
                <tr>
                  <td class="td-mono">#{{ $pay->id }}</td>
                  <td>{{ $pay->borrower_name }}</td>
                  <td class="td-mono">{{ $pay->loan_id }}</td>
                  <td>₱{{ number_format($pay->amount_paid, 2) }}</td>
                  <td>₱{{ number_format($pay->remaining_balance, 2) }}</td>
                  <td>{{ \Carbon\Carbon::parse($pay->payment_date)->format('M d, Y') }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No payment records found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

@include('admin.footer')

</body>
</html>