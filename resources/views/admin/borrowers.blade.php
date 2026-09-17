<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SCC.AI — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  @include('admin.css')
</head>
<body>

<!-- SIDEBAR -->
@include('admin.sidebar')

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">Borrowers Directory <span>/ Clients</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchBorrowerInput" placeholder="Search borrowers…" />
      </div>
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
          <h2 class="panel-title">All Borrowers</h2>
          <span class="records-count">Total Records: {{ $borrowers->count() }}</span>
        </div>
        <button class="btn-new" type="button" onclick="openModal('addBorrower')">+ Add Borrower</button>
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>BORROWER ID</th>
              <th>NAME</th>
              <th>CONTACT INFO</th>
              <th>MONTHLY INCOME</th>
              <th>AI CREDIT SCORE</th>
              <th>STATUS</th>
              <th>JOINED DATE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($borrowers as $borrower)
              <tr>
                <td class="td-mono">#{{ $borrower->borrower_id }}</td>
                <td class="applicant-name">{{ $borrower->full_name }}</td>
                <td>
                  <div style="font-size: 0.85rem;">{{ $borrower->email }}</div>
                  <div style="font-size: 0.75rem; color: var(--muted, #64748b);">{{ $borrower->phone_number ?? 'N/A' }}</div>
                </td>
                <td>₱{{ number_format($borrower->monthly_income, 2) }}</td>
                <td class="td-mono">
                  <span style="font-weight: 600; color: {{ ($borrower->ai_credit_score ?? 0) >= 600 ? '#10b981' : '#ef4444' }};">
                    {{ $borrower->ai_credit_score ?? 'N/A' }}
                  </span>
                </td>
                <td>
                  <span class="status-pill {{ strtolower($borrower->status) }}">
                    <span class="status-dot"></span>{{ ucfirst($borrower->status) }}
                  </span>
                </td>
                <td class="td-date">{{ $borrower->created_at ? \Carbon\Carbon::parse($borrower->created_at)->format('M d, Y') : '—' }}</td>
                <td>
                  <button class="btn-review" type="button" onclick="openBorrowerDetail('{{ $borrower->id }}')">View</button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="empty-table-msg">No borrowers found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @include('admin.footer')
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

</body>
</html>