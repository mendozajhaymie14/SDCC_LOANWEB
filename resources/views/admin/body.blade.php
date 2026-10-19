@php
    $applications = $applications ?? collect();

    // Chart data: Monthly Disbursements vs Repayments (Phase 2)
    $monthlyDisbursements = $monthlyDisbursements ?? [0, 0, 0, 0, 0, 0];
    $monthlyRepayments = $monthlyRepayments ?? [0, 0, 0, 0, 0, 0];

    // AI Risk Profile Distribution (Phase 2)
    $riskDistribution = $riskDistribution ?? ['Low' => 0, 'Moderate' => 0, 'High' => 0];

    // Risk Flags (Phase 1)
    $riskFlagsCount = $riskFlagsCount ?? 0;
    $hasFlags = $riskFlagsCount > 0;
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

  <!-- 1. TOP: Dynamic AI Risk Warning Banner (Phase 1) -->
  @if($hasFlags)
  <div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <span style="color: #dc2626; font-weight: 600; font-size: 0.95rem;">⚠️ Attention Required • {{ $riskFlagsCount }} AI Risk Flag(s) Detected</span>
    <a href="{{ route('risk-flags.index') }}" style="background-color: #dc2626; color: #ffffff; padding: 0.4rem 0.85rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; transition: background 0.2s;">Review Now &rarr;</a>
  </div>
  @else
  <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
    <span style="color: #16a34a; font-weight: 600; font-size: 0.95rem;">🛡️ System Healthy • 0 AI Risk Flags Detected</span>
    <a href="{{ route('risk-flags.index') }}" style="background-color: #16a34a; color: #ffffff; padding: 0.4rem 0.85rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; transition: background 0.2s;">Review Now &rarr;</a>
  </div>
  @endif

  <!-- 2. Row 1: KPI Stat Cards -->
  <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.5rem; width: 100%;">
    <a href="{{ route('disbursements.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 calc(20% - 0.75rem); min-width: 170px;">
      <div class="kpi-card blue" style="background: #fff; padding: 1.1rem; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Total Loan Disbursed</div>
          <div class="kpi-icon blue"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">₱{{ number_format($totalDisbursed ?? 0, 2) }}</div>
      </div>
    </a>
    <a href="{{ route('repayments.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 calc(20% - 0.75rem); min-width: 170px;">
      <div class="kpi-card green" style="background: #fff; padding: 1.1rem; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Total Repayments</div>
          <div class="kpi-icon green"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">₱{{ number_format($totalRepayments ?? 0, 2) }}</div>
      </div>
    </a>
    <a href="{{ route('active-members.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 calc(20% - 0.75rem); min-width: 170px;">
      <div class="kpi-card purple" style="background: #fff; padding: 1.1rem; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Active Members</div>
          <div class="kpi-icon purple"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">{{ $activeMembersCount ?? 0 }}</div>
        <div class="kpi-sub"><span class="kpi-delta up"></span></div>
      </div>
    </a>
    <a href="{{ route('applications.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 calc(20% - 0.75rem); min-width: 170px;">
      <div class="kpi-card gold" style="background: #fff; padding: 1.1rem; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Loan Applications</div>
          <div class="kpi-icon gold"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">{{ $pendingApplicationsCount ?? 0 }}</div>
        <div class="kpi-sub"><span class="kpi-delta down"></span></div>
      </div>
    </a>
    <a href="{{ route('memberships.index') }}" style="text-decoration: none !important; color: inherit !important; flex: 1 1 calc(20% - 0.75rem); min-width: 170px;">
      <div class="kpi-card teal" style="background: #fff; padding: 1.1rem; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        <div class="kpi-top">
          <div class="kpi-label" style="color: #374151 !important;">Membership Applications</div>
          <div class="kpi-icon teal"></div>
        </div>
        <div class="kpi-val" style="color: #111827 !important; font-weight: 700;">{{ $membershipApplicationsCount ?? 0 }}</div>
      </div>
    </a>
  </div>

  <!-- 3. Row 2: Interactive Chart.js Grid (Phase 2) -->
  <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem; width: 100%;">

    <!-- LEFT: Monthly Disbursements vs Repayments Bar Chart -->
    <div style="flex: 1 1 calc(50% - 0.75rem); min-width: 320px; background: #fff; padding: 1.25rem; border-radius: 12px; border: 1px solid #eaecf0;">
      <div class="panel-head">
        <h2 class="panel-title">Monthly Disbursements vs Repayments</h2>
      </div>
      <div class="panel-body h-full flex flex-col">
        <div style="position: relative; height: 260px; width: 100%; overflow: hidden;">
          <canvas id="disbursementChart"></canvas>
        </div>
      </div>
    </div>

    <!-- RIGHT: AI Risk Profile Distribution Doughnut Chart -->
    <div style="flex: 1 1 calc(50% - 0.75rem); min-width: 320px; background: #fff; padding: 1.25rem; border-radius: 12px; border: 1px solid #eaecf0;">
      <div class="panel-head">
        <h2 class="panel-title">AI Risk Profile Distribution</h2>
      </div>
      <div class="panel-body h-full flex flex-col">
        <div style="position: relative; height: 260px; width: 100%; overflow: hidden;">
          <canvas id="riskChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- 4. Row 3: Pending Applications Table & Loan Distribution side-by-side -->
  <div class="body-grid h-full flex flex-col justify-between" style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.6rem;align-items:stretch;">

    <!-- LEFT: Urgent Pending Applications -->
    <div class="panel h-full flex flex-col justify-between">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <div>
          <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1.05rem;">Pending Loan Applications</div>
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
              <th>RECOMMENDED</th>
              <th>RISK</th>
              <th>STATUS</th>
              <th>AI SCORE</th>
              <th>DATE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pendingApplications ?? collect() as $app)
              @php
                  $formattedApp = [
                      'id' => $app->app_id,
                      'app_id' => $app->app_id,
                      'applicant_name' => $app->loanApplication?->fullName() ?? $app->applicant ?? 'Applicant',
                      'loan_type' => $app->loan_type ?? 'educational',
                      'amount' => $app->amount ?? 0,
                      'status' => $app->status ?? 'pending',
                      'ai_score' => $app->ai_score,
                      'risk_level' => $app->risk_level,
                      'risk_score' => $app->risk_score,
                      'recommended_amount' => $app->recommended_amount,
                      'ai_analysis_note' => $app->ai_analysis_note ?? 'Pending AI Scan.',
                      'created_at' => isset($app->created_at) ? \Carbon\Carbon::parse($app->created_at)->format('M d, Y') : 'Oct 13, 2026',
                      'documents' => $app->loanApplication?->documents ?? [],
                  ];
              @endphp
              <tr>
                <td class="font-semibold text-gray-700">#{{ $app->app_id }}</td>
                <td><strong>{{ $app->loanApplication?->user->name ?? $app->applicant ?? 'Applicant' }}</strong></td>
                <td><span class="loan-type-tag">{{ $app->loan_type ?? 'educational' }}</span></td>
                <td>₱{{ number_format($app->amount, 2) }}</td>
                <td>₱{{ number_format($app->recommended_amount ?? 0, 2) }}</td>
                <td class="td-mono">
                  <span class="status-pill {{ strtolower($app->risk_level ?? 'none') }}">
                    {{ ucfirst($app->risk_level ?? 'None') }}
                  </span>
                </td>
                <td>
                    <span class="status-pill {{ strtolower($app->status ?? 'pending') }}">
                        <span class="status-dot"></span>{{ ucfirst($app->status ?? 'pending') }}
                    </span>
                </td>
                <td class="td-mono">
                  @php
                    $score = $app->ai_score;
                    if ($score !== null && $score >= 80) {
                        $badgeStyle = 'background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;';
                    } elseif ($score !== null && $score >= 50) {
                        $badgeStyle = 'background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;';
                    } elseif ($score !== null) {
                        $badgeStyle = 'background-color: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3;';
                    } else {
                        $badgeStyle = 'background-color: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db;';
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
                <td colspan="10" style="text-align:center;color:var(--muted);padding:2rem;">No pending applications.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- RIGHT: Loan Distribution Table -->
    <div class="panel h-full flex flex-col justify-between">
      <div class="panel-header">
        <h3 class="panel-title">Loan Distribution</h3>
      </div>
      <div class="panel-body h-full flex flex-col">
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
                    {{ config('loan_matrix.loan_types.' . $loanType->loan_type, ucfirst($loanType->loan_type)) }}
                  </td>
                  <td style="text-align: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                      <div style="width: 80px; height: 8px; background: #e5e7eb; border-radius: 9999px; overflow: hidden;">
                        <div style="width: {{ round(($loanType->count / max(1, $loanTypes->sum('count'))) * 100) }}%; height: 100%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                      </div>
                      <span class="font-mono text-sm">{{ $loanType->count ?? 19 }}</span>
                    </div>
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
            <tfoot>
              <tr>
                <td style="text-align: left; font-weight: 600;">Total Exposure</td>
                <td style="text-align: center;"></td>
                <td style="text-align: right; font-weight: 600;">₱{{ number_format($totalDisbursed ?? 0, 2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- 5. Row 4: Recent Audit Trail Table (Phase 3) -->
  <div class="panel" style="margin: 32px 0;">
    <div class="panel-head" style="padding: 1.25rem 1.5rem;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.1rem; font-weight: 700; color: #101828; margin: 0;">Recent Audit Trail</h3>
        <a href="{{ url('/admin/audit-logs') }}" style="color: #2563eb; font-size: 0.85rem; font-weight: 600; text-decoration: none;">
          View All Audit Logs &rarr;
        </a>
      </div>
    </div>
    <div class="panel-body">
      <div class="table-wrap">
        <table class="min-w-full divide divide-gray-200">
          <thead>
            <tr>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b font-mono">Date</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">User</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Action</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Details</th>
            </tr>
          </thead>
          <tbody>
            @if($recentAuditLogs->isNotEmpty())
              @foreach($recentAuditLogs as $log)
                <tr class="hover:bg-gray-50 transition-colors {{ $loop->odd ? 'bg-white' : 'bg-gray-50' }}">
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, H:i') }}</td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->admin_name ?? 'System' }}</td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->action }}</td>
                  <td class="py-3 px-4 font-medium text-gray-600 text-sm truncate">{{ Str::limit($log->details ?? '', 60) }}</td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="4" class="py-6 text-center text-gray-400">No audit logs found.</td>
              </tr>
            @endif
          </tbody>
        </table>
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
            <th>Recommended</th>
            <th>Risk</th>
            <th>Status</th>
            <th>AI Score</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($applications as $app)
            @php
                $formattedApp = [
                    'id' => $app->app_id,
                    'app_id' => $app->app_id,
                    'applicant_name' => $app->loanApplication?->user->name ?? $app->applicant,
                    'borrower_name' => $app->loanApplication?->user->name ?? $app->applicant,
                    'applicant' => $app->loanApplication?->user->name ?? $app->applicant,
                    'loan_type' => $app->loan_type,
                    'amount' => $app->amount,
                    'status' => $app->status,
                    'ai_score' => $app->ai_score,
                    'risk_level' => $app->risk_level,
                    'risk_score' => $app->risk_score,
                    'recommended_amount' => $app->recommended_amount,
                    'ai_analysis_note' => $app->ai_analysis_note,
                    'created_at' => $app->created_at,
                    'documents' => $app->loanApplication?->documents ?? [],
                ];
            @endphp
            <tr data-status="{{ strtolower($app->status) }}">
              <td class="td-mono">#{{ $app->app_id }}</td>
              <td><strong>{{ $app->loanApplication?->user->name ?? $app->applicant }}</strong></td>
              <td><span class="loan-type-tag">{{ $app->loan_type }}</span></td>
              <td>₱{{ number_format($app->amount, 2) }}</td>
              <td>₱{{ number_format($app->recommended_amount ?? 0, 2) }}</td>
              <td class="td-mono">
                <span class="status-pill {{ strtolower($app->risk_level ?? 'none') }}">
                  {{ ucfirst($app->risk_level ?? 'None') }}
                </span>
              </td>
              <td>
                <span class="status-pill {{ strtolower($app->status) }}">
                  <span class="status-dot"></span>{{ ucfirst($app->status) }}
                </span>
              </td>
              <td class="td-mono">{{ $app->ai_score ?? 'N/A' }}</td>
              <td>{{ $app->created_at?->format('M d') ?? '—' }}</td>
              <td>
                <button class="btn-review" type="button" onclick="openApplicationModal({{ json_encode($formattedApp) }})">View</button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" style="text-align:center;color:var(--muted);padding:2rem;">No applications found.</td>
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
<div class="page" id="page-risk"><div class="page-header"><div class="page-heading">Risk Flags</div></div><div class="panel"><p style="color:var(--red);font-size:.9rem;margin-bottom:1rem;">⚠️ {{ $riskFlagsCount }} active risk flags require attention.</p><div class="feed"><a href="{{ route('risk-flags.index') }}" style="background:transparent;color:inherit;padding:0.2rem 0.5rem;border-radius:4px;text-decoration:none;font-weight:600;" onclick="event.stopPropagation()">View All Risk Flags</a></div></div></div>
<div class="page" id="page-settings"><div class="page-header"><div class="page-heading">Settings</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"><div class="panel"><div class="panel-title" style="margin-bottom:1.2rem;">General</div><div class="form-group"><label class="form-label">Organization Name</label><input class="form-input" value="San Dionisio Credit Cooperative"/></div><div class="form-group"><label class="form-label">Support Email</label><input class="form-input" value="support@scc.ai"/></div><div class="form-group"><label class="form-label">Default Currency</label><select class="form-select"><option>PHP (₱)</option><option>USD ($)</option></select></div><button class="btn-submit" onclick="showToast('success','Settings saved successfully','✅')">Save Settings</button></div><div class="panel"><div class="panel-title" style="margin-bottom:1.2rem;">Loan Defaults</div><div class="form-group"><label class="form-label">Max Personal Loan</label><input class="form-input" value="500000"/></div><div class="form-group"><label class="form-label">Default Interest Rate (%)</label><input class="form-input" value="1.5"/></div><div class="form-group"><label class="form-label">AI Credit Threshold</label><input class="form-input" value="600"/></div><button class="btn-submit" onclick="showToast('success','Loan defaults updated','✅')">Save Defaults</button></div></div></div>
<div class="page" id="page-reports"><div class="page-header"><div class="page-heading">Reports</div><button class="btn-new" onclick="openModal('generateReport')">Generate Report</button></div><div class="panel" style="text-align:center;padding:3rem;color:var(--muted)"><div style="font-size:3rem;">📄</div><div style="margin-top:.8rem;">Download monthly, quarterly, and annual reports.</div></div></div>

<!-- Chart.js CDN + Chart Rendering (Phase 2) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Monthly Disbursements vs Repayments Bar Chart
  const disbursementChartData = {
    labels: {!! json_encode($monthLabels) !!},
    datasets: [
      {
        label: 'Disbursements',
        data: {{ json_encode($monthlyDisbursements) }},
        backgroundColor: 'rgba(59, 130, 246, 0.5)',
        borderColor: 'rgba(59, 130, 246, 1)',
        borderWidth: 1
      },
      {
        label: 'Repayments',
        data: {{ json_encode($monthlyRepayments) }},
        backgroundColor: 'rgba(16, 185, 129, 0.5)',
        borderColor: 'rgba(16, 185, 129, 1)',
        borderWidth: 1
      }
    ]
  };

  const disbursementChartConfig = {
    type: 'bar',
    data: disbursementChartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        title: { display: false }
      },
      scales: {
        y: { beginAtZero: true }
      }
    }
  };

  // AI Risk Profile Distribution Doughnut Chart
  const riskChartData = {
    labels: ['Low Risk', 'Moderate Risk', 'High Risk'],
    datasets: [{
      data: [
        {{ json_encode($riskDistribution['Low']) }},
        {{ json_encode($riskDistribution['Moderate']) }},
        {{ json_encode($riskDistribution['High']) }}
      ],
      backgroundColor: [
        'rgba(34, 197, 94, 0.5)',
        'rgba(245, 158, 11, 0.5)',
        'rgba(239, 68, 68, 0.5)'
      ],
      borderColor: [
        'rgba(34, 197, 94, 1)',
        'rgba(245, 158, 11, 1)',
        'rgba(239, 68, 68, 1)'
      ],
      borderWidth: 1
    }]
  };

  const riskChartConfig = {
    type: 'doughnut',
    data: riskChartData,
    options: {
      layout: { padding: 15 },
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom' },
        title: { display: false }
      }
    }
  };

  // Initialize charts when DOM is ready
  document.addEventListener('DOMContentLoaded', function() {
    new Chart('disbursementChart', disbursementChartConfig);
    new Chart('riskChart', riskChartConfig);
  });
</script>