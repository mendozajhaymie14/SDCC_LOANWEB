@extends('admin.index')

@php
use App\Models\Setting;
use App\Models\AppNotificationLog;
use App\Models\CoopMember;
@endphp

@section('admin_content')
<style>
  /* ===== Notification Outbox scoped styles (.nl-*) ===== */
  .nl-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
  }
  .nl-title {
    font-family: 'DM Sans', sans-serif;
    font-weight: 700;
    font-size: 1.25rem;
    color: var(--text);
    margin: 0;
  }
  .nl-subtitle {
    font-size: 0.85rem;
    color: var(--muted);
    margin: 0;
  }

  .nl-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
  }
  .nl-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1rem 1.25rem;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }
  .nl-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
  }
  .nl-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.02em;
  }
  .nl-sent { color: var(--accent3); }
  .nl-failed { color: var(--red); }
  .nl-today { color: var(--accent); }
  .nl-error-rate { color: var(--red); }
  .nl-skipped { color: var(--gold); }

  .nl-mail-notice {
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    border: 1px solid var(--border2);
    font-size: 0.8rem;
    color: var(--text);
    background: var(--card2);
  }
  .nl-mail-notice .notice-dismiss {
    float: right;
    cursor: pointer;
    font-weight: 700;
    color: var(--muted);
  }

  .nl-filters {
    margin-bottom: 1rem;
    padding: 1rem 1.25rem;
    background: var(--card2);
    border: 1px solid var(--border);
    border-radius: 10px;
  }
  .nl-filters form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  .nl-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-end;
  }
  .nl-filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 140px;
    flex: 1;
  }
  .nl-filter-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--muted2);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .nl-filter-control {
    width: 100%;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.65rem 1rem;
    color: var(--text);
    font-size: 0.88rem;
    font-family: 'DM Sans', sans-serif;
  }
  .nl-filter-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 0.25rem;
  }

  .nl-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.55rem 1rem;
    cursor: pointer;
    transition: all 0.15s ease;
    background: var(--surface);
    color: var(--text);
  }
  .nl-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
  }
  .nl-btn-primary {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    border: none;
    color: #ffffff;
  }
  .nl-btn-primary:hover {
    opacity: 0.9;
  }
  .nl-btn-danger {
    background: var(--red);
    border: none;
    color: #ffffff;
  }
  .nl-btn-danger:hover {
    opacity: 0.9;
  }

  .nl-table {
    width: 100%;
    border-collapse: collapse;
  }
  .nl-table th {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--muted);
    padding: 0.7rem 0.8rem;
    text-align: left;
    border-bottom: 1px solid var(--border);
    background: var(--card2);
    position: sticky;
    top: 0;
    z-index: 5;
  }
  .nl-table td {
    padding: 0.8rem 0.8rem;
    font-size: 0.84rem;
    color: var(--text);
    border-bottom: 1px solid rgba(203, 213, 225, 0.4);
  }
  .nl-table tbody tr {
    transition: background 0.15s;
    cursor: pointer;
  }
  .nl-table tbody tr:hover {
    background: rgba(2, 132, 199, 0.04);
  }
  .nl-table-responsive {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 10px;
  }

  .nl-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.7rem;
    border-radius: 50px;
  }
  .nl-pill-sent { background: rgba(52, 211, 153, 0.15); color: var(--accent3); }
  .nl-pill-failed { background: rgba(248, 113, 113, 0.15); color: var(--red); }
  .nl-pill-pending { background: rgba(251, 191, 36, 0.15); color: var(--gold); }
  .nl-pill-skipped { background: rgba(107, 114, 128, 0.15); color: var(--muted); }
  .nl-pill-neutral { background: rgba(2, 132, 199, 0.15); color: var(--accent); }

  .nl-muted { color: var(--muted); font-weight: 400; }
  .nl-loan-link { color: var(--accent); text-decoration: none; font-weight: 600; }
  .nl-loan-link:hover { text-decoration: underline; }
  .nl-error-text { color: var(--red); }

  /* Detail modal — value rows use the shared .form-label / .form-group classes */
  .nl-modal-value {
    font-size: 0.9rem;
    color: var(--text);
    word-break: break-word;
  }
  .nl-modal-error {
    background: rgba(248, 113, 113, 0.1);
    border: 1px solid rgba(248, 113, 113, 0.3);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    color: var(--red);
    font-size: 0.85rem;
    word-break: break-word;
  }
  .nl-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
  }

  .flash {
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 1rem;
    border-left: 4px solid;
  }
  .flash-success { background: rgba(52, 211, 153, 0.1); border-color: var(--accent3); color: var(--accent3); }
  .flash-error { background: rgba(248, 113, 113, 0.1); border-color: var(--red); color: var(--red); }

  @media (max-width: 768px) {
    .nl-stats { grid-template-columns: 1fr; }
    .nl-page-header { flex-direction: column; align-items: flex-start; }
    .nl-filter-row { flex-direction: column; align-items: stretch; }
    .nl-filter-group { min-width: unset; }
    .nl-filter-actions { justify-content: flex-start; }
  }
