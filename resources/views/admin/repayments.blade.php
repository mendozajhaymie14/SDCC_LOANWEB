<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}"/>
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
        <span>🔔</span><span class="notif-dot" id="notifDot"></span>
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
          <div style="font-size:0.875rem;color:#64748b;margin-top:0.25rem;">Total Payment Records: {{ $repayments->count() }}</div>
        </div>
        <button class="btn btn-new" onclick="recordNewRepayment()">+ Record Payment</button>
      </div>

      <!-- Summary Panel -->
      <div class="panel" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <div>
            <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1.05rem;">Total Repayments Collected</div>
            <div style="font-size: 2rem; font-weight: 700; color: #1e293b;">{{ \App\Support\Format::money($totalRepayments) }}</div>
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
              <th>Payment Ref</th>
              <th>Borrower Name</th>
              <th>Loan Application ID</th>
              <th>Amount Paid</th>
              <th>Payment Method</th>
              <th>Date Paid</th>
              <th>Balance After</th>
              <th>Recorded By</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($repayments as $repayment)
              @php
                $refLabel = $repayment->refLabel();
                $borrowerName = $repayment->borrowerName();
                $appId = $repayment->appId();
                $amount = (float)($repayment->amount_paid ?? 0);
                $method = $repayment->payment_method ?? 'Cash';
                $dateStr = $repayment->payment_date ? \Carbon\Carbon::parse($repayment->payment_date)->format('Y-m-d') : '';
                $notes = $repayment->notes ?? '';
                $balanceAfter = (float)($repayment->remaining_balance_after ?? 0);
                $loanBalance = (float)($repayment->loanApplication?->remaining_balance ?? 0);
                $nextDue = $repayment->loanApplication?->next_due_date ?? null;
                $recordedBy = $repayment->encoder?->name ?? '—';
                $paymentDateDisplay = $repayment->payment_date ? \Carbon\Carbon::parse($repayment->payment_date)->format('M d, Y') : '';
              @endphp
              <tr>
                <td class="font-semibold text-gray-700">{{ $refLabel }}</td>
                <td>{{ $borrowerName }}</td>
                <td><span class="loan-type-tag">#{{ $appId }}</span></td>
                <td>{{ \App\Support\Format::money($amount) }}</td>
                <td><span class="status-pill {{ strtolower($method) }}">{{ ucfirst($method) }}</span></td>
                <td>{{ $paymentDateDisplay }}</td>
                <td>{{ \App\Support\Format::money($balanceAfter) }}</td>
                <td>{{ $recordedBy }}</td>
                <td>
                  <button type="button" class="btn btn-sm btn-review btn-view-repayment"
                          data-repayment-id="{{ $repayment->id }}"
                          data-ref-label="{{ $refLabel }}"
                          data-borrower="{{ $borrowerName }}"
                          data-app-id="{{ $appId }}"
                          data-amount="{{ $amount }}"
                          data-method="{{ $method }}"
                          data-reference="{{ $repayment->reference_no ?? '' }}"
                          data-date="{{ $dateStr }}"
                          data-notes="{{ $notes }}"
                          data-remain-balance="{{ $balanceAfter }}"
                          data-loan-balance="{{ $loanBalance }}"
                          data-next-due="{{ $nextDue ?? '' }}"
                          data-recorded-by="{{ $recordedBy }}">
                    <i class="fa-solid fa-eye" style="margin-right:4px;"></i>View
                  </button>
                  <button type="button" class="btn btn-sm btn-review btn-edit-repayment"
                          data-repayment-id="{{ $repayment->id }}"
                          data-app-id="{{ $appId }}"
                          data-borrower="{{ $borrowerName }}"
                          data-amount="{{ $amount }}"
                          data-method="{{ $method }}"
                          data-reference="{{ $repayment->reference_no ?? '' }}"
                          data-date="{{ $dateStr }}"
                          data-notes="{{ $notes }}"
                          data-remain-balance="{{ $balanceAfter }}"
                          data-loan-balance="{{ $loanBalance }}"
                          data-next-due="{{ $nextDue ?? '' }}"
                          data-recorded-by="{{ $recordedBy }}">
                    <i class="fa-solid fa-pen" style="margin-right:4px;"></i>Edit
                  </button>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No repayment records found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- RECORD REPAYMENT MODAL -->
  <div class="modal"
       id="modal-recordRepaymentModal"
       onclick="event.stopPropagation()"
       style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
      <div class="modal-title" id="recordModalTitle">💰 Record New Repayment</div>
      <div class="modal-close" type="button" onclick="closeRepaymentModal()">✕</div>
    </div>
    <form id="recordRepaymentForm" action="{{ route('repayments.store') }}" method="POST" novalidate>
      @csrf

      <input type="hidden" name="application_id" id="repayAppId" value="">

      <div style="margin-top: 1rem;">
        <label class="form-label">Select Loan</label>
        <select name="loan_select" id="loanSelect" class="form-select" onchange="populateLoanFields(this)">
          <option value="">-- Select a loan --</option>
          @foreach($approvedLoans as $loan)
            @php
              $borrowerName = $loan->loanApplication?->fullName() ?? $loan->applicant ?? 'Unknown';
              $appId = $loan->app_id ?? $loan->id;
              $remainingBalance = (float)($loan->remaining_balance ?? 0);
            @endphp
            <option value="{{ $loan->id }}"
                    data-app-id="{{ $appId }}"
                    data-borrower="{{ $borrowerName }}"
                    data-balance="{{ $remainingBalance }}">
              #{{ $appId }} — {{ $borrowerName }} ({{\App\Support\Format::money($remainingBalance)}} remaining)
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-row" style="margin-top: 1rem;">
        <div class="form-group">
          <label class="form-label">Application ID</label>
          <input type="text" name="app_id" id="repayAppIdInput" class="form-input" placeholder="APP-0001" readonly/>
        </div>
        <div class="form-group">
          <label class="form-label">Borrower Name</label>
          <input type="text" name="borrower_name" id="borrower_name" class="form-input" placeholder="Full name" readonly/>
        </div>
      </div>

      <div class="form-row" style="margin-top: 1rem;">
        <div class="form-group">
          <label class="form-label">Amount Paid (₱)</label>
          <input type="number" step="0.01" name="amount" id="repayAmount" class="form-input" placeholder="0.00" required min="0.01"/>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select name="payment_method" id="repayMethod" class="form-select">
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

      <div class="form-row" style="margin-top: 1rem;">
        <div class="form-group">
          <label class="form-label">Reference Number</label>
          <input type="text" name="reference_no" id="repayReference" class="form-input" placeholder="Optional reference"/>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" id="repayDate" class="form-input" required/>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label class="form-label" for="repayNotes">Notes</label>
        <textarea name="notes" id="repayNotes" class="form-input" rows="3" placeholder="Optional notes"></textarea>
      </div>

      <!-- Info strip: Current outstanding balance -->
      <div class="form-group" style="margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
        <div style="font-size:0.85rem;color:#64748b;">Current outstanding balance</div>
        <div style="font-weight:700;color:#1e293b;font-size:1.1rem;">₱<span id="currentBalanceDisplay">—</span></div>
      </div>

      <!-- Info strip: Remaining balance after this payment -->
      <div class="form-group" style="margin-top: 0.5rem; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
        <div style="font-size:0.85rem;color:#64748b;">Remaining balance after this payment</div>
        <div style="font-weight:700;color:#1e293b;font-size:1.1rem;">₱<span id="balancePreview">—</span></div>
        <small id="balanceError" style="color:#ef4444;display:none;">Amount exceeds current balance</small>
      </div>

      <!-- Server error display (inline) -->
      <div id="recordError" style="color:#ef4444;font-size:0.875rem;margin-top:0.75rem;display:none;"></div>

      <div style="margin-top: 1.5rem; display:flex; gap:0.75rem;">
        <button type="submit" class="btn-submit" id="recordSubmitBtn">
          <span id="recordModalBtnText">Record Repayment</span>
        </button>
        <button type="button" class="btn-cancel" onclick="closeRepaymentModal()">Cancel</button>
      </div>
    </form>
  </div>

  <!-- EDIT REPAYMENT MODAL (DEDICATED - not reusing Record form) -->
  <div class="modal"
       id="modal-editRepaymentModal"
       onclick="event.stopPropagation()"
       style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Payment</div>
      <div class="modal-close" type="button" onclick="closeEditRepaymentModal()">✕</div>
    </div>
    <div id="editLoanSummary" class="loan-summary" style="padding: 1.5rem;">
      <!-- Loan summary block will be populated by JS -->
    </div>
    <form id="editRepaymentForm" action="" method="POST" novalidate>
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" name="repayment_id" id="editRepaymentId" value="">

      <div class="form-group" style="padding: 0 1.5rem;">
        <label class="form-label">Amount Paid (₱)</label>
        <input type="number" step="0.01" name="amount" id="editAmount" class="form-input" placeholder="0.00" required/>
      </div>

      <div class="form-row" style="padding: 0 1.5rem;">
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select name="payment_method" id="editMethod" class="form-select">
            <option value="">Select Method</option>
            <option value="Cash">Cash</option>
            <option value="Bank Transfer">Bank Transfer</option>
            <option value="GCash">GCash</option>
            <option value="Maya">Maya</option>
            <option value="BPI">BPI</option>
            <option value="BDO">BDO</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Reference Number</label>
          <input type="text" name="reference_no" id="editReference" class="form-input" placeholder="Optional reference"/>
        </div>
      </div>

      <div class="form-row" style="padding: 0 1.5rem;">
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" id="editDate" class="form-input" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <input type="text" name="notes" id="editNotes" class="form-input" placeholder="Optional notes"/>
        </div>
      </div>

      <div style="padding: 0 1.5rem;">
        <label class="form-label">Recorded By</label>
        <div id="editRecordedBy" style="font-weight:600;color:#1e293b;">—</div>
      </div>

      <div class="form-group" style="margin-top: 1rem; padding: 0 1.5rem;">
        <div style="font-size:0.85rem;color:#64748b;">Balance after this edit</div>
        <div style="font-weight:700;color:#1e293b;font-size:1.1rem;">₱<span id="editBalancePreview">0.00</span></div>
        <small style="color:#64748b;">calculated as: balance - new amount + old amount</small>
      </div>

      <!-- Server error display (inline) -->
      <div id="editError" style="color:#ef4444;font-size:0.875rem;margin:0.75rem 1.5rem 0;display:none;"></div>

      <div style="margin-top: 1.5rem; display:flex; gap:0.75rem; padding: 0 1.5rem;">
        <button type="submit" class="btn-submit" id="editSubmitBtn">
          <span id="editModalBtnText">Save Changes</span>
        </button>
        <button type="button" class="btn-cancel" onclick="closeEditRepaymentModal()">Cancel</button>
      </div>
    </form>
  </div>

  <!-- VIEW REPAYMENT MODAL -->
  <div class="modal"
       id="modal-viewRepaymentModal"
       onclick="event.stopPropagation()"
       style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
      <div class="modal-title" id="viewModalTitle">💰 Payment Details</div>
      <div class="modal-close" type="button" onclick="closeViewRepaymentModal()">✕</div>
    </div>
    <div style="padding: 1.5rem;">
      <!-- Header: ref display with "Payment REP-" prefix -->
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
        <div style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;padding:0.4rem 0.8rem;color:#1e293b;font-weight:700;font-size:1rem;">
          <span id="viewRepaymentTitle">Payment REP-</span>
        </div>
      </div>

      <!-- Grouped label/value rows (not input-looking boxes) -->
      <div class="form-row" style="margin-bottom:0.75rem;">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Application ID</label>
          <div id="viewRepaymentAppId" style="font-weight:600;color:#1e293b;">—</div>
        </div>
        <div class="form-group" style="flex:1;">
          <label class="form-label">Borrower Name</label>
          <div id="viewRepaymentBorrower" style="font-weight:600;color:#1e293b;">—</div>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:0.75rem;">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Amount Paid</label>
          <div id="viewRepaymentAmount" style="font-weight:600;color:#1e293b;">₱0.00</div>
        </div>
        <div class="form-group" style="flex:1;">
          <label class="form-label">Payment Method</label>
          <div id="viewRepaymentMethod" style="font-weight:600;color:#1e293b;">—</div>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:0.75rem;">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Reference Number</label>
          <div id="viewRepaymentReference" style="font-weight:600;color:#1e293b;">—</div>
        </div>
        <div class="form-group" style="flex:1;">
          <label class="form-label">Payment Date</label>
          <div id="viewRepaymentDate" style="font-weight:600;color:#1e293b;">—</div>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:0.75rem;">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Balance After</label>
          <div id="viewRepaymentBalance" style="font-weight:600;color:#1e293b;">₱0.00</div>
        </div>
        <div class="form-group" style="flex:1;">
          <label class="form-label">Recorded By</label>
          <div id="viewRepaymentRecorded" style="font-weight:600;color:#1e293b;">—</div>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label">Notes</label>
        <div id="viewRepaymentNotes" style="font-weight:600;color:#1e293b;">—</div>
      </div>

      <div style="margin-top: 1.5rem; display:flex;gap:0.75rem;justify-content:flex-end;">
        <button type="button" class="btn-cancel" onclick="closeViewRepaymentModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    // Modal open/close helpers
    function openRepaymentModal() {
      const modal = document.getElementById('modal-recordRepaymentModal');
      if (!modal) return;
      modal.classList.add('open');
    }

    function closeRepaymentModal() {
      const modal = document.getElementById('modal-recordRepaymentModal');
      if (modal) modal.classList.remove('open');
    }

    function closeEditRepaymentModal() {
      const modal = document.getElementById('modal-editRepaymentModal');
      if (modal) modal.classList.remove('open');
    }

    function closeViewRepaymentModal() {
      const modal = document.getElementById('modal-viewRepaymentModal');
      if (modal) modal.classList.remove('open');
    }

    // Helper: read all data-* attributes from a button as a plain object
    function rowData(btn) {
      const d = {};
      if (!btn || !btn.dataset) return d;
      for (const key of Object.keys(btn.dataset)) {
        d[key] = btn.dataset[key];
      }
      return d;
    }

    // Format money with thousands separators and 2 decimals
    function formatMoney(amount) {
      const n = parseFloat(amount || 0);
      return '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Record a NEW repayment
    function recordNewRepayment() {
      const modal   = document.getElementById('modal-recordRepaymentModal');
      const title   = document.getElementById('recordModalTitle');
      const form    = document.getElementById('recordRepaymentForm');

      if (!modal || !title || !form) return;

      title.textContent = '💰 Record New Repayment';
      form.action = '{{ route("repayments.store") }}';
      form.method = 'POST';

      // Remove any _method spoofing left over from an Edit session
      let methodInput = modal.querySelector('input[name="_method"]');
      if (methodInput) methodInput.remove();

      // Reset form fields
      const appId   = document.getElementById('repayAppId');
      const amount  = document.getElementById('repayAmount');
      const select  = document.getElementById('loanSelect');
      const repayId = document.getElementById('repaymentId');
      const method  = document.getElementById('repayMethod');
      const ref     = document.getElementById('repayReference');
      const date    = document.getElementById('repayDate');
      const notes   = document.getElementById('repayNotes');
      const btnText = document.getElementById('recordModalBtnText');
      const appIdInput = document.getElementById('repayAppIdInput');

      if (appId)   appId.value   = '';
      if (appIdInput) appIdInput.value = '';
      if (amount)  amount.value  = '';
      if (select)  select.selectedIndex = 0;
      if (repayId) repayId.value = '';
      if (method)  method.selectedIndex = 0;
      if (ref)     ref.value     = '';
      if (date)    date.value    = '';
      if (notes)   notes.value   = '';
      if (btnText) btnText.textContent = 'Record Repayment';

      // Reset loan balance data attribute
      if (amount) amount.dataset.loanBalance = '';

      // Set date defaults: today, not future
      const today = new Date().toISOString().split('T')[0];
      date.max = today;
      date.value = today;

      // Clear balance preview
      const preview = document.getElementById('balancePreview');
      if (preview) preview.textContent = '—';
      const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
      if (currentBalanceDisplay) currentBalanceDisplay.textContent = '—';
      const errorEl = document.getElementById('balanceError');
      if (errorEl) errorEl.style.display = 'none';

      openRepaymentModal();
    }

    // View repayment details
    function viewRepayment(data) {
      const ref        = data.refLabel || '';
      const app        = data.appId || '';
      const borrower   = data.borrower || '—';
      const amount     = parseFloat(data.amount || 0);
      const method     = data.method || '—';
      const reference  = data.reference || '—';
      const date       = data.date || '';
      const notes      = data.notes || '—';
      const balance    = parseFloat(data.remainBalance || 0);
      const loanBalance = parseFloat(data.loanBalance || 0);
      const recorded   = data.recordedBy || '—';
      const nextDue    = data.nextDue || '';
      const repaymentId = data.repaymentId || '';

      // Extract repayment number for title (remove # prefix if present)
      const repNum = ref.startsWith('#') ? ref.substring(1) : ref || '—';

      document.getElementById('viewRepaymentTitle').textContent = 'Payment ' + repNum;
      document.getElementById('viewRepaymentAppId').textContent   = '#' + app;
      document.getElementById('viewRepaymentBorrower').textContent   = borrower;
      document.getElementById('viewRepaymentAmount').textContent     = formatMoney(amount);
      document.getElementById('viewRepaymentMethod').textContent     = method;
      document.getElementById('viewRepaymentReference').textContent  = reference;
      // Format date as "Oct 28, 2026"
      document.getElementById('viewRepaymentDate').textContent       = date ? new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
      document.getElementById('viewRepaymentBalance').textContent    = formatMoney(balance);
      document.getElementById('viewRepaymentRecorded').textContent   = recorded;
      document.getElementById('viewRepaymentNotes').textContent      = notes;

      // Store the actual payment ID so openEditFromView can find the Edit button
      currentViewData = {
        repaymentId: repaymentId,
        appId: app,
        borrower: borrower,
        amount: amount.toFixed(2),
        method: method,
        reference: reference,
        date: date,
        notes: notes,
        remainBalance: balance.toFixed(2),
        loanBalance: loanBalance.toFixed(2),
        nextDue: nextDue,
        recordedBy: recorded
      };

      const modal = document.getElementById('modal-viewRepaymentModal');
      if (modal) modal.classList.add('open');
    }

    // Edit existing repayment - opens dedicated Edit modal
    function editRepayment(data) {
      const modal   = document.getElementById('modal-editRepaymentModal');
      const loanSummary = document.getElementById('editLoanSummary');

      if (!modal || !loanSummary) return;

      // Build loan summary (read-only)
      loanSummary.innerHTML = `
        <div style="padding:1.5rem;">
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:1rem;margin-bottom:1rem;">
            <div style="font-size:0.8rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Loan Summary</div>
            <div class="form-row" style="margin-top:0.5rem;">
              <div class="form-group" style="flex:1;">
                <label class="form-label" style="font-size:0.75rem;color:#64748b;">Application ID</label>
                <div style="font-weight:600;color:#1e293b;">#${data.appId || '—'}</div>
              </div>
              <div class="form-group" style="flex:1;">
                <label class="form-label" style="font-size:0.75rem;color:#64748b;">Borrower</label>
                <div style="font-weight:600;color:#1e293b;">${data.borrower || '—'}</div>
              </div>
            </div>
            <div class="form-row" style="margin-top:0.5rem;">
              <div class="form-group" style="flex:1;">
                <label class="form-label" style="font-size:0.75rem;color:#64748b;">Outstanding Balance</label>
                <div style="font-weight:600;color:#1e293b;">₱${parseFloat(data.loanBalance || data.remainBalance || 0).toFixed(2)}</div>
              </div>
              <div class="form-group" style="flex:1;">
                <label class="form-label" style="font-size:0.75rem;color:#64748b;">Next Due Date</label>
                <div style="font-weight:600;color:#1e293b;">${data.nextDue ? new Date(data.nextDue).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'}</div>
              </div>
            </div>
          </div>
        </div>
      `;

      // Form setup
      const form = document.getElementById('editRepaymentForm');
      const repayId = document.getElementById('editRepaymentId');
      const amount = document.getElementById('editAmount');
      const method = document.getElementById('editMethod');
      const ref = document.getElementById('editReference');
      const date = document.getElementById('editDate');
      const notes = document.getElementById('editNotes');
      const recordedBy = document.getElementById('editRecordedBy');

      if (!form) return;

      form.action = '/repayments/' + data.repaymentId;

      // Preload all fields from data attributes
      if (amount)  amount.value = data.amount || '';
      if (method)  method.value = data.method || '';
      if (ref)     ref.value = data.reference || '';
      if (date)    date.value = data.date || '';
      if (notes)   notes.value = data.notes || '';
      if (repayId) repayId.value = data.repaymentId || '';
      if (recordedBy) recordedBy.textContent = data.recordedBy || '—';

      // Set date constraints - today, not future
      const today = new Date().toISOString().split('T')[0];
      date.max = today;

      // Initialize balance preview with current balance (already reflects this payment)
      updateEditBalancePreview();

      document.getElementById('editError').style.display = 'none';
      document.getElementById('editModalBtnText').textContent = 'Save Changes';

      modal.classList.add('open');
    }

    // Balance preview for edit modal
    function updateEditBalancePreview() {
      const amountInput = document.getElementById('editAmount');
      const preview = document.getElementById('editBalancePreview');
      if (!amountInput || !preview) return;

      const newAmount = parseFloat(amountInput.value) || 0;
      const oldAmount = parseFloat(amountInput.dataset.oldAmount || '0');
      const loanBalance = parseFloat(preview.dataset.loanBalance || '0');

      // Balance after this edit = loan's outstanding balance + this payment's old amount - new amount
      // i.e., (loanBalance + oldAmount) - newAmount
      const newBalance = (loanBalance + oldAmount) - newAmount;
      preview.textContent = formatMoney(newBalance);
      preview.dataset.balance = newBalance.toFixed(2);
    }

    // Populate loan fields when loan is selected (Record modal only)
    function populateLoanFields(select) {
      const opt       = select.options[select.selectedIndex];
      if (!opt || !opt.value) return;

      const appIdInput = document.getElementById('repayAppIdInput');
      const borrowerInput = document.getElementById('borrower_name');
      const hiddenAppId = document.getElementById('repayAppId');

      if (appIdInput)   appIdInput.value   = opt.dataset.appId || '';
      if (borrowerInput) borrowerInput.value = opt.dataset.borrower || '';
      if (hiddenAppId)   hiddenAppId.value  = opt.value;

      // Store current balance from data-balance attribute
      const balance = parseFloat(opt.dataset.balance || 0);
      const amountInput = document.getElementById('repayAmount');
      if (amountInput) amountInput.dataset.loanBalance = balance;

      // Show current outstanding balance
      const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
      if (currentBalanceDisplay) {
        currentBalanceDisplay.textContent = balance.toFixed(2);
      }

      // Recalculate remaining balance preview
      updateRecordBalancePreview();

      // Auto-fill default date
      const dateInput = document.getElementById('repayDate');
      if (dateInput && !dateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
      }
    }

    // Recalculate remaining balance preview for Record modal
    function updateRecordBalancePreview() {
      const amountInput = document.getElementById('repayAmount');
      const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
      const preview = document.getElementById('balancePreview');
      const errorEl = document.getElementById('balanceError');

      if (!amountInput || !preview) return;

      const loanBalance = parseFloat(amountInput.dataset.loanBalance || 0);
      const amount = parseFloat(amountInput.value || 0);

      // Show current balance if no loan selected yet
      if (currentBalanceDisplay && !amountInput.dataset.loanBalance) {
        currentBalanceDisplay.textContent = '—';
      }

      if (!amountInput.dataset.loanBalance) {
        preview.textContent = '—';
        if (errorEl) errorEl.style.display = 'none';
        return;
      }

      const remaining = Math.max(0, loanBalance - amount);
      preview.textContent = remaining.toFixed(2);

      // Block amount above balance
      if (amount > loanBalance && amount > 0) {
        if (errorEl) errorEl.style.display = 'block';
      } else {
        if (errorEl) errorEl.style.display = 'none';
      }
    }

    // Form submit handlers - disable button while sending
    document.getElementById('recordRepaymentForm')?.addEventListener('submit', function (e) {
      const btn = document.getElementById('recordSubmitBtn');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        document.getElementById('recordModalBtnText').textContent = 'Saving…';
      }
    });

    document.getElementById('editRepaymentForm')?.addEventListener('submit', function (e) {
      const btn = document.getElementById('editSubmitBtn');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        document.getElementById('editModalBtnText').textContent = 'Saving…';
      }
    });

    // Event listeners for action buttons
    document.addEventListener('DOMContentLoaded', function () {
      // Date defaults to today, no future dates
      const dateInput = document.getElementById('repayDate');
      if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.max = today;
        dateInput.value = today;
      }

      // Balance preview on amount change for Record modal
      const amountInput = document.getElementById('repayAmount');
      if (amountInput) {
        amountInput.addEventListener('input', updateRecordBalancePreview);
      }

      // Balance preview on amount change for Edit modal
      const editAmountInput = document.getElementById('editAmount');
      if (editAmountInput) {
        editAmountInput.addEventListener('input', updateEditBalancePreview);
      }

      // View buttons
      document.querySelectorAll('.btn-view-repayment').forEach(function (btn) {
        btn.addEventListener('click', function () { viewRepayment(rowData(this)); });
      });

      // Edit buttons (table)
      document.querySelectorAll('.btn-edit-repayment').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const data = rowData(this);
          // Store old amount for balance calculation
          const amountInput = document.getElementById('editAmount');
          if (amountInput) amountInput.dataset.oldAmount = data.amount || 0;
          const preview = document.getElementById('editBalancePreview');
          if (preview) {
            preview.dataset.loanBalance = data.loanBalance || '0';
            preview.dataset.balance = data.loanBalance || '0';
            preview.textContent = formatMoney(parseFloat(data.loanBalance || 0));
          }
          editRepayment(data);
        });
      });

      });
  </script>

</body>
</html>