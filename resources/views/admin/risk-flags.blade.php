<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — AI Risk Flags</title>
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
    <div class="topbar-title" id="topbarTitle">Risk Flags <span>/ AI Risk Assessment</span></div>
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
          <h2 class="panel-title">AI Risk Flags</h2>
          <span class="records-count">Flagged Applications: {{ $riskFlagsCount }}</span>
        </div>
      </div>

      <!-- AI Risk Alert Banner -->
      @php
          $riskFlagsCount = $riskFlagsCount ?? 0;
          $hasFlags = $riskFlagsCount > 0;
      @endphp
      @if($hasFlags)
          <!-- Dynamic Risk Alert Banner -->
          <div class="p-3 mb-4 bg-rose-50 border border-rose-300 rounded-lg flex items-center justify-between"
               style="background-color: #fff1f2; border: 1px solid #fecdd3; padding: 0.75rem 1rem;">
            <div>
              <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1.05rem; text-transform:uppercase; letter-spacing:0.05em; color:#9f1239;">⚠️ Attention Required</div>
              <div style="font-size: 1rem; font-weight: 600; color:#9f1239;">{{ $riskFlagsCount }} AI Risk Flag(s) Detected</div>
              <div style="font-size:0.75rem; color:#6b7280; margin-top:0.2rem;">Pending applications with AI Score < 50 require immediate review.</div>
            </div>
            <a href="{{ route('risk-flags.index') }}" style="background:#fecdd3; color:#9f1239; padding:0.5rem 1rem; border-radius:0.5rem; font-weight:600; font-size:0.875rem; text-decoration:none; margin-top:0.2rem; display:inline-block;">
              Review All Flags
            </a>
          </div>
      @else
          <!-- System Healthy Banner -->
          <div class="p-3 mb-4 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between"
               style="background-color: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.75rem 1rem;">
            <span style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:1.05rem; color:#1e293b;">🛡️ System Healthy</span>
            <span style="background-color:#d1fae5; color:#059669; padding:0.25rem 0.5rem; border-radius:0.25rem; font-size:0.75rem; font-weight:600;">All clear! ✓</span>
          </div>
      @endif

      <!-- Flagged Applications Table -->
      @if($riskFlagsCount > 0)
          <div class="table-wrap">
            <table class="min-w-full divide divide-gray-200">
              <thead>
                <tr>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b font-mono">App ID</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Borrower Name</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">AI Score</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Flag Reason</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b border-l">Loan Type</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b border-l">Amount</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b border-l">Date</th>
                  <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b border-l">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($flaggedApplications as $app)
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-4 px-4 font-semibold text-gray-800 font-mono whitespace-break-all">{{ $app->app_id }}</td>
                    <td class="py-4 px-4 font-medium text-gray-800">{{ $app->applicant }}</td>
                    <td class="py-4 px-4">
                      <!-- Score badge -->
                      @php
                          $score = $app->ai_score ?? 0;
                          $isHighRisk = $score < 50;
                          $badgeBg = $isHighRisk ? '#ffe4e6' : '#fef3c7';
                          $badgeTxt = $isHighRisk ? '#9f1239' : '#92400e';
                          $riskLabel = $isHighRisk ? 'High Risk' : 'Moderate';
                      @endphp>
                      <span class="inline-block rounded-xs px-2.5 py-1 text-xs font-bold" style="background:{{ $badgeBg }}; color:{{ $badgeTxt }}; margin-right:0.4rem;">
                        {{$score}}/100 ({{ $riskLabel }})
                      </span>
                      <!-- Small numeric text below -->
                      <div style="margin-top:0.2rem; font-size:0.7rem; color:#6b7280;">{{ round($score * 100 / 550 + 300) ?? 'N/A' }}/100</div>
                    </td>
                    <td class="py-4 px-4">
                      <!-- Flag Reason badge -->
                      @php
                          $reason = 'Low Document Trust';
                          $reasonBg = '#fee2a8';
                          $reasonTxt = '#92400e';
                      @endphp>
                      <span class="inline-block rounded-xs px-2.5 py-1 text-xs font-bold" style="background:{{ $reasonBg }}; color:{{ $reasonTxt }};">
                        Flag: {{$reason}}
                      </span>
                    </td>
                    <td class="py-4 px-4"><span class="loan-type-tag">{{ $app->loan_type }}</span}</td>
                    <td class="py-4 px-4">₱{{ number_format($app->amount, 2) }}</td>
                    <td class="py-4 px-4">{{ \Carbon\Carbon::parse($app->created_at)->format('M d, Y') }}</td>
                    <td class="py-4 px-4">
                      <button type="button"
                              class="btn-view inline-flex items-center rounded-sm px-3 py-1 text-sm font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 transition-colors"
                              onclick="openRiskFlagDetail({{ json_encode($app) }})">
                        <i class="fas fa-eye mr-1.5"></i>View
                      </button>
                    </td>
                  </tr>
                @empty
                <tr>
                  <td colspan="8" class="py-6 text-center text-gray-400">
                    No flagged applications found — all applications are currently within acceptable risk parameters.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
      @endforelse
      @else
          <!-- No flags message (already shown in banner above) -->
      @endif
    </div>
  </div>
