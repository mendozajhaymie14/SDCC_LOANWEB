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
  <!-- EXECUTIVE KPI ROW (5 cards) -->
  <div class="kpi-grid">
    <div class="kpi-card blue" onclick="switchPage('disbursements', null)" data-tip="View disbursements">
      <div class="kpi-top"><div class="kpi-label">Total Loan Disbursed</div><div class="kpi-icon blue"></div></div>
      <div class="kpi-val" id="totalDisbursed">₱{{ number_format($totalDisbursed ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card purple" onclick="window.location.href='{{ route('active-members.index') }}'" data-tip="View active members" style="cursor:pointer;">
      <div class="kpi-top">
        <div class="kpi-label">Active Members</div>
        <div class="kpi-icon purple"></div>
      </div>
      <div class="kpi-val">{{ $activeBorrowersCount ?? 0 }}</div>
      <div class="kpi-sub"><span class="kpi-delta up"></span></div>
    </div>
    <div class="kpi-card green" onclick="switchPage('repayments', null)" data-tip="View repayments">
      <div class="kpi-top"><div class="kpi-label">Repayment Rate</div><div class="kpi-icon green"></div></div>
      <div class="kpi-val">{{ $repaymentRate ?? 0 }}%</div>
      <div class="kpi-sub"><span class="kpi-delta up"></span></div>
    </div>
    <div class="kpi-card gold" onclick="window.location.href='{{ route('applications.index') }}'" data-tip="Review pending" style="cursor:pointer;">
      <div class="kpi-top"><div class="kpi-label">Pending Applications</div><div class="kpi-icon gold"></div></div>
      <div class="kpi-val">{{ $pendingApplicationsCount ?? 0 }}</div>
      <div class="kpi-sub"><span class="kpi-delta down"></span></div>
    </div>
    <div class="kpi-card red" onclick="switchPage('memberships', null)" data-tip="View membership applications" style="cursor:pointer;">
      <div class="kpi-top"><div class="kpi-label">Membership Applications</div><div class="kpi-icon red"></div></div>
      <div class="kpi-val">{{ $membershipApplicationsCount ?? 0 }}</div>
      <div class="kpi-sub"><span class="kpi-delta down"></span></div>
    </div>
  </div>

  <!-- OPERATIONAL WIDGETS ROW -->
  <div class="body-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.6rem;align-items:stretch;">
    <!-- LEFT: URGENT PENDING APPLICATIONS -->
    <div class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <div>
          <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1.05rem;">Pending Applications</div>
          <a href="{{ route('applications.index') }}" style="font-size:0.8rem;color:var(--accent);cursor:pointer;margin-top:0.2rem;display:inline-block;text-decoration:none;">View All →</a>
        </div>
        <span class="badge badge-warning">{{ $pendingApplicationsCount ?? 0 }} Pending</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>BORROWER</th>
              <th>TYPE</th>
              <th>AMOUNT</th>
              <th>AI SCORE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pendingApplications ?? collect() as $app)
              <tr>
                <td class="font-semibold text-gray-700">#APP-{{ str_pad($app->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td><strong>{{ $app->user?->full_name ?? $app->user?->name ?? '—' }}</strong></td>
                <td><span class="loan-type-tag">{{ $app->loan_type }}</span></td>
                <td>₱{{ number_format($app->amount, 2) }}</td>
                <td class="td-mono">
                  @if($app->ai_score)
                    <span class="badge {{ $app->ai_score >= 70 ? 'badge-success' : ($app->ai_score >= 51 ? 'badge-warning' : 'badge-danger') }}">
                      {{ $app->ai_score }}
                    </span>
                  @else
                    <span class="badge badge-muted">N/A</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('admin.applications.show', $app->id) }}" class="px-3 py-1 text-xs font-semibold text-indigo-600 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">View</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No pending applications.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT COLUMN: AI RISK ALERT + LIVE ACTIVITY -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">
      <!-- AI RISK ALERT WIDGET -->
      <div class="panel risk-alert-panel">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
          <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;display:inline-block;"></span>
          <span style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1rem;">AI Risk Alert</span>
          <span class="badge badge-danger">{{ $riskFlaggedCount ?? 0 }} Flags</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
          @if(($lowScoreCount ?? 0) > 0)
            <div class="risk-item">
              <div class="risk-icon risk-low">📉</div>
              <div class="risk-content">
                <div class="risk-title">Low AI Credit Scores</div>
                <div class="risk-desc">{{ $lowScoreCount }} loan(s) with AI score < 51</div>
              </div>
              <button class="btn-action btn-view" onclick="switchPage('risk', null)" title="Review">Review</button>
            </div>
          @endif
          @if(($overdueCount ?? 0) > 0)
            <div class="risk-item">
              <div class="risk-icon risk-overdue">⏰</div>
              <div class="risk-content">
                <div class="risk-title">Overdue Payments</div>
                <div class="risk-desc">{{ $overdueCount }} approved loan(s) past due date</div>
              </div>
              <button class="btn-action btn-view" onclick="switchPage('repayments', null)" title="Review">Review</button>
            </div>
          @endif
          @if(($riskFlaggedCount ?? 0) == 0)
            <div style="color:var(--muted2);font-size:0.85rem;text-align:center;padding:1rem;">No risk flags detected. All clear! ✓</div>
          @endif
        </div>
      </div>

      <!-- LIVE ACTIVITY FEED -->
      <div class="panel">
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
          <span style="width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;"></span>
          <span style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1rem;">Live Activity</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.8rem;">
          @forelse($notifications ?? [] as $n)
            <div style="display:flex;gap:0.8rem;align-items:flex-start;font-size:0.85rem;cursor:pointer;" onclick="openAppDetail({{ $n->loan_application_id ?? 'null' }})">
              <span style="width:8px;height:8px;border-radius:50%;background:{{ $n->type === 'approved' ? '#10b981' : ($n->type === 'rejected' ? '#ef4444' : '#f59e0b') }};margin-top:5px;flex-shrink:0;"></span>
              <div>
                <div style="font-weight:600;">{{ $n->message }}</div>
                <div style="color:var(--muted2);font-size:0.75rem;">{{ $n->applicant ?? $n->user?->full_name ?? '—' }} · #{{ $n->loan_application_id ?? $n->app_id ?? '—' }}</div>
                <div style="color:var(--muted2);font-size:0.7rem;">{{ $n->created_at?->diffForHumans() }}</div>
              </div>
            </div>
          @empty
            <div style="color:var(--muted2);font-size:0.85rem;text-align:center;padding:1rem;">No recent activity.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <!-- LOAN PORTFOLIO DISTRIBUTION + RECENT COLLECTION ACTIVITY (2-column) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
    <!-- Left Card: Loan Portfolio Distribution -->
    <div class="lg:col-span-7 bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h3 class="font-bold text-gray-800 text-base mb-4">Loan Portfolio Distribution</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-400 border-b border-gray-100 pb-2">
                        <th class="py-2">Loan Type</th>
                        <th class="py-2 text-center">Count</th>
                        <th class="py-2 text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($loanTypes ?? [] as $type)
                        <tr>
                            <td class="py-3">
                                <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 capitalize">
                                    {{ $type->loan_type ?? 'Educational' }}
                                </span>
                            </td>
                            <td class="py-3 text-center text-gray-700 font-semibold">{{ $type->count ?? 0 }}</td>
                            <td class="py-3 text-right text-gray-900 font-bold">₱{{ number_format($type->total_amount ?? 0, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-6 text-center text-gray-400 text-sm">No loan portfolio data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Card: Recent Collection Activity -->
    <div class="lg:col-span-5 bg-white rounded-xl border border-gray-200 p-6 shadow-sm flex flex-col justify-between">
        <div>
            <h3 class="font-bold text-gray-800 text-base mb-4">Recent Collection Activity</h3>
            <div class="space-y-3">
                @forelse($recentRepayments ?? [] as $payment)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $payment->user->name ?? 'Borrower' }}</p>
                            <p class="text-xs text-gray-400">{{ $payment->created_at ? $payment->created_at->diffForHumans() : 'Recently' }}</p>
                        </div>
                        <span class="text-sm font-bold text-emerald-600">+₱{{ number_format($payment->amount ?? 0, 2) }}</span>
                    </div>
                @empty
                    <div class="py-10 text-center text-gray-400 text-sm">
                        No recent repayments recorded.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
  </div>
</div>

<div class="page" id="page-applications">
  <div class="page-header">
    <div class="page-heading">All Applications</div>
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
            <tr data-status="{{ strtolower($app->status) }}">
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
                <button class="btn-review" type="button" onclick="openAppDetail('{{ $app->app_id }}')">View</button>
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
<div class="page" id="page-settings"><div class="page-header"><div class="page-heading">Settings</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="panel"><div class="panel-title" style="margin-bottom:1.2rem;">General</div><div class="form-group"><label class="form-label">Organization Name</label><input class="form-input" value="San Dionisio Credit Cooperative"/></div><div class="form-group"><label class="form-label">Support Email</label><input class="form-input" value="support@scc.ai"/></div><div class="form-group"><label class="form-label">Default Currency</label><select class="form-select"><option>PHP (₱)</option><option>USD ($)</option></select></div><button class="btn-submit" onclick="showToast('success','Settings saved successfully','✅')">Save Settings</button></div><div class="panel"><div class="panel-title" style="margin-bottom:1.2rem;">Loan Defaults</div><div class="form-group"><label class="form-label">Max Personal Loan</label><input class="form-input" value="500000"/></div><div class="form-group"><label class="form-label">Default Interest Rate (%)</label><input class="form-input" value="1.5"/></div><div class="form-group"><label class="form-label">AI Credit Threshold</label><input class="form-input" value="600"/></div><button class="btn-submit" onclick="showToast('success','Loan defaults updated','✅')">Save Defaults</button></div></div></div>
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