</style>

@if(session('success'))
<div class="flash flash-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="flash flash-error">{{ session('error') }}</div>
@endif

<div class="panel app-panel">
  <div class="nl-page-header">
    <div>
      <h2 class="nl-title">Email Notification Outbox</h2>
      <p class="nl-subtitle">All system-triggered email notifications (reminders, approvals, disbursements, payments).</p>
    </div>
    <form method="POST" action="{{ route('reminders.run') }}" onsubmit="return confirm('Run reminders for all eligible loans now?')">
      @csrf
      <button type="submit" class="nl-btn nl-btn-primary">
        <i class="fas fa-bell mr-2"></i> Run Reminders Now
      </button>
    </form>
  </div>

  <!-- Stat cards -->
  <div class="nl-stats">
    <div class="nl-card">
      <div class="nl-label">Total</div>
      <div class="nl-value">{{ $stats['total'] }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-sent">Sent</div>
      <div class="nl-value nl-sent">{{ $stats['sent'] }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-failed">Failed</div>
      <div class="nl-value nl-failed">{{ $stats['failed'] }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-skipped">Skipped</div>
      <div class="nl-value nl-skipped">{{ $stats['skipped'] }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-today">Today</div>
      <div class="nl-value nl-today">{{ $stats['today'] }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-error-rate">Error Rate</div>
      <div class="nl-value nl-error-rate">{{ $stats['sent'] + $stats['failed'] > 0 ? round(($stats['failed'] / ($stats['sent'] + $stats['failed'])) * 100, 1) : 0 }}%</div>
    </div>
  </div>

  <!-- Mail-driver notice -->
  @php $mailDriver = config('mail.default'); @endphp
  @if($mailDriver === 'log')
  @elseif($mailDriver === 'array')
  <div class="nl-mail-notice">
    <strong>Mail Driver: ARRAY</strong> — Emails are captured in memory for testing only.
    <span class="notice-dismiss" onclick="this.parentElement.style.display='none'">&times;</span>
  </div>
  @endif

  <!-- Filters (GET form) -->
  <div class="nl-filters">
    <form method="GET" action="{{ route('admin.notifications-log') }}">
      <div class="nl-filter-row">
        <div class="nl-filter-group">
          <label class="nl-filter-label">Search</label>
          <input type="text" name="search" class="nl-filter-control" placeholder="Search subject or recipient..." value="{{ request('search') }}" />
        </div>
        <div class="nl-filter-group">
          <label class="nl-filter-label">Status</label>
          <select name="status" class="nl-filter-control">
            <option value="">All Statuses</option>
            <option value="Sent" {{ request('status') === 'Sent' ? 'selected' : '' }}>Sent</option>
            <option value="Failed" {{ request('status') === 'Failed' ? 'selected' : '' }}>Failed</option>
            <option value="Skipped" {{ request('status') === 'Skipped' ? 'selected' : '' }}>Skipped</option>
          </select>
        </div>
        <div class="nl-filter-group">
          <label class="nl-filter-label">Type</label>
          <select name="type" class="nl-filter-control">
            <option value="">All Types</option>
            <option value="approved" {{ request('type') === 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ request('type') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            <option value="due_soon" {{ request('type') === 'due_soon' ? 'selected' : '' }}>Due Soon</option>
            <option value="due_today" {{ request('type') === 'due_today' ? 'selected' : '' }}>Due Today</option>
            <option value="overdue" {{ request('type') === 'overdue' ? 'selected' : '' }}>Overdue</option>
            <option value="payment_received" {{ request('type') === 'payment_received' ? 'selected' : '' }}>Payment Received</option>
            <option value="membership_approved" {{ request('type') === 'membership_approved' ? 'selected' : '' }}>Membership Approved</option>
            <option value="membership_rejected" {{ request('type') === 'membership_rejected' ? 'selected' : '' }}>Membership Rejected</option>
          </select>
        </div>
        <div class="nl-filter-group">
          <label class="nl-filter-label">Channel</label>
          <select name="channel" class="nl-filter-control">
            <option value="">All Channels</option>
            <option value="email" {{ request('channel') === 'email' ? 'selected' : '' }}>Email</option>
            <option value="sms" {{ request('channel') === 'sms' ? 'selected' : '' }}>SMS</option>
          </select>
        </div>
      </div>
      <div class="nl-filter-row" style="margin-top: 0.5rem;">
        <div class="nl-filter-group">
          <label class="nl-filter-label">Start Date</label>
          <input type="date" name="start_date" class="nl-filter-control" value="{{ request('start_date') }}" />
        </div>
        <div class="nl-filter-group">
          <label class="nl-filter-label">End Date</label>
          <input type="date" name="end_date" class="nl-filter-control" value="{{ request('end_date') }}" />
        </div>
      </div>
      <div class="nl-filter-actions">
        <button type="submit" class="nl-btn">Filter</button>
        <a href="{{ route('admin.notifications-log') }}" class="nl-btn nl-btn-danger" style="background: transparent; color: var(--text); border-color: var(--border);">Reset</a>
      </div>
    </form>
  </div>

  <!-- Outbox Table -->
  <div class="nl-table-responsive">
    <table class="nl-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Member</th>
          <th>Recipient</th>
          <th>Subject</th>
          <th>Loan</th>
          <th>Type</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="notifLogsTableBody">
        @if($logs->isNotEmpty())
          @foreach($logs as $log)
            @php
              // Resolve member like the Repayments page: user link first,
              // then email match (portal-less / staff-encoded loans have no user).
              $loanApplication = $log->loanApplication;
              $member = $log->user?->coopMember ?? ($loanApplication ? CoopMember::forLoanApplication($loanApplication) : null);
              $memberId   = $member?->member_id ?? null;
              $memberName = $log->user?->name ?? $member?->full_name ?? null;
              // Always read app_id from the mirror row this log's loan was
              // approved through; never trust applications.id/app_id being
              // off-by-one from duplicate mirrors.
              $loanRef = $loanApplication?->application?->app_id ?? null;
              $loanAppId = $loanApplication?->application?->id ?? null;
            @endphp
            <tr class="hover:bg-gray-50 transition-colors @if($loop->odd) bg-white @else bg-gray-50 @endif" data-status="{{ strtolower($log->status) }}"
                onclick="openNotifModal(
                  {{ $log->id }},
                  {{ json_encode($log->subject ?? '') }},
                  {{ json_encode($log->recipient_email ?? '') }},
                  {{ json_encode(AppNotificationLog::typeLabel($log->notification_type)) }},
                  {{ json_encode($log->status ?? '') }},
                  {{ json_encode($log->channel ?? '') }},
                  {{ json_encode($log->error_message ?? '') }},
                  {{ json_encode($log->created_at->format('M d, Y H:i')) }},
                  {{ json_encode($log->sent_at?->format('M d, Y H:i')) }},
                  {{ json_encode($memberName) }},
                  {{ json_encode($memberId) }},
                  {{ json_encode($loanRef) }},
                  {{ $loanAppId ?? 'null' }})">
              <td class="font-medium text-gray-600 text-sm">{{ $log->created_at->format('M d, Y H:i') }}</td>
              <td class="font-medium text-gray-600 text-sm">
                @if($memberId || $memberName)
                  <div style="font-weight: 600;">{{ $memberName ?? '—' }}</div>
                  @if($memberId)
                    <div class="nl-muted" style="font-size: 0.72rem;">{{ $memberId }}</div>
                  @endif
                @else
                  —
                @endif
              </td>
              <td class="font-medium text-gray-600 text-sm">{{ $log->recipient_email }}</td>
              <td class="font-medium text-gray-600 text-sm" style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $log->subject }}</td>
              <td class="font-medium text-gray-600 text-sm">
                @if($loanRef)
                  <a href="#" class="nl-loan-link" onclick="openNotifLoan('{{ $loanRef }}', {{ $loanAppId }}); return false;">{{ $loanRef }}</a>
                @else
                  —
                @endif
              </td>
              <td class="font-medium text-gray-600 text-sm">
                <span class="nl-pill nl-pill-neutral">{{ AppNotificationLog::typeLabel($log->notification_type) }}</span>
              </td>
              <td class="font-medium text-gray-600 text-sm">
                <div>
                  <span class="nl-pill {{ 'nl-pill-' . strtolower($log->status) }}">
                    <span class="status-dot"></span>{{ $log->status }}
                  </span>
                  @if($log->status === 'Failed' || $log->status === 'Skipped')
                    <div class="nl-muted" style="font-size: 0.72rem; margin-top: 0.3rem;">{{ $log->error_message }}</div>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        @else
          <tr>
            <td colspan="7" class="nl-empty">No notification logs found.</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="mt-4">
    {{ $logs->appends(request()->query())->links('vendor.pagination.admin') }}
  </div>
</div>

<!-- Detail modal (centered, shared .modal classes) -->
<div class="modal"
     id="modal-notifDetailModal"
     onclick="event.stopPropagation()"
     style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
  <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
      <div class="modal-title" id="notifModalTitle">Notification Detail</div>
      <span id="notifModalPill"></span>
    </div>
    <div class="modal-close" onclick="closeNotifModal()"><i class="fa-solid fa-xmark nav-icon"></i></div>
  </div>
  <div id="notifModalBody" style="flex:1;overflow-y:auto;"></div>
  <div class="nl-modal-footer">
    <button class="btn-cancel" style="width:auto;padding:0.55rem 1.5rem;" onclick="closeNotifModal()">Close</button>
  </div>
</div>

<script>
function openNotifModal(id, subject, recipient, typeLabel, status, channel, error, created, sentAt, memberName, memberId, loanRef, loanAppId) {
  document.getElementById('notifModalTitle').textContent = typeLabel + ' email';

  var pill = document.getElementById('notifModalPill');
  pill.className = 'nl-pill nl-pill-' + String(status).toLowerCase();
  pill.innerHTML = '<span class="status-dot"></span>' + status;

  var body = '';
  body += nlField('Recipient', recipient || '—');
  body += nlField('Subject', subject || '—');
  body += nlField('Type', typeLabel || '—');
  body += nlField('Channel', channel ? channel.charAt(0).toUpperCase() + channel.slice(1) : '—');
  body += nlField('Status', status || '—');
  body += nlField('Member', memberName
      ? memberName + (memberId ? ' (' + memberId + ')' : '')
      : (memberId || '—'));
  body += nlField('Loan', loanRef
      ? '<a href="#" class="nl-loan-link" onclick="openNotifLoan(\'' + loanRef + '\', ' + loanAppId + '); return false;">' + loanRef + '</a>'
      : '—');
  body += nlField('Date and time', created || '—');
  if (sentAt && sentAt !== created) {
    body += nlField('Sent at', sentAt);
  }
  if (status === 'Failed' || status === 'Skipped') {
    body += '<div class="form-group"><div class="form-label">Error</div><div class="nl-modal-error">' + (error || '—') + '</div></div>';
  }

  document.getElementById('notifModalBody').innerHTML = body;
  openModal('notifDetailModal');
}

function nlField(label, valueHtml) {
  return '<div class="form-group"><div class="form-label">' + label + '</div>' +
         '<div class="nl-modal-value">' + valueHtml + '</div></div>';
}

function openNotifLoan(appRef, numericId) {
  if (typeof window.openAppDetail === 'function') {
    window.openAppDetail({ currentTarget: { dataset: { appId: appRef, numericId: String(numericId) } } });
  }
}

function closeNotifModal() {
  document.getElementById('notifModalBody').innerHTML = '';
  closeAllModals();
}

// Esc closes the modal when open
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    var m = document.getElementById('modal-notifDetailModal');
    if (m && m.classList.contains('open')) {
      closeNotifModal();
    }
  }
});

// Backdrop click clears the body too (overlay renders after this script)
document.addEventListener('DOMContentLoaded', function () {
  var overlay = document.getElementById('overlay');
  if (overlay) {
    overlay.addEventListener('click', function () {
      document.getElementById('notifModalBody').innerHTML = '';
    });
  }
});
</script>
@endsection