</div>

<!-- READ-ONLY RISK FLAG DETAIL MODAL -->
<div class="modal" id="modal-riskFlagDetail" onclick="event.stopPropagation()" style="width: 700px; max-width: 90vw;">
  <div class="modal-header">
    <div class="modal-title" id="riskFlagDetailTitle">Application Detail</div>
    <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
  </div>
  <div class="modal-body p-4 space-y-3" id="riskFlagDetailBody"></div>
</div>

<!-- GLOBAL OVERLAY FOR MODALS -->
<div class="overlay" id="overlay" onclick="closeAllModals()"></div>

<script>
  // ─── READ-ONLY RISK FLAG DETAIL MODAL ───
  function openRiskFlagDetail(app) {
    if (!app || typeof app !== 'object') return;

    const titleEl = document.getElementById('riskFlagDetailTitle');
    if (titleEl) titleEl.textContent = '#' + (app.app_id || app.id || '') + ' — ' + (app.applicant || 'N/A');

    const bodyEl = document.getElementById('riskFlagDetailBody');
    if (bodyEl) {
      // Score badge
      const score = app.ai_score ?? app.score ?? null;
      let scoreBadge = '';
      if (score !== null) {
        const isHighRisk = score < 50;
        const badgeBg = isHighRisk ? '#ffe4e6' : '#fef3c7';
        const badgeTxt = isHighRisk ? '#9f1239' : '#92400e';
        const riskLabel = isHighRisk ? 'High Risk' : 'Moderate';
        scoreBadge = '<span class="inline-block rounded-xs px-2.5 py-1 text-xs font-bold" style="background:' + badgeBg + '; color:' + badgeTxt + ';">' + score + '/100 (' + riskLabel + ')</span>';
      } else {
        scoreBadge = '<span style="color:var(--muted);">N/A</span>';
      }

      // Status badge
      const statusStr = (app.status || 'pending').toLowerCase();
      const statusLabel = statusStr.charAt(0).toUpperCase() + statusStr.slice(1);

      // Documents list
      let docsHtml = '';
      const docs = app.documents || app.loanApplication?.documents || [];
      if (!docs || docs.length === 0) {
        docsHtml = '<span style="color:var(--muted);font-style:italic;">No attached documents found.</span>';
      } else {
        docsHtml = docs.map(function(doc) {
          var fileName = typeof doc === 'string' ? doc.split('/').pop() : 'document';
          var docPath = typeof doc === 'string' ? doc : (doc.path || '#');
          return '<span style="display:inline-flex;align-items:center;gap:0.2rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;border-radius:6px;padding:0.35rem 0.65rem;font-size:0.75rem;margin:0.2rem;">📄 <a href="/storage/' + docPath + '" target="_blank" style="color:inherit;text-decoration:none;">' + fileName + '</a></span>';
        }).join('');
      }

      // AI analysis note (flag reason)
      const aiNotes = app.ai_analysis_note || app.ai_notes || app.loanApplication?.ai_analysis_notes || null;
      const aiNotesHtml = aiNotes && String(aiNotes).trim() !== '' && String(aiNotes) !== 'null'
        ? aiNotes
        : 'Flagged because AI Score is below 50, indicating high risk. Pending AI Scan. Click "AI Scan" to evaluate attached documents.';

      const row = function(label, value) {
        return '<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">\
          <span style="color:var(--muted);">' + label + '</span>\
          <span style="font-weight:600;">' + value + '</span>\
        </div>';
      };

      bodyEl.innerHTML = ''
        + row('Applicant', app.applicant_name || app.borrower_name || app.applicant || app.name || 'N/A')
        + row('Loan Type', app.loan_type || app.type || 'educational')
        + row('Amount', '<span style="font-family:\'DM Sans\',sans-serif;">₱' + (parseFloat(app.amount || 0) || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>')
        + row('AI Score', scoreBadge)
        + row('Date', app.created_at || app.date || '—')
        + row('Status', '<span class="status-pill ' + statusStr + '"><span class="status-dot"></span>' + statusLabel + '</span>')
        + row('Uploaded Documents', '<div style="display:flex;flex-wrap:wrap;gap:0.3rem;">' + docsHtml + '</div>')
        + '<div style="margin-top: 1rem; padding: 0.8rem; background-color: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; border-radius: 8px;">\
          <div style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #1e293b; margin-bottom: 0.4rem;">AI SCAN ANALYSIS NOTE</div>\
          <div style="font-size: 0.85rem; color: #1e293b; line-height: 1.5;">' + aiNotesHtml + '</div>\
        </div>';
    }

    openModal('riskFlagDetail');
  }
</script>

</body>
</html>