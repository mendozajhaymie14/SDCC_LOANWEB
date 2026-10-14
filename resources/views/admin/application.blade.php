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
    <div class="topbar-title" id="topbarTitle"> Loan Applications <span>/ Loan Management</span></div>
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
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">All Loan Applications</h2>
          <span class="records-count">Total Records: {{ $applications->count() }}</span>
        </div>
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
  <tr data-status="{{ strtolower($app->status) }}" data-app-id="{{ $app->app_id }}">
    <td class="td-mono">#{{ $app->app_id }}</td>
    <td class="applicant-name">{{ $app->applicant }}</td>
    <td><span class="loan-type-tag">{{ $app->loan_type }}</span></td>
    <td>₱{{ number_format($app->amount, 2) }}</td>
    <td>
      <span class="status-pill {{ strtolower($app->status) }}">
        {{ ucfirst($app->status) }}
      </span>
    </td>
    <td class="td-mono">
      @php
        $aiScore = $app->ai_score ?? ($app->loanApplication ? $app->loanApplication->ai_score : null);
        $aiColor = $aiScore !== null && $aiScore >= 51 ? 'var(--accent3)' : ($aiScore !== null ? 'var(--red)' : 'var(--muted)');
      @endphp
      <span style="font-weight:700; color: {{ $aiColor }}">{{ $aiScore ?? 'N/A' }}</span>
    </td>
    <td class="td-date">{{ \Carbon\Carbon::parse($app->created_at)->format('M d, Y') }}</td>
    <td>
      <div class="ai-note-badge" style="display:none;">
        {{ $app->ai_analysis_note ?? $app->loanApplication?->ai_analysis_notes ?? 'No scan performed' }}
      </div>
      <button class="btn-review" type="button" data-app-id="{{ $app->app_id }}" data-documents='@json($app->loanApplication?->documents ?? [])' data-ai-notes='@json($app->ai_analysis_note ?? $app->loanApplication?->ai_analysis_notes ?? null)' onclick="openAppDetail(event)">View</button>
    </td>
    <td>
      <div class="ai-note-tooltip" style="display:none; padding:0.5rem; background:#f8f9fa; border:1px solid #dee2e6; margin-top:0.5rem; font-size:0.85rem;">
        @if($app->ai_analysis_note)
          <strong>AI Scan Note:</strong> {{ $app->ai_analysis_note }}
        @elseif($app->loanApplication?->ai_analysis_notes)
          <strong>AI Scan Note:</strong> {{ $app->loanApplication->ai_analysis_notes }}
        @else
          No AI scan performed
        @endif
      </div>
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

</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

@include('admin.footer')
@include('admin.partials.application-modal')

</body>
</html>