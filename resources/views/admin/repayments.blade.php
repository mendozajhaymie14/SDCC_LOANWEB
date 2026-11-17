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
<style>
  /* ===== Repayments page scoped styles (.rep-*) ===== */
  .rep-filter-row {
    display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;
  }
  .rep-filter-group {
    display: flex; flex-direction: column; gap: 0.4rem; min-width: 140px; flex: 1;
  }
  .rep-filter-label {
    font-size: 0.7rem; font-weight: 600; color: var(--muted2);
    text-transform: uppercase; letter-spacing: 0.06em;
  }
  .rep-filter-control {
    width: 100%; background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 0.65rem 1rem; color: var(--text);
    font-size: 0.88rem; font-family: 'DM Sans', sans-serif;
  }
  .rep-filter-actions {
    display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.25rem;
  }
  .rep-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    font-size: 0.85rem; font-weight: 600; font-family: 'DM Sans', sans-serif;
    border: 1px solid var(--border); border-radius: 8px; padding: 0.55rem 1rem;
    cursor: pointer; background: var(--surface); color: var(--text);
  }
  .rep-btn:hover { border-color: var(--accent); color: var(--accent); }
  .rep-btn-danger { border-color: var(--red); color: var(--red); }
  .rep-btn-danger:hover { background: var(--red); color: #fff; }

  /* Confirmation panel (Task 1) */
  .rep-confirm-panel {
    border: 2px solid var(--accent);
    background: #fff7e6;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    display: none;
  }
  .rep-confirm-panel[hidden] { display: none !important; }
  .rep-confirm-text {
    font-size: 0.9rem;
    line-height: 1.5;
    color: var(--text);
    margin-bottom: 1rem;
  }
  .rep-confirm-actions {
    display: flex; gap: 0.5rem; justify-content: flex-end;
  }

  /* Read-only field styling (Task 2) */
  .rep-readonly {
    background: #f1f5f9 !important;
    color: #64748b !important;
    border-color: #cbd5e1 !important;
  }

  /* Reversal info block (Task 2) */
  .rep-reversal-info {
    border: 1px solid #fee2e2;
    background: #fef2f2;
    border-radius: 6px;
    padding: 0.75rem 1rem;
  }
  .rep-reversal-info.rep-hidden { display: none; }

  /* Row reversal styling */
  .row-reversed td { opacity: 0.6; }
  .status-pill.reversed { background: #fef2f2; color: #ef4444; border-color: #fee2e2; }
</style>
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
        <i class="fa-solid fa-bell nav-icon"></i><span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings"><i class="fa-solid fa-gear nav-icon"></i></div>
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
        <button class="btn-new" onclick="recordNewRepayment()">+ Record Payment</button>
      </div>

      <!-- Summary Panel -->
      <div class="panel" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
          <div>
            <div class="records-count" style="font-weight:700;">Total Repayments Collected</div>
            <div class="kpi-val" style="font-size: 2rem;">{{ \App\Support\Format::money($totalRepayments) }}</div>
          </div>
          <div class="kpi-card" style="min-width: 160px;">
            <div class="records-count">Collected this month</div>
            <div class="kpi-val" style="font-size: 1.2rem;">{{ \App\Support\Format::money($monthlyCollected ?? 0) }}</div>
          </div>
        </div>
      </div>

      <!-- Tabs: Payments / Due &amp; Overdue -->
      <div class="rep-tab-bar">
        <button type="button" class="rep-tab active" data-tab="payments">Payments</button>
        <button type="button" class="rep-tab" data-tab="due">Due &amp; Overdue
          <span class="rep-tab-count">{{ $dueLoans->count() }}</span>
        </button>
      </div>

      <!-- ===== TAB: PAYMENTS ===== -->
      <div id="tab-payments" class="rep-tab-pane">
      <!-- Search & Filters -->
      <div class="panel" style="margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('repayments.index') }}" class="rep-filter-row">
          <div class="rep-filter-group">
            <label class="rep-filter-label">Search</label>
            <input type="text" name="search" class="rep-filter-control"
                   placeholder="Name, member ID, REP ref, or APP id"
                   value="{{ request('search') }}"/>
          </div>
          <div class="rep-filter-group">
            <label class="rep-filter-label">Start Date</label>
            <input type="date" name="start_date" class="rep-filter-control" value="{{ request('start_date') }}"/>
          </div>
          <div class="rep-filter-group">
            <label class="rep-filter-label">End Date</label>
            <input type="date" name="end_date" class="rep-filter-control" value="{{ request('end_date') }}"/>
          </div>
          <div class="rep-filter-actions">
            <button type="submit" class="rep-btn">Filter</button>
            <a href="{{ route('repayments.index') }}" class="rep-btn rep-btn-danger">Reset</a>
          </div>
        </form>
      </div>

      <!-- Repayments Data Table -->
      <div class="table-wrap">
        <div class="rep-list-toolbar">
          <span class="records-count">{{ $repayments->count() }} record(s)</span>
          <form method="GET" action="{{ route('repayments.index') }}" class="rep-show-reversed">
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            @if(request('start_date'))<input type="hidden" name="start_date" value="{{ request('start_date') }}">@endif
            @if(request('end_date'))<input type="hidden" name="end_date" value="{{ request('end_date') }}">@endif
            <label>
              <input type="checkbox" name="show_reversed" value="1" {{ $showReversed ? 'checked' : '' }}> Show reversed
            </label>
            <button type="submit" class="rep-btn">Apply</button>
          </form>
        </div>
        <table class="app-table" id="repaymentsTable">
          <thead>
            <tr>
              <th>Member ID</th>
              <th>NAME</th>
              <th>Loan Application ID</th>
              <th>Amount Paid</th>
              <th>Payment Method</th>
              <th>Date Paid</th>
              <th>Balance After Payment</th>
              <th>Loan Status</th>
              <th>Recorded By</th>
              <th>Action</th>
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
                $memberId = $repayment->loanApplication?->user?->coopMember?->member_id;
                $standing = $repayment->loanApplication?->repaymentStanding();
                $isReversed = $repayment->isReversed();
                $reversedBy = $repayment->reversedBy?->name ?? '—';
                $reversedAt = $repayment->deleted_at?->format('M d, Y H:i') ?? '—';
                $reversalReason = $repayment->reversal_reason ?? '—';
              @endphp
              <tr @if($isReversed) class="row-reversed"@endif>
                <td class="applicant-name">{{ $memberId ?? '—' }}</td>
                <td>{{ $borrowerName }}</td>
                <td><span class="loan-type-tag">#{{ $appId }}</span></td>
                <td class="num">{{ \App\Support\Format::money($amount) }}</td>
                <td><span class="status-pill {{ strtolower($method) }}">{{ ucfirst($method) }}</span></td>
                <td class="td-date">{{ $paymentDateDisplay }}</td>
                <td class="num">{{ \App\Support\Format::money($balanceAfter) }}</td>
                <td>
                  @if($isReversed)
                    <span class="status-pill reversed">Reversed</span>
                    <div class="rep-standing-balance">by {{ $reversedBy }}</div>
                  @else
                    <span class="status-pill {{ $standing['key'] === 'overdue' ? 'overdue' : ($standing['key'] === 'due_soon' ? 'due-soon' : ($standing['key'] === 'due_today' ? 'review' : 'approved')) }}">{{ $standing['label'] }}</span>
                    <div class="rep-standing-balance">{{ \App\Support\Format::money($loanBalance) }} left</div>
                  @endif
                </td>
                <td>{{ $recordedBy }}</td>
                <td>
                  <button type="button" class="btn-review btn-view-repayment"
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
                          data-recorded-by="{{ $recordedBy }}"
                          data-reversed="{{ $isReversed ? '1' : '' }}"
                          data-reversed-by="{{ $reversedBy }}"
                          data-reversed-at="{{ $reversedAt }}"
                          data-reversal-reason="{{ $reversalReason }}"
                          data-proof-url="{{ $repayment->proof_path ? route('repayments.proof', $repayment->id) : '' }}">
                    <i class="fa-solid fa-eye nav-icon"></i>View
                  </button>
                  @if(!$isReversed)
                  <button type="button" class="btn-review btn-edit-repayment"
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
                          data-recorded-by="{{ $recordedBy }}"
                          data-proof-url="{{ $repayment->proof_path ? route('repayments.proof', $repayment->id) : '' }}">
                    <i class="fa-solid fa-pen nav-icon"></i>Edit
                  </button>
                  @if($canReverse)
                  <button type="button" class="btn-review btn-delete btn-reverse-repayment"
                          data-repayment-id="{{ $repayment->id }}"
                          data-borrower="{{ $borrowerName }}"
                          data-amount="{{ $amount }}"
                          data-ref-label="{{ $refLabel }}">
                    <i class="fa-solid fa-rotate-left nav-icon"></i>Reverse
                  </button>
                  @endif
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="10" class="empty-table-msg">No repayment records found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      </div>
      <!-- ===== /TAB: PAYMENTS ===== -->

      <!-- ===== TAB: DUE & OVERDUE ===== -->
      <div id="tab-due" class="rep-tab-pane rep-hidden">
        <div class="panel">
          <input type="text" id="dueSearch" class="form-input rep-due-search"
                 placeholder="Search by name, member ID or loan ID"/>
          <div class="table-wrap">
            <table class="app-table" id="dueTable">
              <thead>
                <tr>
                  <th>Member ID</th>
                  <th>Name</th>
                  <th>Loan</th>
                  <th>Due Date</th>
                  <th>Status</th>
                  <th>Installment Due</th>
                  <th>Remaining Balance</th>
                  <th>Last Payment</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($dueLoans as $row)
                  @php
                    $st = $row->standing;
                    $pillClass = match ($st['key']) {
                        'overdue' => 'overdue',
                        'due_today' => 'review',
                        'due_soon' => 'due-soon',
                        default => 'approved',
                    };
                    $pillText = match ($st['key']) {
                        'overdue' => $st['days'] . ' day' . ($st['days'] === 1 ? '' : 's') . ' overdue',
                        'due_today' => 'Due today',
                        'due_soon' => 'Due in ' . $st['days'] . ' day' . ($st['days'] === 1 ? '' : 's'),
                        default => 'On track',
                    };
                  @endphp
                  <tr data-search="{{ strtolower($row->resolved_name . '|' . ($row->member_ref ?? '') . '|' . ($row->loan_ref ?? '')) }}">
                    <td class="applicant-name">{{ $row->member_ref ?? '—' }}</td>
                    <td>{{ $row->resolved_name }}</td>
                    <td><span class="loan-type-tag">#{{ $row->loan_ref }}</span></td>
                    <td class="td-date">{{ $row->due_date }}</td>
                    <td><span class="status-pill {{ $pillClass }}">{{ $pillText }}</span></td>
                    <td class="num">{{ \App\Support\Format::money($row->overdue_installment) }}</td>
                    <td class="num">{{ \App\Support\Format::money($row->amount) }}</td>
                    <td class="td-date">{{ $row->last_payment_date }}</td>
                    <td>
                      <button type="button" class="btn-review btn-record-due"
                              data-loan-id="{{ $row->application_id }}"
                              data-installment="{{ $row->overdue_installment }}">
                        <i class="fa-solid fa-money-bill nav-icon"></i>Record payment
                      </button>
                    </td>
                  </tr>
                @empty
                <tr>
                  <td colspan="9" class="empty-table-msg">No loans are due or overdue.</td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- ===== /TAB: DUE & OVERDUE ===== -->
    </div>
  </div>

  <!-- RECORD REPAYMENT MODAL -->
  <div class="modal"
       id="modal-recordRepaymentModal"
       onclick="event.stopPropagation()"
       style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
      <div class="modal-title" id="recordModalTitle"><i class="fa-solid fa-money-bill nav-icon"></i> Record New Repayment</div>
      <div class="modal-close" type="button" onclick="closeRepaymentModal()"><i class="fa-solid fa-xmark nav-icon"></i></div>
    </div>
    <form id="recordRepaymentForm" action="{{ route('repayments.store') }}" method="POST" enctype="multipart/form-data" novalidate>
      @csrf

      <input type="hidden" name="application_id" id="repayAppId" value="">

      <div style="margin-top: 1rem;">
        <label class="form-label">Select Loan <span class="req-mark">*</span></label>
        <select name="loan_select" id="loanSelect" class="form-select" onchange="populateLoanFields(this)">
          <option value="">-- Select a loan --</option>
          @foreach($approvedLoans as $loan)
            @php
              $borrowerName = $loan->loanApplication?->fullName() ?? $loan->applicant ?? 'Unknown';
              $appId = $loan->app_id ?? $loan->id;
              $remainingBalance = (float)($loan->remaining_balance ?? 0);
              $memberId = $loan->loanApplication?->user?->coopMember?->member_id;
              $nextDue = $loan->loanApplication?->next_due_date?->format('Y-m-d') ?? '';
              $monthlyPayment = (float)($loan->loanApplication?->monthly_payment ?? 0);
            @endphp
            <option value="{{ $loan->id }}"
                    data-app-id="{{ $appId }}"
                    data-borrower="{{ $borrowerName }}"
                    data-balance="{{ $remainingBalance }}"
                    data-member-id="{{ $memberId }}"
                    data-next-due="{{ $nextDue }}"
                    data-monthly-payment="{{ $monthlyPayment }}">
              #{{ $appId }} — {{ $borrowerName }} ({{\App\Support\Format::money($remainingBalance)}} remaining)
            </option>
          @endforeach
        </select>
      </div>

      <!-- Read-only summary card (populated after loan is picked) -->
      <div id="loanSummaryCard" style="margin-top:1rem;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;display:none;">
        <div style="font-size:0.8rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Loan Summary</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Application ID</label><div id="sumAppId" class="num" style="font-weight:600;color:#1e293b;">—</div></div>
          <div class="form-group"><label class="form-label">Name</label><div id="sumName" style="font-weight:600;color:#1e293b;">—</div></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Member ID</label><div id="sumMemberId" class="num" style="font-weight:600;color:#1e293b;">—</div></div>
          <div class="form-group"><label class="form-label">Outstanding balance</label><div id="sumBalance" class="num" style="font-weight:600;color:#1e293b;">—</div></div>
        </div>
        <div class="form-group"><label class="form-label">Next due date</label><div id="sumNextDue" style="font-weight:600;color:#1e293b;">—</div></div>
      </div>

      <!-- Quick pay buttons -->
      <div class="form-row" style="margin-top:1rem;gap:0.75rem;">
        <button type="button" class="rep-btn" onclick="quickPay('installment')">Pay monthly installment</button>
        <button type="button" class="rep-btn" onclick="quickPay('full')">Pay full balance</button>
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
          <label class="form-label">Amount Paid (₱) <span class="req-mark">*</span></label>
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
          <label class="form-label">Payment Date <span class="req-mark">*</span></label>
          <input type="date" name="payment_date" id="repayDate" class="form-input" required/>
        </div>
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label class="form-label" for="repayProof">Proof of Payment <span class="req-mark">*</span></label>
        <input type="file" name="proof" id="repayProof" class="form-input" accept="image/jpeg,image/png,image/webp" required
               onchange="previewProof(this, 'proofPreview', 'proofPreviewWrap', 'proofError')"/>
        <small style="font-size:0.75rem;color:var(--muted);">JPG, JPEG, PNG or WEBP only, max 5 MB.</small>
        <div id="proofPreviewWrap" style="margin-top:0.5rem;display:none;">
          <img id="proofPreview" class="proof-thumb" alt="Proof of payment preview"/>
          <button type="button" class="btn-cancel" id="removeProofBtn" style="margin-top:0.5rem;" onclick="removeProof('repayProof', 'proofPreviewWrap', 'proofError')">Remove</button>
        </div>
        <small id="proofError" style="color:#ef4444;font-size:0.75rem;display:none;"></small>
      </div>

      <div class="form-group" style="margin-top: 1rem;">
        <label class="form-label" for="repayNotes">Notes</label>
        <textarea name="notes" id="repayNotes" class="form-input" rows="3" placeholder="Optional notes"></textarea>
      </div>

      <!-- Info strip: Current outstanding balance -->
      <div class="form-group" style="margin-top: 1rem; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
        <div style="font-size:0.85rem;color:#64748b;">Current outstanding balance</div>
        <div style="font-weight:700;color:#1e293b;font-size:1.1rem;"><span id="currentBalanceDisplay">—</span></div>
      </div>

      <!-- Info strip: Remaining balance after this payment -->
      <div class="form-group" style="margin-top: 0.5rem; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
        <div style="font-size:0.85rem;color:#64748b;">Remaining balance after this payment</div>
        <div style="font-weight:700;color:#1e293b;font-size:1.1rem;"><span id="balancePreview">—</span></div>
        <small id="balanceError" style="color:#ef4444;display:none;">Amount exceeds current balance</small>
      </div>

      <!-- Server error display (inline) -->
      <div id="recordError" style="color:#ef4444;font-size:0.875rem;margin-top:0.75rem;display:none;"></div>

      <!-- Inline confirmation panel (Task 1 — shown before actual submit) -->
      <div id="recordConfirmPanel" class="rep-confirm-panel" hidden>
        <div class="rep-confirm-text" id="recordConfirmText"></div>
        <div class="rep-confirm-actions">
          <button type="button" class="btn-submit" onclick="confirmAndRecord()">Confirm and record</button>
          <button type="button" class="btn-cancel" onclick="hideRecordConfirmation()">Back</button>
        </div>
      </div>

      <div class="modal-footer">
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
      <div class="modal-title"><i class="fa-solid fa-pen nav-icon"></i> Edit Payment</div>
      <div class="modal-close" type="button" onclick="closeEditRepaymentModal()"><i class="fa-solid fa-xmark nav-icon"></i></div>
    </div>
    <div id="editLoanSummary" class="loan-summary" style="padding: 1.5rem;">
      <!-- Loan summary block will be populated by JS -->
    </div>
    <form id="editRepaymentForm" action="" method="POST" enctype="multipart/form-data" novalidate>
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" name="repayment_id" id="editRepaymentId" value="">

      <div class="form-group" style="padding: 0 1.5rem;">
        <label class="form-label">Amount Paid (₱) <span class="req-mark">*</span></label>
        <input type="text" name="amount" id="editAmount" class="form-input num" placeholder="0.00" required/>
        <small style="font-size:0.75rem;color:var(--muted);">Plain number, e.g. 10000.00</small>
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
          <label class="form-label">Payment Date <span class="req-mark">*</span></label>
          <input type="date" name="payment_date" id="editDate" class="form-input" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <input type="text" name="notes" id="editNotes" class="form-input" placeholder="Optional notes"/>
        </div>
      </div>

      <div class="form-group" style="padding: 0 1.5rem;">
        <label class="form-label">Reason for change</label>
        <input type="text" name="reason" id="editReason" class="form-input" placeholder="Optional — saved to audit log"/>
      </div>

      <div class="form-group" style="padding: 0 1.5rem; margin-top: 1rem;">
        <label class="form-label" for="editProof">Proof of Payment</label>
        <div id="editProofCurrent" style="margin-bottom:0.5rem;font-weight:600;color:#1e293b;">—</div>
        <input type="file" name="proof" id="editProof" class="form-input" accept="image/jpeg,image/png,image/webp"
               onchange="previewProof(this, 'editProofPreview', 'editProofPreviewWrap', 'editProofError')"/>
        <small style="font-size:0.75rem;color:var(--muted);">Leave empty to keep the current proof.</small>
        <div id="editProofPreviewWrap" style="margin-top:0.5rem;display:none;">
          <img id="editProofPreview" class="proof-thumb" alt="Proof of payment preview"/>
          <button type="button" class="btn-cancel" id="removeEditProofBtn" style="margin-top:0.5rem;" onclick="removeProof('editProof', 'editProofPreviewWrap', 'editProofError')">Remove</button>
        </div>
        <small id="editProofError" style="color:#ef4444;font-size:0.75rem;display:none;"></small>
      </div>

      <div style="padding: 0 1.5rem;">
        <label class="form-label">Recorded By</label>
        <div id="editRecordedBy" style="font-weight:600;color:#1e293b;">—</div>
      </div>

      <div class="form-group" style="margin-top: 1rem; padding: 0 1.5rem;">
        <div style="font-size:0.85rem;color:#64748b;">Balance after this change</div>
        <div style="font-weight:700;color:#1e293b;font-size:1.1rem;"><span id="editBalancePreview">0.00</span></div>
        <small style="color:#64748b;">calculated as: balance - new amount + old amount</small>
        <small style="color:var(--gold);display:block;">Editing changes the loan balance.</small>
      </div>

      <!-- Server error display (inline) -->
      <div id="editError" style="color:#ef4444;font-size:0.875rem;margin:0.75rem 1.5rem 0;display:none;"></div>

      <div class="modal-footer">
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
      <div class="modal-title" id="viewModalTitle"><i class="fa-solid fa-eye nav-icon"></i> Payment Details</div>
      <div class="modal-close" type="button" onclick="closeViewRepaymentModal()"><i class="fa-solid fa-xmark nav-icon"></i></div>
    </div>
    <div style="padding: 1.5rem;">
      <!-- Header: ref display with "Payment REP-" prefix -->
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
        <div style="background:#f1f5f9;border:1px solid #cbd5e1;border-radius:6px;padding:0.4rem 0.8rem;color:#1e293b;font-weight:700;font-size:1rem;">
          <span id="viewRepaymentTitle">Payment REP-</span>
        </div>
      </div>

      <!-- Big amount at top -->
      <div style="margin-bottom:1rem;">
        <div id="viewRepaymentAmountBig" style="font-family:'DM Sans',sans-serif;font-weight:800;font-size:2rem;color:var(--text);">₱0.00</div>
      </div>

      <!-- Loan status after this payment -->
      <div style="margin-bottom:1rem;">
        <span id="viewLoanStatusAfter" class="status-pill approved">—</span>
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
          <div id="viewRepaymentAmount" style="font-weight:600;color:#1e293b;">0.00</div>
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
          <label class="form-label">Payment Date <span class="req-mark">*</span></label>
          <div id="viewRepaymentDate" style="font-weight:600;color:#1e293b;">—</div>
        </div>
      </div>

      <div class="form-row" style="margin-bottom:0.75rem;">
        <div class="form-group" style="flex:1;">
          <label class="form-label">Balance After</label>
          <div id="viewRepaymentBalance" style="font-weight:600;color:#1e293b;">0.00</div>
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

      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label">Proof of Payment</label>
        <div id="viewRepaymentProof">—</div>
        <div id="viewProofDownload" style="margin-top:0.5rem;display:none;">
          <a id="viewProofDownloadLink" href="#" target="_blank" class="nl-loan-link">Download proof</a>
        </div>
      </div>

      <!-- Reversal info block (shown only when payment was reversed) -->
      <div id="viewReversalBlock" class="rep-reversal-info rep-hidden" style="margin-bottom:1rem;">
        <div style="font-size:0.8rem;color:#ef4444;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;">Reversed Payment</div>
        <div class="form-row" style="margin-bottom:0.5rem;">
          <div class="form-group" style="flex:1;"><label class="form-label">Reversed By</label><div id="viewReversedBy" style="font-weight:600;color:#1e293b;">—</div></div>
          <div class="form-group" style="flex:1;"><label class="form-label">Reversed At</label><div id="viewReversedAt" style="font-weight:600;color:#1e293b;">—</div></div>
        </div>
        <div class="form-group" style="margin-bottom:0.5rem;">
          <label class="form-label">Reason</label>
          <div id="viewReversalReason" style="font-weight:600;color:#1e293b;">—</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-submit" id="viewEditBtn" onclick="editFromView()">Edit</button>
        <button type="button" class="btn-review btn-delete btn-submit" id="viewReverseBtn" onclick="openReverseFromView()">Reverse</button>
        <button type="button" class="btn-cancel" onclick="closeViewRepaymentModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- REVERSE PAYMENT MODAL -->
  <div class="modal"
       id="modal-reverseRepayment"
       onclick="event.stopPropagation()"
       style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
      <div class="modal-title"><i class="fa-solid fa-rotate-left nav-icon" style="color:#ef4444;"></i> Reverse Payment</div>
      <div class="modal-close" type="button" onclick="closeReverseRepaymentModal()"><i class="fa-solid fa-xmark nav-icon"></i></div>
    </div>
    <form id="reverseRepaymentForm" method="POST" novalidate>
      @csrf
      <input type="hidden" name="_method" value="POST">
      <div style="padding: 1.5rem;">
        <div style="font-size:0.8rem;color:#ef4444;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;">Reversal Reason <span class="req-mark">*</span></div>
        <textarea name="reason" id="reverseReason" class="form-input" rows="4" placeholder="Why is this payment being reversed? (min 10 chars)" required></textarea>
        <div id="reverseWarn" style="margin-top:0.5rem;color:#ef4444;font-size:0.75rem;display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeReverseRepaymentModal()">Cancel</button>
        <button type="button" class="btn-submit btn-review btn-delete" id="reverseSubmitBtn" onclick="submitReverseRepayment()">Reverse</button>
      </div>
    </form>
  </div>

  <script>
    // ==================================================================
    // SHARED HELPERS
    // ==================================================================
    function rowData(btn) {
      const d = btn.dataset;
      return {
        repaymentId: d.repaymentId || '',
        refLabel: d.refLabel || '',
        appId: d.appId || '',
        borrower: d.borrower || '',
        amount: d.amount || '0',
        method: d.method || '',
        reference: d.reference || '',
        date: d.date || '',
        notes: d.notes || '',
        remainBalance: d.remainBalance || '0',
        loanBalance: d.loanBalance || '0',
        nextDue: d.nextDue || '',
        recordedBy: d.recordedBy || '',
        proofUrl: d.proofUrl || '',
        reversed: d.reversed === '1',
        reversedBy: d.reversedBy || '—',
        reversedAt: d.reversedAt || '—',
        reversalReason: d.reversalReason || '—'
      };
    }

    function formatMoney(n) {
      return n.toLocaleString('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ==================================================================
    // MODAL OPEN/CLOSE HELPERS
    // ==================================================================
    function openRepaymentModal() {
      const modal = document.getElementById('modal-recordRepaymentModal');
      if (modal) modal.classList.add('open');
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
    function closeReverseRepaymentModal() {
      const modal = document.getElementById('modal-reverseRepayment');
      if (modal) modal.classList.remove('open');
    }

    // ==================================================================
    // PROOF-OF-PAYMENT FILE VALIDATION
    // ==================================================================
    function proofFileOk(file) {
      const okTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!okTypes.includes(file.type)) {
        return 'Only JPG, JPEG, PNG or WEBP images are allowed.';
      }
      if (file.size > 5 * 1024 * 1024) {
        return 'The proof of payment must be 5 MB or smaller.';
      }
      return null;
    }

    function previewProof(input, imgId, wrapId, errorId) {
      const img = document.getElementById(imgId);
      const wrap = document.getElementById(wrapId);
      const errorEl = document.getElementById(errorId);
      if (!input || !img || !wrap || !errorEl) return;
      errorEl.style.display = 'none';
      if (!input.files || !input.files.length) { wrap.style.display = 'none'; return; }
      const problem = proofFileOk(input.files[0]);
      if (problem) {
        input.value = '';
        wrap.style.display = 'none';
        errorEl.textContent = problem;
        errorEl.style.display = 'block';
        return;
      }
      const reader = new FileReader();
      reader.onload = function (e) { img.src = e.target.result; wrap.style.display = 'block'; };
      reader.readAsDataURL(input.files[0]);
    }

    function removeProof(inputId, wrapId, errorId) {
      const input = document.getElementById(inputId);
      const wrap = document.getElementById(wrapId);
      const errorEl = document.getElementById(errorId);
      if (input) input.value = '';
      if (wrap) wrap.style.display = 'none';
      if (errorEl) errorEl.style.display = 'none';
    }

    // ==================================================================
    // DUE & OVERDUE TAB SWITCHING
    // ==================================================================
    document.querySelectorAll('.rep-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.rep-tab').forEach(function (b) { b.classList.remove('active'); });
        this.classList.add('active');
        document.querySelectorAll('.rep-tab-pane').forEach(function (p) { p.classList.add('rep-hidden'); });
        document.getElementById('tab-' + this.dataset.tab).classList.remove('rep-hidden');
      });
    });

    // Due table search (client-side filter)
    document.getElementById('dueSearch')?.addEventListener('input', function () {
      const q = this.value.toLowerCase();
      document.querySelectorAll('#dueTable tbody tr').forEach(function (tr) {
        tr.style.display = tr.dataset.search.includes(q) ? '' : 'none';
      });
    });

    // ==================================================================
    // RECORD REPAYMENT MODAL: Inline Confirmation Panel (Task 1)
    // ==================================================================
    let repConfirmed = false;
    let currentViewData = {};

    function showRecordConfirmation() {
      const select = document.getElementById('loanSelect');
      const opt = select ? select.options[select.selectedIndex] : null;
      const appId = opt ? (opt.dataset.appId || '') : '';
      const borrower = opt ? (opt.dataset.borrower || '') : '';
      const memberId = opt ? (opt.dataset.memberId || '') : '';
      const balance = opt ? parseFloat(opt.dataset.balance || '0') : 0;
      const dateVal = document.getElementById('repayDate') ? document.getElementById('repayDate').value : '';
      const methodVal = document.getElementById('repayMethod') ? document.getElementById('repayMethod').value : '';

      const amountInput = document.getElementById('repayAmount');
      const amount = parseFloat(amountInput.value) || 0;
      const newBalance = Math.max(0, balance - amount);

      const who = memberId ? borrower + ' (' + memberId + ')' : borrower;
      const dateDisplay = dateVal
        ? new Date(dateVal).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        : '—';

      let text = 'Record ' + formatMoney(amount) + ' for ' + appId + ' (' + who + ')? '
        + 'Method: ' + (methodVal || '—') + '. '
        + 'Date: ' + dateDisplay + '. '
        + 'Balance will go from ' + formatMoney(balance) + ' to ' + formatMoney(newBalance) + '.';

      if (newBalance === 0 && amount > 0) {
        text += ' This pays off the loan; it will be marked Paid.';
      }

      document.getElementById('recordConfirmText').textContent = text;
      document.getElementById('recordConfirmPanel').hidden = false;
      repConfirmed = false;
    }

    function hideRecordConfirmation() {
      const panel = document.getElementById('recordConfirmPanel');
      if (panel) panel.hidden = true;
      repConfirmed = false;
    }

    function confirmAndRecord() {
      repConfirmed = true;
      const panel = document.getElementById('recordConfirmPanel');
      if (panel) panel.hidden = true;
      const form = document.getElementById('recordRepaymentForm');
      if (form) form.requestSubmit();
    }

    // Record a NEW repayment
    function recordNewRepayment() {
      const modal   = document.getElementById('modal-recordRepaymentModal');
      const title   = document.getElementById('recordModalTitle');
      const form    = document.getElementById('recordRepaymentForm');

      if (!modal || !title || !form) return;

      title.textContent = 'Record New Repayment';
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

      // Reset proof upload state
      removeProof('repayProof', 'proofPreviewWrap', 'proofError');

      // Reset loan balance data attribute
      if (amount) amount.dataset.loanBalance = '';

      // Reset confirmation state
      repConfirmed = false;
      const confirmPanel = document.getElementById('recordConfirmPanel');
      if (confirmPanel) confirmPanel.hidden = true;

      // Reset date constraints and balance preview
      const today = new Date().toISOString().split('T')[0];
      date.max = today;
      date.value = today;

      const preview = document.getElementById('balancePreview');
      if (preview) preview.textContent = '—';
      const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
      if (currentBalanceDisplay) currentBalanceDisplay.textContent = '—';
      const errorEl = document.getElementById('balanceError');
      if (errorEl) errorEl.style.display = 'none';

      openRepaymentModal();
    }

    // Populate loan fields when loan selected (Record modal only)
    function populateLoanFields(select) {
      const opt = select.options[select.selectedIndex];
      const summary = document.getElementById('loanSummaryCard');
      if (!opt || !opt.value) {
        if (summary) summary.style.display = 'none';
        return;
      }

      const appIdInput = document.getElementById('repayAppIdInput');
      const borrowerInput = document.getElementById('borrower_name');
      const hiddenAppId = document.getElementById('repayAppId');

      if (appIdInput) appIdInput.value = opt.dataset.appId || '';
      if (borrowerInput) borrowerInput.value = opt.dataset.borrower || '';
      if (hiddenAppId) hiddenAppId.value = opt.value;

      // Store current balance from data-balance attribute
      const balance = parseFloat(opt.dataset.balance || 0);
      const amountInput = document.getElementById('repayAmount');
      if (amountInput) amountInput.dataset.loanBalance = balance;

      // Show current outstanding balance
      const currentBalanceDisplay = document.getElementById('currentBalanceDisplay');
      if (currentBalanceDisplay) {
        currentBalanceDisplay.textContent = formatMoney(balance);
      }

      // Populate summary card
      if (summary) {
        document.getElementById('sumAppId').textContent = '#' + (opt.dataset.appId || '—');
        document.getElementById('sumName').textContent = opt.dataset.borrower || '—';
        document.getElementById('sumMemberId').textContent = opt.dataset.memberId || '—';
        document.getElementById('sumBalance').textContent = formatMoney(balance);
        document.getElementById('sumNextDue').textContent = opt.dataset.nextDue
            ? new Date(opt.dataset.nextDue).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
            : '—';
        summary.style.display = 'block';
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

    // Quick pay: fill amount with monthly installment or full balance
    function quickPay(kind) {
      const select = document.getElementById('loanSelect');
      const opt = select.options[select.selectedIndex];
      if (!opt || !opt.value) return;
      const amount = document.getElementById('repayAmount');
      if (!amount) return;
      amount.value = kind === 'full'
          ? parseFloat(opt.dataset.balance || 0).toFixed(2)
          : parseFloat(opt.dataset.monthlyPayment || 0).toFixed(2);
      amount.dispatchEvent(new Event('input'));
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
      preview.textContent = formatMoney(remaining);

      // Block amount above balance
      if (amount > loanBalance && amount > 0) {
        if (errorEl) errorEl.style.display = 'block';
      } else {
        if (errorEl) errorEl.style.display = 'none';
      }
    }

    // ==================================================================
    // VIEW MODAL: reversal info + reverse button
    // ==================================================================
    function viewRepayment(data) {
      const ref = data.refLabel || '';
      const app = data.appId || '';
      const borrower = data.borrower || '—';
      const amount = parseFloat(data.amount || 0);
      const method = data.method || '—';
      const reference = data.reference || '—';
      const date = data.date || '';
      const notes = data.notes || '—';
      const balance = parseFloat(data.remainBalance || 0);
      const loanBalance = parseFloat(data.loanBalance || 0);
      const recorded = data.recordedBy || '—';
      const nextDue = data.nextDue || '';
      const repaymentId = data.repaymentId || '';
      const proofUrl = data.proofUrl || '';
      const reversed = data.reversed;
      const reversedBy = data.reversedBy || '—';
      const reversedAt = data.reversedAt || '—';
      const reversalReason = data.reversalReason || '—';

      // Extract repayment number for title
      const repNum = ref.startsWith('#') ? ref.substring(1) : ref || '—';

      document.getElementById('viewRepaymentTitle').textContent = 'Payment ' + repNum;
      document.getElementById('viewRepaymentAppId').textContent = '#' + app;
      document.getElementById('viewRepaymentBorrower').textContent = borrower;
      document.getElementById('viewRepaymentAmount').textContent = formatMoney(amount);
      document.getElementById('viewRepaymentMethod').textContent = method;
      document.getElementById('viewRepaymentReference').textContent = reference;
      document.getElementById('viewRepaymentDate').textContent = date ? new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
      document.getElementById('viewRepaymentBalance').textContent = formatMoney(balance);
      document.getElementById('viewRepaymentRecorded').textContent = recorded;
      document.getElementById('viewRepaymentNotes').textContent = notes;

      // Big amount at top
      const amountBig = document.getElementById('viewRepaymentAmountBig');
      if (amountBig) amountBig.textContent = formatMoney(amount);

      // Loan status after this payment
      const statusEl = document.getElementById('viewLoanStatusAfter');
      if (statusEl) {
        if (balance <= 0) {
          statusEl.textContent = 'Loan fully paid';
          statusEl.className = 'status-pill approved';
        } else {
          statusEl.textContent = formatMoney(balance) + ' left';
          statusEl.className = 'status-pill pending';
        }
      }

      // Reversal info block
      const revBlock = document.getElementById('viewReversalBlock');
      if (revBlock) {
        if (reversed) {
          revBlock.classList.remove('rep-hidden');
          document.getElementById('viewReversedBy').textContent = reversedBy || '—';
          const atEl = document.getElementById('viewReversedAt');
          if (atEl) {
            const d = new Date(reversedAt);
            atEl.textContent = isNaN(d.getTime()) ? reversedAt : d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
          }
          document.getElementById('viewReversalReason').textContent = reversalReason || '—';
        } else {
          revBlock.classList.add('rep-hidden');
        }
      }

      // Show / hide Edit + Reverse buttons based on reversal status
      const viewEditBtn = document.getElementById('viewEditBtn');
      const viewReverseBtn = document.getElementById('viewReverseBtn');
      if (viewEditBtn) {
        if (reversed) {
          viewEditBtn.style.display = 'none';
        } else {
          viewEditBtn.style.display = '';
        }
      }
      if (viewReverseBtn) {
        if (reversed) {
          viewReverseBtn.style.display = 'none';
        } else {
          viewReverseBtn.style.display = '';
        }
      }

      // Proof download link
      const dlWrap = document.getElementById('viewProofDownload');
      const dlLink = document.getElementById('viewProofDownloadLink');
      if (dlWrap && dlLink) {
        if (proofUrl) {
          dlWrap.style.display = 'block';
          dlLink.href = proofUrl;
        } else {
          dlWrap.style.display = 'none';
        }
      }

      // Proof of payment thumbnail
      const viewProof = document.getElementById('viewRepaymentProof');
      if (viewProof) {
        viewProof.innerHTML = proofUrl
          ? '<img class="proof-thumb" src="' + proofUrl + '" alt="Proof of payment" title="Click to view full size" onclick="window.open(\'' + proofUrl + '\', \'_blank\');"/>'
          : 'No proof on file';
      }

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
        recordedBy: recorded,
        proofUrl: proofUrl,
        reversed: !!reversed,
        reversedBy: reversedBy,
        reversedAt: reversedAt,
        reversalReason: reversalReason
      };

      const modal = document.getElementById('modal-viewRepaymentModal');
      if (modal) modal.classList.add('open');
    }

    function editFromView() {
      closeViewRepaymentModal();
      if (currentViewData) {
        editRepayment(currentViewData);
      }
    }

    // ==================================================================
    // REVERSE PAYMENT (Task 2)
    // ==================================================================
    function openReverseFromView() {
      const data = currentViewData || {};
      if (!data.repaymentId) return;

      const modal = document.getElementById('modal-reverseRepayment');
      if (!modal) return;
      modal.classList.add('open');

      const reasonInput = document.getElementById('reverseReason');
      if (reasonInput) {
        reasonInput.value = '';
        reasonInput.style.borderColor = '';
      }

      const warnEl = document.getElementById('reverseWarn');
      if (warnEl) {
        warnEl.style.display = 'block';
        warnEl.textContent = 'This cannot be undone. Enter a reason (min 10 chars).';
      }

      modal.dataset.repaymentId = data.repaymentId;
    }

    function submitReverseRepayment() {
      const modal = document.getElementById('modal-reverseRepayment');
      const reasonInput = document.getElementById('reverseReason');
      if (!modal || !reasonInput) return;

      const repaymentId = modal.dataset.repaymentId;
      if (!repaymentId) return;

      if (reasonInput.value.length < 10) {
        reasonInput.style.borderColor = '#ef4444';
        return;
      }

      reasonInput.style.borderColor = '';
      const btn = document.getElementById('reverseSubmitBtn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Reversing…';
      }

      fetch('/repayments/' + repaymentId + '/reverse', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ reason: reasonInput.value })
      })
        .then(function (response) {
          if (!response.ok) {
            return response.json().then(function (json) {
              throw new Error(json.message || 'Reverse failed');
            });
          }
          return response.json();
        })
        .then(function (json) {
          if (json.success) {
            closeReverseRepaymentModal();
            showToast(json.message || 'Payment reversed successfully.', 'success');
            location.reload();
          } else {
            showToast(json.message || 'Reverse failed.', 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Reverse'; }
          }
        })
        .catch(function (err) {
          showToast(err.message || 'Reverse failed.', 'error');
          if (btn) { btn.disabled = false; btn.textContent = 'Reverse'; }
        });
    }

    function showToast(message, type) {
      const container = document.querySelector('.toast-container');
      if (!container) return;
      const toast = document.createElement('div');
      toast.className = 'toast ' + (type || 'info');
      toast.innerHTML = '<div class="toast-msg">' + message + '</div><span class="toast-close" onclick="this.parentElement.parentElement.remove()">×</span>';
      container.appendChild(toast);
      setTimeout(function () { if (toast.parentElement) toast.parentElement.removeChild(toast); }, 5000);
    }

    // ==================================================================
    // EDIT REPAYMENT MODAL
    // ==================================================================
    function editRepayment(data) {
      const modal   = document.getElementById('modal-editRepaymentModal');
      const loanSummary = document.getElementById('editLoanSummary');

      if (!modal || !loanSummary) return;

      loanSummary.innerHTML =
        '<div style="padding:1.5rem;">' +
        '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:1rem;margin-bottom:1rem;">' +
        '<div style="font-size:0.8rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Loan Summary</div>' +
        '<div class="form-row" style="margin-top:0.5rem;">' +
        '<div class="form-group" style="flex:1;">' +
        '<label class="form-label" style="font-size:0.75rem;color:#64748b;">Application ID</label>' +
        '<div style="font-weight:600;color:#1e293b;">#' + (data.appId || '—') + '</div>' +
        '</div>' +
        '<div class="form-group" style="flex:1;">' +
        '<label class="form-label" style="font-size:0.75rem;color:#64748b;">Borrower</label>' +
        '<div style="font-weight:600;color:#1e293b;">' + (data.borrower || '—') + '</div>' +
        '</div>' +
        '</div>' +
        '<div class="form-row" style="margin-top:0.5rem;">' +
        '<div class="form-group" style="flex:1;">' +
        '<label class="form-label" style="font-size:0.75rem;color:#64748b;">Outstanding Balance</label>' +
        '<div style="font-weight:600;color:#1e293b;" id="editLoanOutstandingBalance">0.00</div>' +
        '</div>' +
        '<div class="form-group" style="flex:1;">' +
        '<label class="form-label" style="font-size:0.75rem;color:#64748b;">Next Due Date</label>' +
        '<div style="font-weight:600;color:#1e293b;">' + (data.nextDue ? new Date(data.nextDue).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—') + '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';

      const outstandingBalanceEl = document.getElementById('editLoanOutstandingBalance');
      if (outstandingBalanceEl) {
        outstandingBalanceEl.textContent = formatMoney(data.loanBalance || data.remainBalance || 0);
      }

      const form = document.getElementById('editRepaymentForm');
      const repayId = document.getElementById('editRepaymentId');
      const amount = document.getElementById('editAmount');
      const method = document.getElementById('editMethod');
      const ref = document.getElementById('editReference');
      const date = document.getElementById('editDate');
      const notes = document.getElementById('editNotes');
      const recordedBy = document.getElementById('editRecordedBy');
      const editProofCurrent = document.getElementById('editProofCurrent');
      const editProofInput = document.getElementById('editProof');
      const editProofWrap = document.getElementById('editProofPreviewWrap');
      const editProofError = document.getElementById('editProofError');

      if (!form) return;

      form.action = '/repayments/' + data.repaymentId;

      if (amount) {
        amount.value = data.amount ? parseFloat(data.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        amount.dataset.oldAmount = data.amount || 0;
        amount.dataset.loanBalance = data.loanBalance || '0';
        // Non-privileged users: amount/date are read-only
        amount.readOnly = {{ $canReverse ? 'false' : 'true' }};
      }
      if (method) method.value = data.method || '';
      if (ref) ref.value = data.reference || '';
      if (date) {
        date.value = data.date || '';
        date.readOnly = {{ $canReverse ? 'false' : 'true' }};
      }
      if (notes) notes.value = data.notes || '';
      const reason = document.getElementById('editReason');
      if (reason) reason.value = '';
      if (repayId) repayId.value = data.repaymentId || '';
      if (recordedBy) recordedBy.textContent = data.recordedBy || '—';

      if (editProofCurrent) {
        editProofCurrent.innerHTML = data.proofUrl
          ? '<img class="proof-thumb" src="' + data.proofUrl + '" alt="Current proof of payment"/>'
          : 'No proof on file';
      }
      if (editProofInput) editProofInput.value = '';
      if (editProofWrap) editProofWrap.style.display = 'none';
      if (editProofError) editProofError.style.display = 'none';

      if (date) {
        const today = new Date().toISOString().split('T')[0];
        date.max = today;
      }

      const preview = document.getElementById('editBalancePreview');
      if (preview) {
        preview.dataset.loanBalance = data.loanBalance || '0';
        preview.dataset.balance = data.loanBalance || '0';
        preview.textContent = formatMoney(parseFloat(data.loanBalance || 0));
      }

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

      const newBalance = (loanBalance + oldAmount) - newAmount;
      preview.textContent = formatMoney(newBalance);
      preview.dataset.balance = newBalance.toFixed(2);
    }

    // ==================================================================
    // FORM SUBMIT HANDLERS
    // ==================================================================
    document.getElementById('recordRepaymentForm')?.addEventListener('submit', function (e) {
      if (!repConfirmed) {
        e.preventDefault();
        showRecordConfirmation();
        return;
      }
      const btn = document.getElementById('recordSubmitBtn');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        document.getElementById('recordModalBtnText').textContent = 'Saving…';
      }
    });

    document.getElementById('editRepaymentForm')?.addEventListener('submit', function () {
      const amt = document.getElementById('editAmount');
      if (amt) amt.value = String(amt.value).replace(/,/g, '');
      const btn = document.getElementById('editSubmitBtn');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        document.getElementById('editModalBtnText').textContent = 'Saving…';
      }
    });

    // ==================================================================
    // EVENT LISTENERS
    // ==================================================================
    document.addEventListener('DOMContentLoaded', function () {
      const dateInput = document.getElementById('repayDate');
      if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.max = today;
        dateInput.value = today;
      }

      const amountInput = document.getElementById('repayAmount');
      if (amountInput) {
        amountInput.addEventListener('input', updateRecordBalancePreview);
      }

      const editAmountInput = document.getElementById('editAmount');
      if (editAmountInput) {
        editAmountInput.addEventListener('input', updateEditBalancePreview);
      }

      document.querySelectorAll('.btn-view-repayment').forEach(function (btn) {
        btn.addEventListener('click', function () { viewRepayment(rowData(this)); });
      });

      document.querySelectorAll('.btn-edit-repayment').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const data = rowData(this);
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

      document.querySelectorAll('.btn-reverse-repayment').forEach(function (btn) {
        btn.addEventListener('click', function () {
          currentViewData = {
            repaymentId: this.dataset.repaymentId || '',
            borrower: this.dataset.borrower || '',
            amount: this.dataset.amount || '0',
            refLabel: this.dataset.refLabel || ''
          };
          openReverseFromView();
        });
      });
    });
  </script>

</body>
</html>