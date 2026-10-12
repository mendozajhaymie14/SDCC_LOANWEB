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
    <div class="topbar-title" id="topbarTitle">Credit Assessment <span>/ AI Scoring</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="creditSearchInput" placeholder="Search applicants…" oninput="handleCreditSearch(this.value)"/>
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
  <div class="content-body" id="page-credit">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">AI Scoring Summary</h2>
          <span class="records-count">Total Evaluated: {{ $totalEvaluated }}</span>
        </div>
      </div>

      <!-- KPI GRID -->
      <div class="kpi-grid" style="margin-bottom:1.6rem;">
        <div class="kpi-card blue">
          <div class="kpi-top"><div class="kpi-label">Total Evaluated</div><div class="kpi-icon blue"></div></div>
          <div class="kpi-val">{{ $totalEvaluated }}</div>
          <div class="kpi-sub"><span class="kpi-delta up"></span></div>
        </div>
        <div class="kpi-card green">
          <div class="kpi-top"><div class="kpi-label">High Grade %</div><div class="kpi-icon green"></div></div>
          <div class="kpi-val">{{ $highGradePct }}%</div>
          <div class="kpi-sub"><span class="kpi-delta up"></span></div>
        </div>
        <div class="kpi-card red">
          <div class="kpi-top"><div class="kpi-label">High Risk %</div><div class="kpi-icon red"></div></div>
          <div class="kpi-val">{{ $highRiskPct }}%</div>
          <div class="kpi-sub"><span class="kpi-delta down"></span></div>
        </div>
      </div>

      <!-- Applicant Scoring Table -->
      <div class="panel">
        <div class="panel-title" style="margin-bottom:1.2rem;">Applicant AI Scores</div>
        <div class="filter-row">
          <button class="filter-btn active" onclick="filterCredit('all',this)">All ({{ $evaluated->count() }})</button>
          <button class="filter-btn" onclick="filterCredit('approved',this)">Approved</button>
          <button class="filter-btn" onclick="filterCredit('review',this)">Manual Review</button>
          <button class="filter-btn" onclick="filterCredit('risk',this)">High Risk</button>
        </div>

        <div class="table-wrap">
          <table class="app-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Requested Amount</th>
                <th>Calculated AI Score</th>
                <th>Recommendation</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($evaluated as $e)
                <tr data-status="{{ strtolower($e->recommendation) }}">
                  <td class="applicant-name">{{ $e->name }}</td>
                  <td>₱{{ number_format($e->requested_amount, 2) }}</td>
                  <td class="td-mono">
                    @php
                      $scoreColor = $e->ai_score !== null && $e->ai_score >= 51 ? 'var(--accent3)' : ($e->ai_score !== null ? 'var(--red)' : 'var(--muted)');
                    @endphp
                    <span style="font-weight:700; color: {{ $scoreColor }}">{{ $e->ai_score ?? 'N/A' }}</span>
                  </td>
                  <td>
                    <span class="status-pill {{ strtolower($e->recommendation) }}">
                      {{ $e->recommendation }}
                    </span>
                  </td>
                  <td>
                    <button class="btn-review" type="button" onclick="openCreditDetail('{{ $e->id }}')">Evaluate / Details</button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="empty-table-msg">No applicants found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Credit Detail Modal -->
<div class="modal" id="modal-creditDetail" onclick="event.stopPropagation()">
  <div class="modal-header">
    <div class="modal-title" id="creditDetailTitle">Credit Assessment Detail</div>
    <div class="modal-close" onclick="closeAllModals()">✕</div>
  </div>
  <div id="creditDetailBody"></div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

@include('admin.footer')

</body>
</html>