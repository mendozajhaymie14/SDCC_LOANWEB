<!-- Shared Application Detail Modal
     Single source of truth for the application detail popup.
     Both the dashboard "Pending Loan Applications" table and the
     /applications page render View buttons that call the same
     openApplicationModal() JS function defined here, so the two
     views can never drift apart.
-->
<div class="modal"
     id="modal-applicationModal"
     onclick="event.stopPropagation()"
     style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">

    <!-- Risk badge styles (reusable) -->
    <style>
      .risk-badge-Low    { background: #dcfce7; border-color: #86efac; color: #166534; }
      .risk-badge-Medium { background: #fef3c7; border-color: #fde68a; color: #92400e; }
      .risk-badge-High   { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
      .risk-badge-none   { background: #f3f4f6; border-color: #d1d5db; color: #6b7280; }
    </style>

    <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div class="modal-title" id="appDetailTitle">#APP-0000 — Application Detail</div>
        <div class="modal-close" type="button" onclick="closeAllModals()"><i class="fa-solid fa-xmark nav-icon"></i></div>
    </div>
    <div id="appDetailBody"></div>
</div>

<script>
<?php
    $user = auth()->user();
    $perms = [
        'approve' => \App\Support\Permissions::has($user, \App\Support\Permissions::APPROVE_LOANS),
        'reject'  => \App\Support\Permissions::has($user, \App\Support\Permissions::REJECT_LOANS),
        'scan'    => \App\Support\Permissions::has($user, \App\Support\Permissions::VERIFY_DOCUMENTS),
        'delete'  => \App\Support\Permissions::has($user, \App\Support\Permissions::ENCODE_APPLICATIONS),
        'override' => \App\Support\Permissions::has($user, \App\Support\Permissions::OVERRIDE_CREDIT_LIMIT),
    ];
?>
/* Permission flags computed server-side so role checks cannot be bypassed
   client-side. Missing permission ⇒ button never rendered. */
window.__modalPermissions = {
    approve: <?php echo json_encode($perms['approve']); ?>,
    reject:  <?php echo json_encode($perms['reject']); ?>,
    scan:    <?php echo json_encode($perms['scan']); ?>,
    delete:  <?php echo json_encode($perms['delete']); ?>,
    override: <?php echo json_encode($perms['override']); ?>,
};

/* ── Shared modal helpers ── */

window.closeAllModals = function () {
    var overlay = document.getElementById('overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.style.display = 'none'; }

    document.querySelectorAll('.modal').forEach(function (m) {
        m.classList.remove('open');
        m.style.display = 'none';
    });
};

window.closeModal = function (modalId) { closeAllModals(); };

window.openModal = function (modalId) {
    closeAllModals();

    var targetModal = document.getElementById(modalId) || document.getElementById('modal-' + modalId);
    var targetOverlay = document.getElementById('overlay');

    if (targetOverlay) {
        targetOverlay.classList.add('open');
        targetOverlay.style.display = 'flex';
    }

    if (targetModal) {
        targetModal.classList.add('open');
        targetModal.style.display = 'block';
    }

    document.body.classList.add('modal-open');
};

/* Format number as PHP-style "₱1,234,567.89" */
function fmtMoney(v) {
    if (v == null || v === 'null' || v === '') return '₱0.00';
    const n = typeof v === 'number' ? v : parseFloat(v);
    if (isNaN(n)) return '₱0.00';
    return '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* Render a label/value row */
function row(label, value) {
    return '<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">' +
        '<span style="color:var(--muted);">' + label + '</span>' +
        '<span style="font-weight:600;">' + value + '</span>' +
        '</div>';
}

/* Build the shared modal body from a plain object */
window.renderApplicationModal = function (app) {
    /* Normalize required keys */
    const statusStr     = String(app.status || 'pending').toLowerCase();
    const statusLabel   = statusStr.charAt(0).toUpperCase() + statusStr.slice(1);
    const applicantName = app.applicant_name || app.borrower_name || app.applicant || app.name || 'Applicant';
    const loanType      = app.loan_type_label || app.loan_type || 'educational';
    const amount        = app.amount || 0;
    const score         = app.risk_score ?? app.ai_score ?? app.score ?? null;
    const riskLevel     = app.risk_level || 'None';
    const riskScore     = app.risk_score ?? app.ai_score ?? app.score ?? null;
    const recommended   = app.recommended_amount || 0;
    const termMonths    = app.term_months || 12;
    const risks         = Array.isArray(app.risk_factors) ? app.risk_factors :
                          (typeof app.risk_factors === 'string' && app.risk_factors ? JSON.parse(app.risk_factors) : []);
    const reasons       = Array.isArray(app.reasons) ? app.reasons :
                          (typeof app.reasons === 'string' && app.reasons ? JSON.parse(app.reasons) : []);
    const docs          = Array.isArray(app.documents) ? app.documents :
                          (typeof app.documents === 'string' && app.documents ? JSON.parse(app.documents) : []);
    const scanNote      = app.ai_analysis_note || app.ai_notes || app.ai_analysis_notes || null;
    const docStatus     = (app.document_processing_status || 'pending').toLowerCase();
    const lastScan      = app.last_scanned_at || app.last_scan_at || app.scanned_at || null;
    const pendingCount       = app.pending_loans_count ?? 0;
    const pendingMonthlyTotal = app.pending_loans_monthly_total ?? 0;
    let docStatusLabel  = 'Pending scan';
    if (docs.length === 0) docStatusLabel = 'No documents';
    else if (docStatus === 'completed') docStatusLabel = 'Verified';
    else if (docStatus === 'flagged' || docStatus === 'unverified') docStatusLabel = 'Unverified';
    const scanNoteDisplay = scanNote && scanNote !== 'null' && String(scanNote).trim() !== ''
        ? scanNote : 'No document check performed. Click "⚡ Document Check" below to evaluate attached documents.';
    const scanTimeDisplay = lastScan ? new Date(lastScan).toLocaleString() : 'Never';
    /* displayId is the "APP-0021" number shown to people; appId is the real database id used by the routes */
    const displayId     = String(app.app_id || app.id || '').replace(/^#?(?:APP-)?/i, '');
    const rawId         = String(app.id || '');
    const appId         = /^\d+$/.test(rawId) ? rawId : displayId;
    window._modalRecommended = Number(recommended) || 0;

    const titleEl = document.getElementById('appDetailTitle');
    if (titleEl) titleEl.textContent = '#APP-' + displayId + ' — ' + applicantName;

    const bodyEl = document.getElementById('appDetailBody');
    if (!bodyEl) return;

    /* Sparse table objects (window.apps on /applications, the dashboard
       $formattedApp rows, search results) carry display fields only — no
       monthly_available, available_credit_total or recommended_amount.
       The override panel and the credit rows need the canonical payload,
       so fetch it once and re-render. /data resolves both the numeric id
       and the APP-xxxx id. */
    if (app.monthly_available === undefined && (app.app_id || app.id)) {
        var dataId = app.app_id || app.id;
        var dataToken = String(Date.now());
        window._modalDataToken = dataToken;
        bodyEl.innerHTML = '<div style="padding:2rem 0;text-align:center;color:var(--muted);">Loading application details…</div>';
        fetch('/admin/applications/' + encodeURIComponent(dataId) + '/data', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
            .then(function (data) {
                if (window._modalDataToken !== dataToken) return;
                if (!data || !data.success) throw new Error((data && data.message) || 'Application not found');
                renderApplicationModal(data.application);
            })
            .catch(function (err) {
                if (window._modalDataToken !== dataToken) return;
                console.error('Load application failed:', err);
                bodyEl.innerHTML = '<div style="padding:1.5rem 0;text-align:center;color:var(--red);">Could not load this application (' + (err && err.message ? err.message : 'error') + '). Close and try again.</div>';
            });
        return;
    }

    /* Documents badges */
    let docsHtml = '';
    if (!docs || docs.length === 0) {
        docsHtml = row('Uploaded Documents', '<span style="color:var(--muted);font-style:italic;">No attached documents found.</span>');
    } else {
        var badges = docs.map(function (doc, i) {
            var docPath = typeof doc === 'string' ? doc : (doc && doc.path ? doc.path : '');
            if (!docPath) return '';
            var cleanPath = String(docPath).replace(/^\/?(?:storage\/)?/, '');
            var ext = (cleanPath.split('.').pop() || '').toUpperCase();
            var label = 'Document ' + (i + 1) + (ext ? ' (' + ext + ')' : '');
            return '<span style="display:inline-flex;align-items:center;gap:0.2rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;border-radius:6px;padding:0.35rem 0.65rem;font-size:0.75rem;margin:0.2rem;">' +
                '<i class="fa-solid fa-file nav-icon"></i> <a href="/storage/' + cleanPath + '" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">' + label + '</a>' +
                '</span>';
        }).join('');
        docsHtml = '<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">' +
            '<span style="color:var(--muted);">Uploaded Documents</span>' +
            '<div style="display:flex;flex-wrap:wrap;gap:0.3rem;">' + badges + '</div>' +
            '</div>';
    }

    /* Reasons list */
    const reasonsArr = (reasons.length ? reasons : (risks.length ? risks : []));
    let reasonsHtml = '';
    if (reasonsArr.length > 0) {
        reasonsHtml = reasonsArr.map(function (r) {
            var text = (r && typeof r === 'object') ? (r.reason || r.message || '') : r;
            if (!text) return '';
            return '<li style="margin-bottom:0.2rem;font-size:0.85rem;color:#1e293b;">' + text + '</li>';
        }).join('');
        reasonsHtml = '<ul style="margin:0;padding-left:1.2rem;font-size:0.85rem;line-height:1.6;">' + reasonsHtml + '</ul>';
    } else {
        reasonsHtml = '<span style="color:var(--muted);font-style:italic;">No risk reasons recorded.</span>';
    }

    /* Score colour */
    var scoreC = 'var(--muted)';
    if (score !== null && score !== undefined) {
        scoreC = score >= 80 ? 'var(--accent3)' : (score >= 50 ? 'var(--gold)' : 'var(--red)');
    }

    /* Approve/Reject buttons (only for pending/review) */
    const showActions = statusStr === 'pending' || statusStr === 'review';

    /* Credit limit display from the application data */
    const monthlyCapacity = app.monthly_capacity ?? null;
    const monthlyUsed = app.monthly_used ?? 0;
    const monthlyAvailable = app.monthly_available ?? null;
    const monthlyMax12 = app.monthly_max_12 ?? null;
    const totalCreditLimit = app.total_credit_limit ?? null;
    const availableCreditTotal = app.available_credit_total ?? null;
    const pendingLoansTotal = app.pending_loans_total ?? 0;

    // Check if monthly_capacity is a fallback (suggested) vs truly assessed
    const isCapacitySuggested = monthlyCapacity !== null && app.monthly_capacity_source === 'auto';

    /* Prefill the approved amount with the recommendation (fall back to the
       requested amount when the recommendation is 0). */
    const prefillAmount = recommended > 0 ? recommended : amount;
    /* Over-limit: requested above monthly available OR above total available.
       Drives the override UI at load time — computed server-side and echoed
       here so the client never has to recompute it from the request amount. */
    const overLimit = app.over_limit === true || app.over_limit === '1';
    const requestedAmount = app.requested_amount != null ? app.requested_amount : amount;

    const rowHtml = (label, value, extra = '') => `
        <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">${label}</span>
          <span style="font-weight:600;">${value} ${extra}</span>
        </div>`;

    const creditLimitHtml = `
        ${rowHtml('Monthly Used', '<span class="num">' + fmtMoney(monthlyUsed) + '</span>')}
        ${rowHtml('Monthly Available', monthlyAvailable !== null ? '<span class="num" style="color:var(--success);">' + fmtMoney(monthlyAvailable) + '</span>' : '<span style="color:var(--muted);font-style:italic;">Not assessed</span>')}
        ${monthlyCapacity !== null && monthlyAvailable !== null ? '<div style="padding:.2rem 0;font-size:.75rem;color:var(--muted);">This month: <span class="num">' + fmtMoney(monthlyUsed) + '</span> approved of <span class="num">' + fmtMoney(monthlyCapacity) + '</span> limit' + (isCapacitySuggested ? ' (suggested)' : '') + '</div>' : ''}
        ${rowHtml('Max for 12 months', monthlyMax12 !== null ? '<span class="num">' + fmtMoney(monthlyMax12) + '</span>' : '<span style="color:var(--muted);font-style:italic;">Not assessed</span>')}
        ${totalCreditLimit !== null ? '<div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.78rem;color:var(--muted);"><span>Total credit limit <span class="num">' + fmtMoney(totalCreditLimit) + '</span> · Available credit <span class="num">' + fmtMoney(availableCreditTotal ?? 0) + '</span></span></div>' : ''}
        ${pendingCount > 0 ? '<div style="padding:.4rem 0;font-size:.8rem;color:var(--muted);">Member has ' + pendingCount + ' other pending loan(s) totalling <span class="num">' + fmtMoney(pendingLoansTotal) + '</span>.</div>' : ''}
    `;

    bodyEl.innerHTML =
        row('Applicant', applicantName) +
        row('Loan Type', loanType) +
        row('Requested Amount', '<span class="num">' + fmtMoney(amount) + '</span>') +
        row('Recommended Amount', '<span class="num">' + fmtMoney(recommended) + '</span>') +
        row('Risk', '<span class="risk-badge risk-badge-' + riskLevel + '" style="padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.78rem;font-weight:700;">' + riskLevel + '</span>') +
        row('Score', '<span id="modalAiScore" class="num" style="color:' + scoreC + ';">' + (score != null ? score : 'N/A') + '</span>') +
        row('Term', termMonths + ' months') +
        creditLimitHtml +
        '<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">' +
            '<span style="color:var(--muted);">Reasons</span>' +
            '<div style="text-align:right;">' + reasonsHtml + '</div>' +
        '</div>' +
        docsHtml +
        row('Document Status', '<span style="font-weight:600;">' + docStatusLabel + '</span>') +
        '<div style="margin-top:1rem;padding:.8rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;">' +
            '<div style="font-weight:600;font-size:0.8rem;text-transform:uppercase;letter-spacing:.05em;color:#1e293b;margin-bottom:.4rem;">Document Check Analysis Note</div>' +
            '<div id="modalAiNotes" style="font-size:0.85rem;color:#1e293b;line-height:1.5;">' + scanNoteDisplay + '</div>' +
        '</div>' +
        row('Last Scan', scanTimeDisplay) +
        (showActions ? modalActionSection(appId, prefillAmount, monthlyAvailable, availableCreditTotal, overLimit, requestedAmount) : '');
};

/* Action buttons + Approve Amount / reject reason form */
function modalActionSection(appId, prefillAmount, monthlyAvailable, totalAvailable, overLimit, requestedAmount) {
    /* Permission flags computed server-side (see window.__modalPermissions).
       A role without the permission never sees the button. */
    const p = window.__modalPermissions || { approve: false, reject: false, scan: false, delete: false, override: false };
    if (! p.approve && ! p.reject && ! p.scan && ! p.delete) return '';

    const ma = monthlyAvailable === null || monthlyAvailable === undefined ? '' : monthlyAvailable;
    const ta = totalAvailable === null || totalAvailable === undefined ? '' : totalAvailable;
    const isOverLimit = overLimit === true || overLimit === '1' || overLimit === 1;

    /* Initial override UI at load is driven by the REQUESTED amount vs the
       available limits (the server-computed over_limit flag), NOT by the
       prefill. The recommendation is capped at available, so for an over-limit
       request it equals available and the old prefill-based check could never
       fire — that was the bug: staff never saw the override option. */
    const initialBox = isOverLimit && p.override;
    const initialNotice = isOverLimit && !p.override;
    const introText = isOverLimit
        ? 'This request is above the member\'s available credit this month. Approve up to ' + fmtMoney(prefillAmount) + ', or tick Override to approve a higher amount (reason required, audited).'
        : '';

    return '' +
        '<div style="display:flex;flex-direction:column;gap:0.5rem;width:100%;margin-top:1.2rem;">' +
            /* Row 1: Primary Decision */
            '<div style="display:flex;gap:0.5rem;width:100%;">' +
                (p.reject ? '<button type="button" id="btn-reject" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:500;color:var(--red);border:1px solid var(--red);background:transparent;cursor:pointer;" onclick="openRejectModal(\'' + appId + '\', \'' + fmtMoney(prefillAmount) + '\')"><i class="fa-solid fa-xmark nav-icon"></i> Reject</button>' : '') +
                (p.approve ? '<button type="button" id="btn-approve" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:700;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;" onclick="openApproveModal(\'' + appId + '\', \'' + fmtMoney(prefillAmount) + '\')"><i class="fa-solid fa-check nav-icon"></i> Approve</button>' : '') +
            '</div>' +
            /* Row 2: Tools & Cleanup */
            '<div style="display:flex;gap:0.5rem;width:100%;">' +
                (p.scan ? '<button type="button" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:500;color:#4f46e5;border:2px solid #4f46e5;background:rgba(79,70,229,0.08);cursor:pointer;" onclick="triggerAiScan(\'' + appId + '\')"><i class="fa-solid fa-bolt nav-icon"></i> Document Check</button>' : '') +
                (p.delete ? '<button type="button" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:500;color:var(--red);border:1px solid var(--red);background:rgba(239,68,68,0.05);cursor:pointer;" onclick="deleteApp(\'' + appId + '\')"><i class="fa-solid fa-trash-can nav-icon"></i> Delete Application</button>' : '') +
            '</div>' +
        '</div>' +
        /* Approve modal (inline, hidden until needed) */
        '<div id="approveModal" style="display:none;margin-top:1rem;padding:1rem;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;">' +
            '<h4 style="margin:0 0 .5rem;font-size:.9rem;font-weight:600;">Approve Application</h4>' +
            '<div style="margin-bottom:.5rem;">' +
                '<label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.2rem;">Approved Amount</label>' +
                '<input type="number" id="approvedAmount" step="0.01" min="0" style="width:100%;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-family:inherit;font-size:.9rem;" value="' + prefillAmount + '" ' +
                    'data-monthly-available="' + ma + '" data-total-available="' + ta + '" ' +
                    'data-over-limit="' + (isOverLimit ? '1' : '0') + '" ' +
                    'data-requested="' + requestedAmount + '" ' +
                    'data-recommended="' + prefillAmount + '" ' +
                    'oninput="toggleOverrideBox()" />' +
            '</div>' +
            (p.override ? '<div id="overrideBox" style="display:' + (initialBox ? 'block' : 'none') + ';margin-bottom:.5rem;padding:.5rem;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;">' +
                (isOverLimit ? '<div id="overrideIntro" style="font-size:.78rem;color:#92400e;margin-bottom:.4rem;">' + introText + '</div>' : '') +
                '<label style="display:flex;align-items:flex-start;gap:.4rem;cursor:pointer;font-size:.82rem;color:#92400e;">' +
                    '<input type="checkbox" id="overrideCreditLimit" style="margin-top:0.15rem;" onchange="onOverrideToggle()" />' +
                    '<span><strong>Override credit limit</strong> — I authorize approving this application above the member\'s available credit. This action is audited.</span>' +
                '</label>' +
                '<label id="overrideReasonLabel" style="display:none;font-size:.78rem;color:#92400e;margin-top:.3rem;margin-bottom:.1rem;">Override Reason <span style="color:#ef4444;">(required when checked)</span></label>' +
                '<textarea id="overrideReason" placeholder="e.g. Member has strong repayment history, co-signed by branch manager..." style="display:none;width:100%;padding:.35rem .5rem;border:1px solid #fde68a;border-radius:6px;font-size:.82rem;min-height:50px;"></textarea>' +
            '</div>' : '') +
            (initialNotice ? '<div id="overrideNotice" style="margin-bottom:.5rem;padding:.5rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;font-size:.82rem;color:#991b1b;">This request is above the member\'s available credit. Ask a manager with the override-credit-limit permission to approve it.</div>' : '') +
            '<div style="display:flex;gap:.5rem;">' +
                '<button type="button" id="btn-confirmApprove" ' + (initialNotice ? 'disabled ' : '') + 'style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:600;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;' + (initialNotice ? 'opacity:.5;cursor:not-allowed;' : '') + '" onclick="confirmApprove(\'' + appId + '\')">Confirm Approve</button>' +
                '<button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:500;color:var(--muted);border:1px solid var(--border);background:transparent;cursor:pointer;" onclick="closeApproveModal()">Cancel</button>' +
            '</div>' +
        '</div>' +
        /* Reject modal (inline, hidden until needed) */
        '<div id="rejectModal" style="display:none;margin-top:1rem;padding:1rem;border:1px solid #e2e8f0;border-radius:8px;background:#fef2f2;">' +
            '<h4 style="margin:0 0 .5rem;font-size:.9rem;font-weight:600;">Reject Application</h4>' +
            '<div style="margin-bottom:.5rem;">' +
                '<label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.2rem;">Rejection Reason <span style="color:#ef4444;">(required)</span></label>' +
                '<textarea id="rejectReason" placeholder="e.g. Insufficient documentation..." style="width:100%;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-size:.85rem;min-height:60px;"></textarea>' +
            '</div>' +
            '<div style="display:flex;gap:.5rem;">' +
                '<button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:600;background:var(--red);color:#fff;border:none;cursor:pointer;" onclick="confirmReject(\'' + appId + '\')">Confirm Reject</button>' +
                '<button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:500;color:var(--muted);border:1px solid var(--border);background:transparent;cursor:pointer;" onclick="closeRejectModal()">Cancel</button>' +
            '</div>' +
        '</div>';
}

/* The ONE place that decides the override UI. Pure: same inputs, same output,
   so the initial render and every keystroke agree, and agree with the server
   rule in AdminController::approve() — above Monthly Available OR above total
   available credit ⇒ override required. Returns:
     'box'    amount above an available limit and the user MAY override
     'notice' amount above an available limit but the user may NOT (ask a
              manager; Confirm stays disabled)
     ''       amount fits — no override UI
   Amounts arrive as either numbers or formatted strings ("₱10,000.00"),
   so commas/currency symbols are stripped before comparing. */
window.shouldShowOverride = function (amount, monthlyAvailable, totalAvailable, canOverride) {
    var amt = parseFloat(String(amount).replace(/[^\d.\-]/g, '')) || 0;
    var over = function (v) {
        if (v === null || v === undefined || v === '') return false;
        var n = parseFloat(v);
        return !isNaN(n) && amt > n;
    };
    if (!(over(monthlyAvailable) || over(totalAvailable))) return '';
    return canOverride ? 'box' : 'notice';
};

/* Keep the box, the ask-a-manager notice and the Confirm button in sync
   with the approved-amount input. The checkbox is NOT reset here — it is
   only touched by onOverrideToggle(), so unchecking restores the
   recommended amount and hides the reason box. */
window.toggleOverrideBox = function () {
    var box = document.getElementById('overrideBox');
    var notice = document.getElementById('overrideNotice');
    var btn = document.getElementById('btn-confirmApprove');
    var input = document.getElementById('approvedAmount');
    if (!input) return;
    var state = shouldShowOverride(
        input.value,
        input.dataset.monthlyAvailable,
        input.dataset.totalAvailable,
        (window.__modalPermissions || {}).override
    );
    if (box) box.style.display = state === 'box' ? 'block' : 'none';
    if (notice) notice.style.display = state === 'notice' ? 'block' : 'none';
    if (btn) btn.disabled = state === 'notice';
};

/* Override checkbox: checked ⇒ show the required reason box and prefill the
   approved amount with the REQUESTED amount (editable, above available).
   Unchecked ⇒ hide the reason box and restore the recommended amount. */
window.onOverrideToggle = function () {
    var checkbox = document.getElementById('overrideCreditLimit');
    var input = document.getElementById('approvedAmount');
    var reasonLabel = document.getElementById('overrideReasonLabel');
    var reasonBox = document.getElementById('overrideReason');
    if (!checkbox || !input) return;

    if (checkbox.checked) {
        if (reasonLabel) reasonLabel.style.display = 'block';
        if (reasonBox) { reasonBox.style.display = 'block'; }
        if (input.dataset.requested) {
            input.value = parseFloat(input.dataset.requested);
        }
    } else {
        if (reasonLabel) reasonLabel.style.display = 'none';
        if (reasonBox) { reasonBox.style.display = 'none'; reasonBox.value = ''; }
        if (input.dataset.recommended) {
            input.value = parseFloat(input.dataset.recommended);
        }
        toggleOverrideBox();
    }
};

/* ── Modal openers ── */

/* Direct: pass a full app object */
window.openApplicationModal = function (appDataOrId) {
    if (typeof closeAllDropdowns === 'function') closeAllDropdowns();

    var app = {}, appId = null;

    if (typeof appDataOrId === 'object' && appDataOrId !== null) {
        app = appDataOrId;
        appId = app.id || app.app_id || app.application_id;
    } else {
        appId = appDataOrId;
        var appsRef = (typeof window !== 'undefined' && window.apps) ? window.apps : null;
        if (appsRef && appsRef[appId]) {
            app = appsRef[appId];
        } else {
            var btn = window._lastModalBtn || (event ? event.currentTarget : null);
            if (btn && btn.dataset.documents) {
                try { app.documents = JSON.parse(btn.dataset.documents || '[]'); } catch (e) {}
            }
        }
    }

    renderApplicationModal(app);
    openModal('applicationModal');
};

/* Event-based wrapper used by View buttons that store data in dataset attrs */
window.openAppDetail = function (eventOrId) {
    if (typeof eventOrId === 'object' && eventOrId && eventOrId.currentTarget) {
        var btn = eventOrId.currentTarget;
        window._lastModalBtn = btn;
        var appId = btn.dataset.appId || '';
        var numericId = btn.dataset.numericId || String(appId).replace(/^#?APP-/i, '');

        if (window.apps && window.apps[numericId]) {
            renderApplicationModal(window.apps[numericId]);
            openModal('applicationModal');
            return;
        }

        /* Open at once with a loading state so the popup is never empty */
        var titleEl = document.getElementById('appDetailTitle');
        var bodyEl  = document.getElementById('appDetailBody');
        if (titleEl) titleEl.textContent = '#' + appId + ' — Loading…';
        if (bodyEl)  bodyEl.innerHTML = '<div style="padding:2rem 0;text-align:center;color:var(--muted);">Loading application details…</div>';
        openModal('applicationModal');

        var token = String(Date.now());
        window._appDetailToken = token;

        fetch('/admin/applications/' + numericId + '/data', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)); })
            .then(function (data) {
                if (window._appDetailToken !== token) return;
                if (!data || !data.success) throw new Error((data && data.message) || 'Application not found');
                renderApplicationModal(data.application);
                var m = document.getElementById('modal-applicationModal');
                if (m && m.style.display === 'none') openModal('applicationModal');
            })
            .catch(function (err) {
                if (window._appDetailToken !== token) return;
                console.error('Load application failed:', err);
                if (bodyEl) bodyEl.innerHTML = '<div style="padding:1.5rem 0;text-align:center;color:var(--red);">Could not load this application (' + (err && err.message ? err.message : 'error') + '). Close and try again.</div>';
            });
    } else {
        openApplicationModal(eventOrId);
    }
};

/* ── Approve / Reject modal helpers ── */

window.openApproveModal = function (appId, recommendedStr) {
    var m = document.getElementById('approveModal');
    if (m) m.style.display = 'block';
};

window.closeApproveModal = function () {
    var m = document.getElementById('approveModal');
    if (m) m.style.display = 'none';
};

window.openRejectModal = function (appId, recommendedStr) {
    var m = document.getElementById('rejectModal');
    if (m) m.style.display = 'block';
};

window.closeRejectModal = function () {
    var m = document.getElementById('rejectModal');
    if (m) m.style.display = 'none';
};

/* ── Confirm Approve ── */
window.confirmApprove = function (appId) {
    var approvedAmountStr = document.getElementById('approvedAmount').value;
    var overrideCheckbox = document.getElementById('overrideCreditLimit');
    var overrideReason = overrideCheckbox ? document.getElementById('overrideReason').value.trim() : '';

    /* Recommended amount comes from the modal that is currently open */
    var recommended = Number(window._modalRecommended) || 0;
    var approvedAmount = parseFloat(approvedAmountStr);

    if (overrideCheckbox && overrideCheckbox.checked && !overrideReason) {
        showToast('error', 'A reason is required when overriding the credit limit.', '<i class="fa-solid fa-xmark"></i>');
        return;
    }

    if (!approvedAmount || approvedAmount <= 0) {
        showToast('error', 'Please enter a valid approved amount.', '<i class="fa-solid fa-xmark"></i>');
        return;
    }

    /* Use override reason when the override checkbox is checked; otherwise use empty string. */
    var finalReason = (overrideCheckbox && overrideCheckbox.checked) ? overrideReason : '';

    var token = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken;

    /* Numeric ID (routes use ->whereNumber) */
    var numericId = appId;

    fetch('/applications/' + numericId + '/approve', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ approved_amount: approvedAmount, reason: finalReason })
    })
        .then(function (res) {
            if (!res.ok) return res.json().then(function (err) { throw err; });
            return res.json();
        })
        .then(function (data) {
            showToast('success', 'Application approved (₱' + Number(approvedAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')', '<i class="fa-solid fa-check"></i>');
            closeAllModals();
            setTimeout(function () { location.reload(); }, 700);
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Failed to approve application.';
            showToast('error', msg, '<i class="fa-solid fa-xmark"></i>');
        });
};

/* ── Confirm Reject ── */
window.confirmReject = function (appId) {
    var reason = document.getElementById('rejectReason').value.trim();

    if (!reason) {
        showToast('error', 'A rejection reason is required.', '<i class="fa-solid fa-xmark"></i>');
        return;
    }

    var token = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken;

    /* Numeric ID (routes use ->whereNumber) */
    var numericId = appId;

    fetch('/applications/' + numericId + '/reject', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ reason: reason })
    })
        .then(function (res) {
            if (!res.ok) return res.json().then(function (err) { throw err; });
            return res.json();
        })
        .then(function (data) {
            showToast('success', 'Application Rejected.', '<i class="fa-solid fa-check"></i>');
            closeAllModals();
            setTimeout(function () { location.reload(); }, 700);
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Failed to reject application.';
            showToast('error', msg, '<i class="fa-solid fa-xmark"></i>');
        });
};

/* ── Delete application ── */
window.deleteApp = function (id) {
    if (!confirm('Are you sure you want to permanently delete application #' + id + '?')) return;

    var token = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken;

    /* Numeric ID (routes use ->whereNumber) */
    var numericId = id;

    fetch('/applications/' + numericId, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
    })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
        .then(function () {
            showToast('success', 'Application #' + id + ' deleted', '<i class="fa-solid fa-trash-can"></i>');
            closeAllModals();
            setTimeout(function () { location.reload(); }, 700);
        })
        .catch(function () { showToast('error', 'Failed to delete application #' + id + '.', '<i class="fa-solid fa-xmark"></i>'); });
};

