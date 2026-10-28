<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Notification Outbox</title>
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
    <div class="topbar-title" id="topbarTitle">Notification Outbox <span>/ Email Logs</span></div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <div class="notif-dropdown">
    <div class="notif-head">
      <div class="notif-head-title">Notifications</div>
      <div class="notif-mark-all" onclick="markAllRead()">Mark all read</div>
    </div>
  </div>

  @if(session('success'))
<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded text-green-800 text-sm">{{ session('success') }}</div>
@endif
    <div class="panel app-panel">
      <div class="page-header">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <h2 class="panel-title">Email Notification Outbox</h2>
          <form method="POST" action="{{ route('reminders.run') }}" onsubmit="return confirm('Run reminders for all eligible loans now?')">
            @csrf
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-medium transition-colors">
              <i class="fas fa-bell mr-2"></i>Run Reminders Now
            </button>
          </form>
        </div>
      </div>

      <!-- Filters -->
      <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg mb-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">Search</label>
            <input type="text" id="notifSearchInput" class="form-input" placeholder="Search subject or recipient..." oninput="filterNotifLogs(this.value)" />
          </div>
          <div>
            <label class="form-label">Status Filter</label>
            <select id="notifStatusFilter" class="form-select" onchange="filterNotifLogsByStatus(this.value)">
              <option value="">All Statuses</option>
              <option value="Sent">Sent</option>
              <option value="Failed">Failed</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div>
            <label class="form-label">Start Date</label>
            <input type="date" id="notifStartDate" class="form-input" />
          </div>
          <div>
            <label class="form-label">End Date</label>
            <input type="date" id="notifEndDate" class="form-input" />
          </div>
        </div>
      </div>

      <!-- Outbox Table -->
      <div class="table-responsive">
        <table class="min-w-full divide divide-gray-200">
          <thead>
            <tr>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b font-mono">Date</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Recipient</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Subject</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Type</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Status</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Sent At</th>
            </tr>
          </thead>
          <tbody id="notifLogsTable">
            @if($logs->isNotEmpty())
              @php $rowIndex = 0; @endphp
              @foreach($logs as $log)
                @php $rowIndex++; @endphp
                <tr class="hover:bg-gray-50 transition-colors @if($rowIndex % 2 === 1) bg-white @else bg-gray-50 @endif">
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->created_at->format('M d, Y H:i') }}</td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->recipient_email }}</td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->subject }}</td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->notification_type }}</td>
                  <td class="py-3 px-4">
                    <span class="status-pill {{ strtolower($log->status) }}">
                      <span class="status-dot"></span>{{ $log->status }}
                    </span>
                  </td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->sent_at?->format('M d, Y H:i') ?? '—' }}</td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="6" class="py-6 text-center text-gray-400">No notification logs found.</td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- GLOBAL OVERLAY FOR MODALS -->
<div class="overlay" id="overlay" onclick="closeAllModals()"></div>

<script>
function filterNotifLogs(term) {
  const termLower = term.trim().toLowerCase();
  const rows = document.querySelectorAll('#notifLogsTable tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = termLower ? (text.includes(termLower) ? '' : 'none') : '';
  });
}

function filterNotifLogsByStatus(status) {
  const rows = document.querySelectorAll('#notifLogsTable tbody tr');
  rows.forEach(row => {
    const statusCell = row.querySelector('td:nth-child(5)');
    if (!statusCell) return;
    const rowStatus = statusCell.textContent.trim().toLowerCase();
    row.style.display = !status || rowStatus === status.toLowerCase() ? '' : 'none';
  });
}
</script>

</body>
</html>