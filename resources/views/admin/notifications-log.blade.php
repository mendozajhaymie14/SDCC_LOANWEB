@extends('admin.index')

@php
use App\Models\Setting;
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
    grid-template-columns: repeat(2, 1fr);
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

  .nl-drawer {
    display: none !important;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 2000;
    align-items: center;
    justify-content: flex-end;
  }
  .nl-drawer.open {
    display: flex !important;
  }
  .nl-drawer-panel {
    background: var(--surface);
    width: 100%;
    max-width: 480px;
    max-height: 90vh;
    border-radius: 20px 0 0 20px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
    position: relative;
    z-index: 2001;
    padding: 2rem;
    overflow-y: auto;
  }
  .nl-drawer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
  }
  .nl-drawer-title {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text);
    margin: 0;
  }
  .nl-drawer-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--card2);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.95rem;
    color: var(--muted);
  }
  .nl-drawer-field {
    margin-bottom: 1rem;
  }
  .nl-drawer-field-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted2);
    margin-bottom: 0.35rem;
  }
  .nl-drawer-field-value {
    font-size: 0.88rem;
    color: var(--text);
    word-break: break-word;
  }
  .nl-empty {
    text-align: center;
    padding: 2.5rem 0;
    color: var(--muted);
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
    .nl-drawer-panel { max-width: 100%; width: 100%; border-radius: 0; }
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
      <div class="nl-value">{{ $logs->total() }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-sent">Sent</div>
      <div class="nl-value nl-sent">{{ $logs->filter(fn($l) => $l->status === 'Sent')->count() }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-failed">Failed</div>
      <div class="nl-value nl-failed">{{ $logs->filter(fn($l) => $l->status === 'Failed')->count() }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-today">Today</div>
      <div class="nl-value nl-today">{{ $logs->filter(fn($l) => $l->created_at->isToday())->count() }}</div>
    </div>
    <div class="nl-card">
      <div class="nl-label nl-error-rate">Error Rate</div>
      <div class="nl-value nl-error-rate">{{ $logs->total() ? round(($logs->filter(fn($l) => $l->status === 'Failed')->count() / $logs->total()) * 100, 1) : 0 }}%</div>
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
            <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
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
            <option value="welcome" {{ request('type') === 'welcome' ? 'selected' : '' }}>Welcome</option>
            <option value="reminder_failed" {{ request('type') === 'reminder_failed' ? 'selected' : '' }}>Reminder Failed</option>
            <option value="reminder_summary" {{ request('type') === 'reminder_summary' ? 'selected' : '' }}>Reminder Summary</option>
            <option value="system" {{ request('type') === 'system' ? 'selected' : '' }}>System</option>
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
          <th>Recipient</th>
          <th>Subject</th>
          <th>Member Ref</th>
          <th>Loan Ref</th>
          <th>Type</th>
          <th>Channel</th>
          <th>Status</th>
          <th>Error</th>
          <th>Sent At</th>
        </tr>
      </thead>
      <tbody id="notifLogsTableBody">
        @if($logs->isNotEmpty())
          @foreach($logs as $log)
            <tr class="hover:bg-gray-50 transition-colors @if($loop->odd) bg-white @else bg-gray-50 @endif" data-status="{{ strtolower($log->status) }}" onclick="openNotifDrawer({{ $log->id }}, {{ json_encode($log->subject ?? '') }}, {{ json_encode($log->recipient_email ?? '') }}, {{ json_encode($log->notification_type ?? '') }}, {{ json_encode($log->status ?? '') }}, {{ json_encode($log->channel ?? '') }}, {{ json_encode($log->error_message ?? '') }}, {{ json_encode($log->sent_at?->format('M d, Y H:i') ?? '') }}, {{ $log->user_id ?? 'null' }}, {{ $log->loan_application_id ?? 'null' }})">
              <td class="font-medium text-gray-600 text-sm">{{ $log->created_at->format('M d, Y H:i') }}</td>
              <td class="font-medium text-gray-600 text-sm">{{ $log->recipient_email }}</td>
              <td class="font-medium text-gray-600 text-sm" style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $log->subject }}</td>
              <td class="font-medium text-gray-600 text-sm">{{ $log->user_id ? '<a href="#" class="text-accent hover:underline">' . $log->user_id . '</a>' : '—' }}</td>
              <td class="font-medium text-gray-600 text-sm">{{ $log->loan_application_id ? 'APP-' . str_pad($log->loan_application_id, 4, '0', STR_PAD_LEFT) : '—' }}</td>
              <td class="font-medium text-gray-600 text-sm">{{ ucfirst($log->notification_type) }}</td>
              <td class="font-medium text-gray-600 text-sm">{{ ucfirst($log->channel) }}</td>
              <td class="font-medium text-gray-600 text-sm">
                <span class="nl-pill {{ 'nl-pill-' . strtolower($log->status) }}">
                  <span class="status-dot"></span>{{ $log->status }}
                </span>
              </td>
              <td class="font-medium text-gray-600 text-sm text-red-600">{{ $log->error_message ?? '—' }}</td>
              <td class="font-medium text-gray-600 text-sm">{{ $log->sent_at?->format('M d, Y H:i') ?? '—' }}</td>
            </tr>
          @endforeach
        @else
          <tr>
            <td colspan="10" class="nl-empty">No notification logs found.</td>
          </tr>
        @endif
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="mt-4 flex justify-center">
    {{ $logs->appends(request()->query())->links() }}
  </div>
</div>

<!-- Drawer overlay -->
<div class="nl-drawer" id="notifDrawerOverlay" onclick="closeNotifDrawer()">
  <div class="nl-drawer-panel" onclick="event.stopPropagation()">
    <div class="nl-drawer-header">
      <h3 class="nl-drawer-title" id="drawerTitle">Notification Detail</h3>
      <div class="nl-drawer-close" onclick="closeNotifDrawer()">&times;</div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Subject</div>
      <div class="nl-drawer-field-value" id="drawerSubject"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Recipient</div>
      <div class="nl-drawer-field-value" id="drawerRecipient"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Type</div>
      <div class="nl-drawer-field-value" id="drawerType"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Channel</div>
      <div class="nl-drawer-field-value" id="drawerChannel"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Status</div>
      <div class="nl-drawer-field-value" id="drawerStatus"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Error Message</div>
      <div class="nl-drawer-field-value" id="drawerError">{{ '—' }}</div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Sent At</div>
      <div class="nl-drawer-field-value" id="drawerSentAt"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Member Ref</div>
      <div class="nl-drawer-field-value" id="drawerMemberRef"></div>
    </div>
    <div class="nl-drawer-field">
      <div class="nl-drawer-field-label">Loan Ref</div>
      <div class="nl-drawer-field-value" id="drawerLoanRef"></div>
    </div>
  </div>
</div>

<script>
function openNotifDrawer(id, subject, recipient, type, status, channel, error, sentAt, memberId, loanAppId) {
  document.getElementById('drawerTitle').textContent = 'Notification #' + id;
  document.getElementById('drawerSubject').textContent = subject || '—';
  document.getElementById('drawerRecipient').textContent = recipient || '—';
  document.getElementById('drawerType').textContent = type ? type.charAt(0).toUpperCase() + type.slice(1) : '—';
  document.getElementById('drawerStatus').textContent = status || '—';
  document.getElementById('drawerChannel').textContent = channel ? channel.charAt(0).toUpperCase() + channel.slice(1) : '—';
  document.getElementById('drawerError').textContent = error || '—';
  document.getElementById('drawerSentAt').textContent = sentAt || '—';
  document.getElementById('drawerMemberRef').textContent = memberId ? '#' + memberId : '—';
  document.getElementById('drawerLoanRef').textContent = loanAppId ? 'APP-' + String(loanAppId).padStart(4, '0') : '—';
  document.getElementById('notifDrawerOverlay').classList.add('open');
}
function closeNotifDrawer() {
  document.getElementById('notifDrawerOverlay').classList.remove('open');
}

function filterNotifLogs(term) {
  const termLower = term.trim().toLowerCase();
  const rows = document.querySelectorAll('#notifLogsTableBody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = termLower ? (text.includes(termLower) ? '' : 'none') : '';
  });
}

function filterNotifLogsByStatus(status) {
  const rows = document.querySelectorAll('#notifLogsTableBody tr');
  rows.forEach(row => {
    const rowStatus = row.dataset.status;
    row.style.display = !status || rowStatus === status.toLowerCase() ? '' : 'none';
  });
}
</script>
@endsection