/* ── Document Check ── */
window.triggerAiScan = function (id) {
    if (!id || id === 'undefined' || id === 'null') {
        if (typeof showToast === 'function') showToast('error', 'Invalid application ID for AI scan', '<i class="fa-solid fa-xmark"></i>');
        return;
    }

    if (!confirm('Re-run AI credit score scan for application #' + id + '?')) return;

    var scanBtn = null;
    var buttons = document.querySelectorAll('#appDetailBody button');
    for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].textContent && buttons[i].textContent.includes('Document Check')) { scanBtn = buttons[i]; break; }
    }
    if (scanBtn) { scanBtn.disabled = true; scanBtn.innerHTML = 'Scanning...'; }

    var aiScoreEl = document.getElementById('modalAiScore');
    if (aiScoreEl) { aiScoreEl.textContent = 'Analyzing...'; aiScoreEl.style.color = 'var(--muted)'; }

    var token = document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken;

    var rawId = String(id).trim();
    var cleanNumeric = rawId.replace(/[^0-9]/g, '');

    function tryScan(targetId) {
        return fetch('/admin/applications/' + targetId + '/scan', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        });
    }

    tryScan(cleanNumeric || rawId)
        .then(function (res) {
            if (res.status === 404 && cleanNumeric && rawId !== cleanNumeric) return tryScan(rawId);
            return res;
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            if (!data || !data.success) {
                showToast('error', (data && data.message) ? data.message : 'Document Check failed.', '<i class="fa-solid fa-xmark"></i>');
                if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ Document Check'; }
                return;
            }

            var score = data.score;
            var riskLevel = data.risk_level || 'None';

            /* Update in-modal score */
            if (aiScoreEl) {
                aiScoreEl.textContent = score != null ? score : 'N/A';
                aiScoreEl.style.color = score != null ? scoreColor(score) : 'var(--muted)';
            }

            /* Update in-modal AI notes */
            var aiNotesEl = document.getElementById('modalAiNotes');
            if (aiNotesEl) {
                var notes = data.analysis_note || data.ai_analysis_notes || data.ai_analysis_note;
                if (notes && notes !== 'null' && String(notes).trim() !== '') {
                    aiNotesEl.textContent = notes;
                } else {
                    aiNotesEl.textContent = 'Documents not verified automatically; manual review required.';
                }
            }

            /* Update risk badge if present */
            var riskBadge = document.querySelector('#appDetailBody .risk-badge');
            if (riskBadge) {
                riskBadge.className = 'risk-badge risk-badge-' + riskLevel;
                riskBadge.textContent = riskLevel;
            }

            /* Update in-memory apps object so subsequent opens are fresh */
            if (window.apps && window.apps[cleanNumeric]) {
                window.apps[cleanNumeric].ai_score = score;
                window.apps[cleanNumeric].risk_score = score;
                window.apps[cleanNumeric].risk_level = riskLevel;
                if (data.analysis_note || data.ai_analysis_note) {
                    window.apps[cleanNumeric].ai_analysis_note = data.analysis_note || data.ai_analysis_note;
                }
            }

            /* Update table row score if visible */
            var tableRow = document.querySelector('tr[data-app-id="' + id + '"]');
            if (tableRow) {
                var scoreCells = tableRow.querySelectorAll('td.td-num');
                if (scoreCells.length > 0) {
                    var lastCell = scoreCells[scoreCells.length - 1];
                    lastCell.innerHTML = '<span style="font-weight:700;color:' + (score != null ? scoreColor(score) : 'var(--muted)') + '">' + (score != null ? score : 'N/A') + '</span>';
                }
            }

            /* Refresh modal with latest data from server so risk_level, risk_factors,
             * recommended_amount and other fields are synchronized after the scan. */
            fetch('/admin/applications/' + cleanNumeric + '/data')
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(function (data) {
                    if (data && data.success && data.application) {
                        renderApplicationModal(data.application);
                    }
                })
                .catch(function () {
                    /* ignore fetch errors – the scan updates are already visible */
                });

            showToast('success', 'Document Check Complete • Score: ' + (score != null ? score : 'N/A') + ' • Risk: ' + riskLevel, '<i class="fa-solid fa-bolt"></i>');

            if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ Document Check'; }
        })
        .catch(function (err) {
            console.error('Document Check error:', err);
            showToast('error', 'Document Check failed: ' + (err.message || 'check server logs'), '<i class="fa-solid fa-xmark"></i>');
            if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ Document Check'; }
        });
};
</script>