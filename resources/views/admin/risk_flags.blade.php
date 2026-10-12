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
    <div class="topbar-title" id="topbarTitle">Risk Flags <span>/ Monitoring</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="riskSearchInput" placeholder="Search flagged accounts…" oninput="handleRiskSearch(this.value)"/>
      </div>
    </div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="content-body" id="page-risk">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Risk Flags Monitoring</h2>
          <span class="records-count">Total Flagged: {{ $totalFlagged }}</span>
        </div>
      </div>

      <!-- Metric Cards -->
      <div class="kpi-grid" style="margin-bottom:1.6rem;">
        <div class="kpi-card red">
          <div class="kpi-top"><div class="kpi-label">Total Flagged Accounts</div><div class="kpi-icon red"></div></div>
          <div class="kpi-val">{{ $totalFlagged }}</div>
          <div class="kpi-sub"><span class="kpi-delta down"></span></div>
        </div>
        <div class="kpi-card gold">
          <div class="kpi-top"><div class="kpi-label">Overdue Payments (&gt;30 Days)</div><div class="kpi-icon gold"></div></div>
          <div class="kpi-val">{{ $overdueCount }}</div>
          <div class="kpi-sub"><span class="kpi-delta down"></span></div>
        </div>
        <div class="kpi-card purple">
          <div class="kpi-top"><div class="kpi-label">High-Risk AI Scores</div><div class="kpi-icon purple"></div></div>
          <div class="kpi-val">{{ $highRiskCount }}</div>
          <div class="kpi-sub"><span class="kpi-delta down"></span></div>
        </div>
      </div>

      <!-- Flagged Borrowers Table -->
      <div class="panel">
        <div class="panel-title" style="margin-bottom:1.2rem;">Flagged Borrowers</div>
        <div class="filter-row">
          <button class="filter-btn active" onclick="filterRisk('all',this)">All ({{ $flagged->count() }})</button>
          <button class="filter-btn" onclick="filterRisk('red',this)">Red</button>
          <button class="filter-btn" onclick="filterRisk('amber',this)">Amber</button>
        </div>

        <div class="table-wrap">
          <table class="app-table">
            <thead>
              <tr>
                <th>Member Name</th>
                <th>Loan Application ID</th>
                <th>Flag Reason</th>
                <th>Priority Level</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($flagged as $f)
                <tr data-status="{{ strtolower($f->priority) }}">
                  <td class="applicant-name">{{ $f->member_name }}</td>
                  <td class="td-mono">{{ $f->loan_application_id }}</td>
                  <td>{{ $f->flag_reason }}</td>
                  <td>
                    <span class="status-pill {{ strtolower($f->priority) }}">
                      {{ ucfirst($f->priority) }}
                    </span>
                  </td>
                  <td>
                    <button class="btn-review" type="button" onclick="sendReminder('{{ $f->id }}')">Notify / Send Reminder</button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="empty-table-msg">No flagged accounts.</td>
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