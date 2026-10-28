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
          <div style="font-size:0.875rem;color:#64748b;margin-top:0.25rem;">Total Payment Records: {{ $repayments->count() }}</div>
        </div>
        <button class="btn-new" onclick="recordNewRepayment()">+ Record Payment</button>
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
                <td class="font-semibold text-gray-700">#{{ $repayment->reference_no ?? 'N/A' }}</td>
                <td>{{ $repayment->loanApplication?->user?->name ?? $repayment->loanApplication?->full_name ?? 'N/A' }}</td>
                <td><span class="loan-type-tag">#{{ $repayment->loanApplication?->app_id ?? $repayment->loan_application_id ?? 'N/A' }}</span></td>
                <td>₱{{ number_format($repayment->amount_paid ?? 0, 2) }}</td>
                <td><span class="status-pill {{ strtolower($repayment->payment_method ?? 'cash') }}">{{ ucfirst($repayment->payment_method ?? 'cash') }}</span></td>
                <td>{{ \Carbon\Carbon::parse($repayment->payment_date ?? now())->format('M d, Y') }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary"
                          style="border: 1px solid #cbd5e1; background-color: #ffffff; color: #1e293b; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; cursor: pointer;"
                          onclick="viewRepayment({{ $repayment->id }})">
                    View
                  </button>
                  <button class="btn btn-sm btn-outline-primary"
                          style="border: 1px solid #cbd5e1; background-color: #ffffff; color: #1e293b; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; cursor: pointer;"
                          onclick="editRepayment({{ $repayment->id }})"
                          data-repayment-id="{{ $repayment->id }}"
                          data-amount="{{ $repayment->amount_paid ?? 0 }}"
                          data-method="{{ $repayment->payment_method ?? 'Cash' }}"
                          data-reference="{{ $repayment->reference_no ?? '' }}"
                          data-date="{{ $repayment->payment_date ? \Carbon\Carbon::parse($repayment->payment_date)->format('Y-m-d') : '' }}"
                          data-notes="{{ $repayment->notes ?? '' }}">Edit</button>
                  <button class="btn btn-sm btn-outline-danger"
                          style="border: 1px solid #cbd5e1; background-color: #ffffff; color: #1e293b; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; cursor: pointer;"
                          onclick="deleteRepayment({{ $repayment->id }}, '{{ $repayment->loanApplication?->user?->name ?? 'Unknown' }}')">
                    Delete
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
  <div class="modal"
       id="modal-recordRepaymentModal"
       onclick="event.stopPropagation()"
       style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
      <div class="modal-title">💰 Record New Repayment</div>
      <div class="modal-close" type="button" onclick="closeRepaymentModal()">✕</div>
    </div>
    <form action="{{ route('repayments.store') }}" method="POST">
      @csrf

      <input type="hidden" name="application_id" id="repayAppId" value="">
      <input type="hidden" id="repaymentId" name="repayment_id" value="">

      <div class="form-group">
        <label class="form-label">Select Loan</label>
        <select name="loan_select" id="loanSelect" class="form-select" onchange="populateLoanFields(this)">
          <option value="">-- Select a loan --</option>
          @foreach($approvedLoans as $loan)
            @php
              $borrowerName = $loan->loanApplication?->user?->name ?? $loan->loanApplication?->full_name ?? $loan->applicant ?? 'Unknown';
              $appId = $loan->app_id ?? $loan->id;
              $remainingBalance = $loan->remaining_balance ?? $loan->loanApplication?->remaining_balance ?? 0;
              $loanType = $loan->loan_type ?? $loan->loanApplication?->loan_type ?? 'N/A';
            @endphp
            <option value="{{ $loan->id }}"
                    data-app-id="{{ $appId }}"
                    data-borrower="{{ $borrowerName }}"
                    data-amount="{{ $remainingBalance }}"
                    data-loan-type="{{ $loanType }}">
              #{{ $appId }} — {{ $borrowerName }} (₱{{ number_format($remainingBalance, 2) }} remaining)
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Application ID</label>
          <input type="text" name="app_id" id="repayAppIdInput" class="form-input" placeholder="#APP-0001" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Borrower Name</label>
          <input type="text" name="borrower_name" id="borrower_name" class="form-input" placeholder="Full name" required/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Amount Paid (₱)</label>
          <input type="number" step="0.01" name="amount" class="form-input" placeholder="0.00" required/>
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
          <input type="text" name="reference_no" class="form-input" placeholder="Optional reference"/>
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
          onclick="closeRepaymentModal()">Cancel</button>
      </div>
    </form>
  </div>

</div>

<!-- GLOBAL OVERLAY FOR MODALS -->
<div class="overlay" id="overlay" onclick="closeRepaymentModal()"></div>

<script>
  // ─── Self-contained modal open/close (footer.blade.php is not loaded on this standalone page) ───
  function openRepaymentModal() {
    const modal = document.getElementById('modal-recordRepaymentModal');
    if (!modal) return;
    modal.classList.add('open');
  }

  function closeRepaymentModal() {
    const modal = document.getElementById('modal-recordRepaymentModal');
    if (modal) modal.classList.remove('open');
  }

  // ─── Record a NEW repayment (clear form, POST to store) ───
  function recordNewRepayment() {
    const modal = document.getElementById('modal-recordRepaymentModal');
    const title = modal.querySelector('.modal-title');
    const form = modal.querySelector('form');
    const appIdInput = document.getElementById('repayAppIdInput');
    const borrowerInput = modal.querySelector('input[name="borrower_name"]');
    const amountInput = modal.querySelector('input[name="amount"]');
    const select = document.getElementById('loanSelect');
    const hiddenAppId = document.getElementById('repayAppId');
    const repaymentIdInput = document.getElementById('repaymentId');

    if (!modal || !title || !form) return;

    title.textContent = '💰 Record New Repayment';
    form.action = '{{ route('repayments.store') }}';
    form.method = 'POST';
    if (hiddenAppId) hiddenAppId.value = '';
    if (borrowerInput) borrowerInput.value = '';
    if (amountInput) amountInput.value = '';
    if (select) select.selectedIndex = 0;
    if (repaymentIdInput) repaymentIdInput.value = '';

    // Reset payment method / date / reference / notes if present
    const methodSelect = modal.querySelector('select[name="payment_method"]');
    if (methodSelect) methodSelect.selectedIndex = 0;
    const refInput = modal.querySelector('input[name="reference_no"]');
    if (refInput) refInput.value = '';
    const dateInput = modal.querySelector('input[name="payment_date"]');
    if (dateInput) dateInput.value = '';
    const notesInput = modal.querySelector('textarea[name="notes"], input[name="notes"]');
    if (notesInput) notesInput.value = '';

    openRepaymentModal();
  }

  // ─── Edit an EXISTING repayment (prefill form, PUT to update) ───
  function editRepayment(id) {
    const modal = document.getElementById('modal-recordRepaymentModal');
    const title = modal.querySelector('.modal-title');
    const form = modal.querySelector('form');
    const btn = document.querySelector(`button[onclick="editRepayment(${id})"]`);
    const amountInput = modal.querySelector('input[name="amount"]');
    const methodSelect = modal.querySelector('select[name="payment_method"]');
    const refInput = modal.querySelector('input[name="reference_no"]');
    const dateInput = modal.querySelector('input[name="payment_date"]');
    const notesInput = modal.querySelector('textarea[name="notes"], input[name="notes"]');
    const repaymentIdInput = document.getElementById('repaymentId');

    if (!modal || !title || !form || !btn) return;

    title.textContent = '✏️ Edit Repayment';
    form.action = '/repayments/' + id;
    form.method = 'POST';

    // Laravel method spoofing for PUT
    let methodInput = modal.querySelector('input[name="_method"]');
    if (!methodInput) {
      methodInput = document.createElement('input');
      methodInput.type = 'hidden';
      methodInput.name = '_method';
      form.appendChild(methodInput);
    }
    methodInput.value = 'PUT';

    if (repaymentIdInput) repaymentIdInput.value = id;

    // Prefill from data attributes
    if (amountInput) amountInput.value = btn.dataset.amount ?? '';
    if (methodSelect) {
      for (let i = 0; i < methodSelect.options.length; i++) {
        if (methodSelect.options[i].value === (btn.dataset.method ?? '')) {
          methodSelect.selectedIndex = i;
          break;
        }
      }
    }
    if (refInput) refInput.value = btn.dataset.reference ?? '';
    if (dateInput) dateInput.value = btn.dataset.date ?? '';
    if (notesInput) notesInput.value = btn.dataset.notes ?? '';

    openRepaymentModal();
  }

  // ─── View repayment details ───
  function viewRepayment(id) {
    const btn = document.querySelector(`button[onclick="editRepayment(${id})"]`);
    const amount = btn?.dataset.amount ?? 'N/A';
    const method = btn?.dataset.method ?? 'N/A';
    const reference = btn?.dataset.reference ?? 'N/A';
    const date = btn?.dataset.date ?? 'N/A';
    const notes = btn?.dataset.notes ?? '—';

    const msg = `Repayment #${id}\n` +
                `Amount Paid: ₱${parseFloat(amount || 0).toFixed(2)}\n` +
                `Payment Method: ${method}\n` +
                `Reference Number: ${reference}\n` +
                `Payment Date: ${date}\n` +
                `Notes: ${notes}`;
    alert(msg);
  }

  // ─── Delete an existing repayment record ───
  function deleteRepayment(id, borrowerName) {
    if (!confirm(`Delete repayment for "${borrowerName}"? This cannot be undone.`)) return;

    const token = document.querySelector('meta[name="csrf-token"]');
    const headers = {
      'Accept': 'application/json',
    };
    if (token) headers['X-CSRF-TOKEN'] = token.content;

    fetch('/repayments/' + id, {
      method: 'DELETE',
      headers: headers,
    })
    .then(response => {
      if (response.ok) location.reload();
      else alert('Failed to delete repayment.');
    })
    .catch(() => alert('Failed to delete repayment.'));
  }

  function closeRecordRepaymentModal() {
    closeRepaymentModal();
  }

  // ─── Populate form fields when a loan is selected from the dropdown ───
  function populateLoanFields(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) return;

    const appIdInput = document.getElementById('repayAppIdInput');
    const borrowerInput = document.getElementById('borrower_name');
    const hiddenAppId = document.getElementById('repayAppId');

    if (appIdInput) appIdInput.value = opt.dataset.appId || '';
    if (borrowerInput) borrowerInput.value = opt.dataset.borrower || '';
    if (hiddenAppId) hiddenAppId.value = opt.value;
  }
</script>

</body>
</html>