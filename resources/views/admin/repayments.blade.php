<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Repayments</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  @include('admin.css')
</head>

<body>

<!-- SIDEBAR -->
@include('admin.sidebar')

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">Repayments <span>/ Payment Management</span></div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <div class="notif-dropdown" id="notifDropdown">
    <div class="notif-head">
      <div class="notif-head-title">Notifications</div>
      <div class="notif-mark-all" onclick="markAllRead()">Mark all read</div>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="content-body">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Repayments</h2>
        </div>
      </div>

      <!-- Summary Panel -->
      <div class="panel" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <div>
            <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1.05rem;">Total Repayments Collected</div>
            <div style="font-size: 2rem; font-weight: 700; color: #1e293b;">₱{{ number_format($totalRepayments ?? 0, 2) }}</div>
          </div>
          <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.75rem 1.25rem;">
            <div style="font-size: 0.875rem; color: #64748b;">Total Payment Records</div>
            <div style="font-weight: 700; color: #1e293b;">{{ $repayments->count() }}</div>
          </div>
        </div>
      </div>

      <!-- Repayments Data Table -->
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>PAYMENT REF/ID</th>
              <th>BORROWER NAME</th>
              <th>LOAN APPLICATION ID</th>
              <th>AMOUNT PAID</th>
              <th>PAYMENT METHOD</th>
              <th>DATE PAID</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($repayments as $repayment)
              <tr>
                <td class="font-semibold text-gray-700">#{{ $repayment->reference_number ?? 'N/A' }}</td>
                <td>{{ $repayment->borrower_name ?? 'N/A' }}</td>
                <td><span class="loan-type-tag">{{ $repayment->loan_application_id ?? 'N/A' }}</span></td>
                <td>₱{{ number_format($repayment->amount_paid ?? 0, 2) }}</td>
                <td><span class="status-pill {{ strtolower($repayment->payment_method ?? 'cash') }}">{{ ucfirst($repayment->payment_method ?? 'cash') }}</span></td>
                <td>{{ \Carbon\Carbon::parse($repayment->payment_date ?? now())->format('M d, Y') }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary"
                          style="border: 1px solid #cbd5e1; background-color: #ffffff; color: #1e293b; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; cursor: pointer;"
                          onclick="recordNewRepayment()">
                    Record Repayment
                  </button>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:2rem;">No repayment records found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- RECORD REPAYMENT MODAL -->
  <div class="modal" id="recordRepaymentModal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">💰 Record New Repayment</div>
      <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <form action="{{ route('repayments.store') }}" method="POST">
      @csrf

      <input type="hidden" name="application_id" id="repayAppId" value="">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Application ID</label>
          <input type="text" name="app_id" id="repayAppIdInput" class="form-input" placeholder="#APP-0001" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Borrower Name</label>
          <input type="text" name="borrower_name" class="form-input" placeholder="Full name" required/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount Paid (₱)</label>
          <input type="number" step="0.01" name="amount_paid" class="form-input" placeholder="0.00" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select name="payment_method" class="form-select">
            <option value="">Select Method</option>
            <option value="Cash">Cash</option>
            <option value="Bank Transfer">Bank Transfer</option>
            <option value="GCash">GCash</option>
            <option value="Maya">Maya</option>
            <option value="BPI">BPI</option>
            <option value="BDO">BDO</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Reference Number</label>
          <input type="text" name="reference_number" class="form-input" placeholder="Optional reference"/>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" class="form-input" required/>
        </div>
      </div>

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn-submit"
          style="background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #ffffff; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.875rem; font-weight: 700; width: 100%; cursor: pointer;">
          Record Repayment
        </button>
        <button type="button" class="btn-cancel"
          style="background: transparent; color: var(--muted); border: 1px solid var(--muted); border-radius: 8px; padding: 0.6rem 1.2rem; font-size: 0.875rem; font-weight: 500; width: 100%; cursor: pointer;"
          onclick="closeAllModals()">Cancel</button>
      </div>
    </form>
  </div>

</div>

<!-- GLOBAL OVERLAY FOR MODALS -->
<div class="overlay" id="overlay" onclick="closeAllModals()"></div>

<script>
  // ─── RECORD REPAYMENT MODAL LOGIC ───
  function recordNewRepayment() {
    // Show the modal with the first application pre-filled (or you could implement a selector)
    const modal = document.getElementById('recordRepaymentModal');
    if (!modal) return;

    // Optionally pre-fill with a default app ID - you can customize this
    const appIdInput = document.getElementById('repayAppIdInput');
    if (appIdInput) {
      // Default to first pending application or leave empty for selector
      appIdInput.value = '#APP-0001';
    }

    // Pre-fill borrower name from the app if available
    const borrowerInput = modal.querySelector('input[name="borrower_name"]');
    if (borrowerInput) {
      borrowerInput.value = '';
    }

    openModal('recordRepaymentModal');
  }

  function closeRecordRepaymentModal() {
    closeAllModals();
  }
</script>

</body>
</html>