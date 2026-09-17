<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SCC.AI — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  @include ('admin.css')
</head>
<body>

<!-- SIDEBAR -->
@include ('admin.sidebar')

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">All Applications <span>/ Loan Management</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Search applications…" oninput="handleSearch(this.value)" onfocus="showSearchResults()" onblur="setTimeout(hideSearchResults,200)"/>
      </div>
      <div class="search-results" id="searchResults"></div>
    </div>
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
    @php
    $applications = \Illuminate\Support\Facades\DB::table('applications')->orderBy('id', 'desc')->get();
@endphp

    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">All Applications</h2>
          <span class="records-count">Total Records: {{ $applications->count() }}</span>
        </div>
        <button class="btn-new" type="button" onclick="openModal('newApp')">+ New Application</button>
      </div>

      <div class="filter-row">
        <button class="filter-btn active" onclick="filterApps('all',this)">All ({{ $applications->count() }})</button>
        <button class="filter-btn" onclick="filterApps('approved',this)">Approved</button>
        <button class="filter-btn" onclick="filterApps('pending',this)">Pending</button>
        <button class="filter-btn" onclick="filterApps('rejected',this)">Rejected</button>
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Applicant</th>
              <th>Loan Type</th>
              <th>Amount</th>
              <th>Status</th>
              <th>AI Score</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($applications as $app)
  <tr data-status="{{ strtolower($app->status) }}">
    <td class="td-mono">#{{ $app->app_id }}</td>
    <td class="applicant-name">{{ $app->applicant }}</td>
    <td><span class="loan-type-tag">{{ $app->loan_type }}</span></td>
    <td>₱{{ number_format($app->amount, 2) }}</td>
    <td>
      <span class="status-pill {{ strtolower($app->status) }}">
        {{ ucfirst($app->status) }}
      </span>
    </td>
    <td class="td-mono">{{ $app->ai_score ?? 'N/A' }}</td>
    <td class="td-date">{{ \Carbon\Carbon::parse($app->created_at)->format('M d, Y') }}</td>
    <td>
      <button class="btn-review" type="button" onclick="openAppDetail('{{ $app->app_id }}')">View</button>
    </td>
  </tr>
@empty
  <tr>
    <td colspan="8" class="empty-table-msg">No applications found.</td>
  </tr>
@endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @include('admin.footer')
</div>

<!-- ═══ DRAWER: Application Detail ═══ -->
<div class="drawer" id="drawer" style="display: none;">
  <div class="drawer-header">
    <div class="drawer-title">Application Detail</div>
    <div class="modal-close" type="button" onclick="closeDrawer()">✕</div>
  </div>
  <div class="drawer-body" id="drawer-body"></div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<script>
  function filterApps(status, btnEl) {
    // Mark the clicked tab as active, un-mark the rest.
    document.querySelectorAll('.filter-row .filter-btn')
      .forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    // Show only rows whose data-status matches, or all rows for 'all'.
    document.querySelectorAll('.app-table tbody tr[data-status]')
      .forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
      });
  }
</script>
</body>
</html>