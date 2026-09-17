@php($applications = $applications ?? collect())
<div class="topbar">
  <div class="topbar-title" id="topbarTitle">Dashboard <span>/ Overview</span></div>
  <div class="search-wrap">
    <div class="search-box">
      <span style="color:var(--muted);font-size:.85rem;">🔍</span>
      <input type="text" id="searchInput" placeholder="Search applications…" oninput="handleSearch(this.value)" onfocus="showSearchResults()" onblur="setTimeout(hideSearchResults,200)"/>
    </div>
    <div class="search-results" id="searchResults"></div>
  </div>
  
  <!-- Topbar Settings Icon with Dropdown Trigger -->
  <div class="topbar-actions">
    <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
      🔔<span class="notif-dot" id="notifDot"></span>
    </div>
    <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">
      ⚙️
    </div>
  </div>

  <div class="notif-dropdown" id="notifDropdown">
    <div class="notif-head">
      <div class="notif-head-title">Notifications</div>
      <div class="notif-mark-all" onclick="markAllRead()">Mark all read</div>
    </div>
  </div>
</div>

<div class="page active" id="page-dashboard">
  <!-- KPI GRID -->
  <div class="kpi-grid">
    <div class="kpi-card blue" onclick="switchPage('disbursements', null)" data-tip="View disbursements">
      <div class="kpi-top"><div class="kpi-label">Total Loan Disbursed</div><div class="kpi-icon blue"></div></div>
      <div class="kpi-val">₱0</div>
    </div>
    <div class="kpi-card purple" onclick="window.location.href='{{ route('borrowers.index') }}'" data-tip="View borrowers" style="cursor:pointer;">
      <div class="kpi-top">
        <div class="kpi-label">Active Borrowers</div>
        <div class="kpi-icon purple"></div>
      </div>
      <div class="kpi-val">{{ $activeBorrowersCount ?? 0 }}</div>
      <div class="kpi-sub"><span class="kpi-delta up"></span></div>
    </div>
    <div class="kpi-card green" onclick="switchPage('repayments', null)" data-tip="View repayments">
      <div class="kpi-top"><div class="kpi-label">Repayment Rate</div><div class="kpi-icon green"></div></div>
      <div class="kpi-val">0</div>
      <div class="kpi-sub"><span class="kpi-delta up"></span></div>
    </div>
    <div class="kpi-card gold" onclick="switchPage('applications', document.querySelectorAll('.nav-item')[1])" data-tip="Review pending">
      <div class="kpi-top"><div class="kpi-label">Pending Applications</div><div class="kpi-icon gold"></div></div>
      <div class="kpi-val">{{ $applications->where('status', 'Pending')->count() }}</div>
      <div class="kpi-sub"><span class="kpi-delta down"></span></div>
    </div>
  </div>

  <!-- RECENT APPLICATIONS, LIVE ACTIVITY, & QUICK ACTIONS PANELS -->
  <div class="body-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.6rem;">
    <!-- RECENT APPLICATIONS CARD CONTAINER -->
    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <div>
          <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.05rem;">Recent Applications</div>
          <a href="{{ route('applications.index') }}" style="font-size:0.8rem;color:var(--accent);cursor:pointer;margin-top:0.2rem;display:inline-block;text-decoration:none;">View All →</a>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>APPLICANT</th>
              <th>LOAN TYPE</th>
              <th>AMOUNT</th>
              <th>STATUS</th>
              <th>DATE</th>
            </tr>
          </thead>
          <tbody id="recentAppsTbody">
            @forelse($applications->take(5) as $app)
              <tr style="cursor:pointer;" onclick="openAppDetail('{{ $app->app_id }}')">
                <td><strong>{{ $app->applicant }}</strong></td>
                <td><span class="loan-type-tag">{{ $app->loan_type }}</span></td>
                <td>₱{{ number_format($app->amount, 2) }}</td>
                <td>
                  <span class="status-pill {{ strtolower($app->status) }}">
                    <span class="status-dot"></span>{{ ucfirst($app->status) }}
                  </span>
                </td>
                <td>{{ $app->created_at?->format('M d') ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" style="text-align:center;color:var(--muted);padding:2rem;">No applications yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT COLUMN FOR LIVE ACTIVITY & QUICK ACTIONS CARDS -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">
      <div class="panel">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
          <span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;"></span>
          <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;">Live Activity</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.8rem;">
          <div style="display:flex;gap:0.8rem;align-items:flex-start;font-size:0.85rem;cursor:pointer;" onclick="openAppDetail('SCC-20891')">
          </div>
        </div>
      </div>

      <div class="panel">
        <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1rem;margin-bottom:1rem;">Quick Actions</div>
        <div class="qa-grid-classic">
          <div class="qa-card-classic" onclick="openModal('newApp')">
            <span style="font-size:1.5rem;">📋</span>
            <span>New Application</span>
          </div>
          <div class="qa-card-classic" onclick="openModal('addBorrower')">
            <span style="font-size:1.5rem;">👤</span>
            <span>Add Borrower</span>
          </div>
          <div class="qa-card-classic" onclick="openModal('processPayout')">
            <span style="font-size:1.5rem;">💸</span>
            <span>Process Payout</span>
          </div>
          <div class="qa-card-classic" onclick="openModal('generateReport')">
            <span style="font-size:1.5rem;">📊</span>
            <span>Generate Report</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="page" id="page-applications">
  <div class="page-header">
    <div class="page-heading">All Applications</div>
    <button class="btn-new" onclick="openModal('newApp')">+ New Application</button>
  </div>
  <div class="filter-row">
  <button class="filter-btn active" onclick="filterApps('all',this)">All ({{ $applications->count() }})</button>
  <button class="filter-btn" onclick="filterApps('approved',this)">Approved</button>
  <button class="filter-btn" onclick="filterApps('pending',this)">Pending</button>
  <button class="filter-btn" onclick="filterApps('rejected',this)">Rejected</button>
</div>
  <div class="panel">
    <div class="table-wrap">
      <table>
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
            <tr>
              <td class="td-mono">#{{ $app->app_id }}</td>
              <td><strong>{{ $app->applicant }}</strong></td>
              <td><span class="loan-type-tag">{{ $app->loan_type }}</span></td>
              <td>₱{{ number_format($app->amount, 2) }}</td>
              <td>
                <span class="status-pill {{ strtolower($app->status) }}">
                  <span class="status-dot"></span>{{ ucfirst($app->status) }}
                </span>
              </td>
              <td class="td-mono">{{ $app->ai_score ?? 'N/A' }}</td>
              <td>{{ $app->created_at?->format('M d') ?? '—' }}</td>
              <td>
                <button class="btn-review" onclick="openAppDetail('{{ $app->app_id }}')">View</button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" style="text-align:center;color:var(--muted);padding:2rem;">No applications found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="page" id="page-disbursements"><div class="page-header"><div class="page-heading">Disbursements</div><button class="btn-new" onclick="openModal('processPayout')">+ Process Payout</button></div><div class="panel" style="text-align:center;padding:3rem;color:var(--muted)"><div style="font-size:3rem;">💰</div><div style="margin-top:.8rem;">3 disbursements pending. Connect to backend to view all.</div></div></div>
<div class="page" id="page-repayments"><div class="page-header"><div class="page-heading">Repayments</div></div><div class="panel" style="text-align:center;padding:3rem;color:var(--muted)"><div style="font-size:3rem;">🔄</div><div style="margin-top:.8rem;">Repayment schedule and history. Connect to backend to populate.</div></div></div>
<div class="page" id="page-credit"><div class="page-header"><div class="page-heading">Credit Assessment</div></div><div class="panel" style="text-align:center;padding:3rem;color:var(--muted)"><div style="font-size:3rem;">🔍</div><div style="margin-top:.8rem;">AI-powered credit scoring engine. Connect to backend to view reports.</div></div></div>
<div class="page" id="page-risk"><div class="page-header"><div class="page-heading">Risk Flags</div></div><div class="panel"><p style="color:var(--red);font-size:.9rem;margin-bottom:1rem;">⚠️ 2 active risk flags require attention.</p><div class="feed"><div class="feed-item" onclick="showToast('error','Risk flag opened: Income mismatch on #SCC-20890','⚠️')"><div class="feed-icon fi-red">⚠️</div><div class="feed-text"><div class="feed-msg"><strong>#SCC-20890 Jose Santos</strong> — Income documents don't match bank statements</div><div class="feed-time">High Risk · Flagged 8 min ago</div></div></div><div class="feed-item" onclick="showToast('error','Risk flag opened: Multiple applications from same IP','⚠️')"><div class="feed-icon fi-red">🚩</div><div class="feed-text"><div class="feed-msg"><strong>IP 192.168.1.44</strong> — 3 applications submitted in 10 minutes</div><div class="feed-time">Medium Risk · Flagged 2 hrs ago</div></div></div></div></div></div>
<div class="page" id="page-settings"><div class="page-header"><div class="page-heading">Settings</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="panel"><div class="panel-title" style="margin-bottom:1.2rem;">General</div><div class="form-group"><label class="form-label">Organization Name</label><input class="form-input" value="SCC.AI Lending Corp."/></div><div class="form-group"><label class="form-label">Support Email</label><input class="form-input" value="support@scc.ai"/></div><div class="form-group"><label class="form-label">Default Currency</label><select class="form-select"><option>PHP (₱)</option><option>USD ($)</option></select></div><button class="btn-submit" onclick="showToast('success','Settings saved successfully','✅')">Save Settings</button></div><div class="panel"><div class="panel-title" style="margin-bottom:1.2rem;">Loan Defaults</div><div class="form-group"><label class="form-label">Max Personal Loan</label><input class="form-input" value="500000"/></div><div class="form-group"><label class="form-label">Default Interest Rate (%)</label><input class="form-input" value="1.5"/></div><div class="form-group"><label class="form-label">AI Credit Threshold</label><input class="form-input" value="600"/></div><button class="btn-submit" onclick="showToast('success','Loan defaults updated','✅')">Save Defaults</button></div></div></div>
<div class="page" id="page-admins"><div class="page-header"><div class="page-heading">Admin Users</div><button class="btn-new" onclick="openModal('addAdmin')">+ Add Admin</button></div><div class="panel"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody><tr><td>Super Admin</td><td class="td-mono">admin@scc.ai</td><td><span class="loan-type-tag">Super Admin</span></td><td><span class="status-pill approved"><span class="status-dot"></span>Active</span></td><td><button class="btn-review" style="padding:.3rem .8rem;font-size:.75rem;" onclick="showToast('info','Cannot edit Super Admin','🔒')">Edit</button></td></tr><tr><td>Juan dela Cruz</td><td class="td-mono">juan@scc.ai</td><td><span class="loan-type-tag">Loan Officer</span></td><td><span class="status-pill approved"><span class="status-dot"></span>Active</span></td><td><button class="btn-review" style="padding:.3rem .8rem;font-size:.75rem;" onclick="openModal('addAdmin')">Edit</button></td></tr></tbody></table></div></div></div>
<div class="page" id="page-reports"><div class="page-header"><div class="page-heading">Reports</div><button class="btn-new" onclick="openModal('generateReport')">Generate Report</button></div><div class="panel" style="text-align:center;padding:3rem;color:var(--muted)"><div style="font-size:3rem;">📄</div><div style="margin-top:.8rem;">Download monthly, quarterly, and annual reports.</div></div></div>
<script>
  function filterApps(status, btnEl) {
    // Mark the clicked tab as active, un-mark the rest.
    document.querySelectorAll('#page-applications .filter-row .filter-btn')
      .forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    // Show only rows whose data-status matches, or all rows for 'all'.
    document.querySelectorAll('#page-applications tbody tr[data-status]')
      .forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
      });
  }
</script>