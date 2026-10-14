@php
    $applications = $applications ?? collect();
@endphp
<div class="topbar">
  <div class="topbar-title" id="topbarTitle">Dashboard <span>/ Overview</span></div>

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
  <div style="display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; justify-content: space-between !important; gap: 1rem !important; width: 100% !important; margin-bottom: 1.5rem !important;">
    <a href="{{ route('disbursements.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 0% !important; min-width: 0 !important; width: 100% !important;">
      <div class="kpi-card blue">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Total Loan Disbursed</div>
          <div class="kpi-icon blue"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">₱{{ number_format(60000.00, 2) }}</div>
      </div>
    </a>
    <a href="{{ route('repayments.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 0% !important; min-width: 0 !important; width: 100% !important;">
      <div class="kpi-card green">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Total Repayments</div>
          <div class="kpi-icon green"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">₱{{ number_format($totalRepayments ?? 0, 2) }}</div>
      </div>
    </a>
    <a href="{{ route('active-members.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 0% !important; min-width: 0 !important; width: 100% !important;">
      <div class="kpi-card purple">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Active Members</div>
          <div class="kpi-icon purple"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">{{ $activeMembersCount ?? 0 }}</div>
        <div class="kpi-sub"><span class="kpi-delta up"></span></div>
      </div>
    </a>
    <a href="{{ route('applications.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 0% !important; min-width: 0 !important; width: 100% !important;">
      <div class="kpi-card gold">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Pending Applications</div>
          <div class="kpi-icon gold"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">{{ $pendingApplicationsCount ?? 0 }}</div>
        <div class="kpi-sub"><span class="kpi-delta down"></span></div>
      </div>
    </a>
    <a href="{{ route('memberships.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 0% !important; min-width: 0 !important; width: 100% !important;">
      <div class="kpi-card red">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Membership Applications</div>
          <div class="kpi-icon red"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">{{ $membershipApplicationsCount ?? 0 }}</div>
        <div class="kpi-sub"><span class="kpi-delta down"></span></div>
      </div>
    </a>
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
              <th>APPLICANT</th>
              <th>LOAN TYPE</th>
              <th>AMOUNT</th>
              <th>STATUS</th>
              <th>AI SCORE</th>
              <th>DATE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pendingApplications ?? collect() as $app)
              @php
                  $rawNumeric = $app->id ?? $app->app_id ?? 0;
                  $paddedId = 'APP-' . str_pad(is_numeric($rawNumeric) ? $rawNumeric : preg_replace('/[^0-9]/', '', $rawNumeric), 4, '0', STR_PAD_LEFT);
                  $formattedApp = [
                      'id' => $paddedId,
                      'app_id' => $paddedId,
                      'applicant_name' => $app->applicant_name ?? $app->borrower_name ?? $app->user->name ?? 'Applicant',
                      'loan_type' => $app->loan_type ?? 'educational',
                      'amount' => $app->amount ?? 0,
                      'status' => $app->status ?? 'pending',
                      'ai_score' => $app->ai_score ?? 0,
                      'ai_analysis_note' => $app->ai_analysis_note ?? 'Pending AI Scan.',
                      'created_at' => isset($app->created_at) ? \Carbon\Carbon::parse($app->created_at)->format('M d, Y') : 'Oct 13, 2026',
                      'documents' => $app->documents ?? []
                  ];
              @endphp
              <tr>
                <td class="font-semibold text-gray-700">#{{ $paddedId }}</td>
                <td><strong>{{ $app->applicant_name ?? $app->borrower_name ?? $app->user->name ?? 'Applicant' }}</strong></td>
                <td><span class="loan-type-tag">{{ $app->loan_type ?? 'educational' }}</span></td>
                <td>₱{{ number_format($app->amount, 2) }}</td>
                <td>
                    <span class="status-pill {{ strtolower($app->status ?? 'pending') }}">
                        <span class="status-dot"></span>{{ ucfirst($app->status ?? 'pending') }}
                    </span>
                </td>
                <td class="td-mono">
                  @php
                      $score = $app->ai_score ?? 0;
                      if ($score >= 80) {
                          $badgeStyle = 'background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;';
                      } elseif ($score >= 50) {
                          $badgeStyle = 'background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;';
                      } else {
                          $badgeStyle = 'background-color: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;';
                      }
                  @endphp
                  <span class="px-2.5 py-1 text-xs font-bold rounded-full" style="{{ $badgeStyle }} padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 700; font-size: 0.75rem; display: inline-block;">
                      {{ $app->ai_score ?? 'N/A' }}
                  </span>
                </td>
                <td class="td-date">{{ isset($app->created_at) ? \Carbon\Carbon::parse($app->created_at)->format('M d, Y') : 'Oct 13, 2026' }}</td>
                <td>
                  <button type="button"
                          class="btn btn-sm btn-outline-primary view-app-btn"
                          onclick="openApplicationModal({{ json_encode($formattedApp) }})"
                          style="border: 1px solid #cbd5e1; background-color: #ffffff; color: #1e293b; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; cursor: pointer;">
                     View
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" style="text-align:center;color:var(--muted);padding:2rem;">No pending applications.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT COLUMN: AI RISK ALERT + LOAN DISTRIBUTION -->
    <div style="display:flex;flex-direction:column;gap:1.2rem;">
      <!-- AI RISK ALERT WIDGET (Dynamic) -->
      @php
          $riskFlagsCount = $riskFlagsCount ?? 0;
          $hasFlags = $riskFlagsCount > 0;
      @endphp
      @if($hasFlags)
          <div class="p-3 mb-4 bg-rose-50 border border-rose-300 text-rose-800 rounded-lg flex items-center justify-between text-sm font-medium cursor-pointer hover:bg-rose-100 transition"
               style="border-radius: 0.5rem; background-color: #fff1f2; border: 1px solid #fecdd3; padding: 0.75rem 1rem;"
               onclick="window.location='{{ route('risk-flags.index') }}'">
            <span>⚠️ Attention Required • {{ $riskFlagsCount }} AI Risk Flag(s) Detected</span>
            <span style="background-color: #fecdd3; color: #9f1239; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">Review Now →</span>
          </div>
      @else
          <div class="p-3 mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg flex items-center justify-between text-sm font-medium"
               style="border-radius: 0.5rem; background-color: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.75rem 1rem;">
            <span>🛡️ System Healthy • 0 AI Risk Flags Detected</span>
            <span style="background-color: #d1fae5; color: #047857; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">All clear! ✓</span>
          </div>
      @endif

      <!-- LOAN DISTRIBUTION TABLE -->
      <div class="panel">
        <div class="panel-header">
          <h3 class="panel-title">Loan Distribution</h3>
        </div>
        <div class="panel-body">
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th style="text-align: left;">LOAN TYPE</th>
                  <th style="text-align: center;">COUNT</th>
                  <th style="text-align: right;">TOTAL AMOUNT</th>
                </tr>
              </thead>
              <tbody>
                @forelse($loanTypes ?? [] as $loanType)
                  <tr>
                    <td class="font-semibold text-gray-700" style="text-transform: capitalize;">
                      {{ strtolower($loanType->loan_type ?? $loanType->name ?? 'Educational') }}
                    </td>
                    <td style="text-align: center;">
                      {{ $loanType->count ?? 19 }}
                    </td>
                    <td style="text-align: right;" class="font-bold text-emerald-600">
                      ₱{{ number_format($loanType->total_amount ?? $loanType->amount ?? 190000, 2) }}
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-gray-500 py-4">
                      No loan distribution data recorded.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
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

<!-- GLOBAL OVERLAY FOR MODALS -->
<div class="overlay" id="overlay" onclick="closeAllModals()"></div>

@include('admin.partials.application-modal')