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
        <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <div id="appDetailBody"></div>
</div>

<script>
/* ── Shared modal helpers ── */

window.closeAllModals = function () {
    var overlay = document.getElementById('overlay');
    if (overlay) { overlay.classList.remove('open'); overlay.style.display = 'none'; }

    document.querySelectorAll('.modal').forEach(function (m) {
        m.classList.remove('open');
        m.style.display = 'none';
    });

    document.querySelectorAll('.overlay, .modal-backdrop, .backdrop').forEach(function (b) {
        if (b && b.parentNode) { b.parentNode.removeChild(b); }
    });

    document.body.classList.remove('modal-open', 'overflow-hidden');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
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
    const loanType      = app.loan_type || 'educational';
    const amount        = app.amount || 0;
    const score         = app.ai_score ?? app.risk_score ?? app.score ?? null;
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
    let docStatusLabel  = 'Pending scan';
    if (docs.length === 0) docStatusLabel = 'No documents';
    else if (docStatus === 'completed') docStatusLabel = 'Verified';
    else if (docStatus === 'flagged' || docStatus === 'unverified') docStatusLabel = 'Unverified';
    const scanNoteDisplay = scanNote && scanNote !== 'null' && String(scanNote).trim() !== ''
        ? scanNote : 'Pending AI Scan. Click "⚡ AI Scan" below to evaluate attached documents.';
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
                '📄 <a href="/storage/' + cleanPath + '" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">' + label + '</a>' +
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

    bodyEl.innerHTML =
        row('Applicant', applicantName) +
        row('Loan Type', loanType) +
        row('Requested Amount', '<span style="font-family:monospace;">' + fmtMoney(amount) + '</span>') +
        row('Recommended Amount', '<span style="font-family:monospace;">' + fmtMoney(recommended) + '</span>') +
        row('Risk', '<span class="risk-badge risk-badge-' + riskLevel + '" style="padding:0.2rem 0.6rem;border-radius:9999px;font-size:0.78rem;font-weight:700;">' + riskLevel + '</span>') +
        row('Score', '<span id="modalAiScore" style="font-family:monospace;color:' + scoreC + ';font-weight:700;">' + (score != null ? score : 'N/A') + '</span>') +
        row('Term', termMonths + ' months') +
        '<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">' +
            '<span style="color:var(--muted);">Reasons</span>' +
            '<div style="text-align:right;">' + reasonsHtml + '</div>' +
        '</div>' +
        docsHtml +
        row('Document Status', '<span style="font-weight:600;">' + docStatusLabel + '</span>') +
        '<div style="margin-top:1rem;padding:.8rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;">' +
            '<div style="font-weight:600;font-size:0.8rem;text-transform:uppercase;letter-spacing:.05em;color:#1e293b;margin-bottom:.4rem;">AI Scan Analysis Note</div>' +
            '<div id="modalAiNotes" style="font-size:0.85rem;color:#1e293b;line-height:1.5;">' + scanNoteDisplay + '</div>' +
        '</div>' +
        row('Last Scan', scanTimeDisplay) +
        (showActions ? modalActionSection(appId, recommended) : '');
};

/* Action buttons + Approve Amount / reject reason form */
function modalActionSection(appId, recommendedAmount) {
    return '' +
        '<div style="display:flex;flex-direction:column;gap:0.5rem;width:100%;margin-top:1.2rem;">' +
            /* Row 1: Primary Decision */
            '<div style="display:flex;gap:0.5rem;width:100%;">' +
                '<button type="button" id="btn-reject" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:500;color:var(--red);border:1px solid var(--red);background:transparent;cursor:pointer;" onclick="openRejectModal(\'' + appId + '\', \'' + fmtMoney(recommendedAmount) + '\')">✗ Reject</button>' +
                '<button type="button" id="btn-approve" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:700;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;" onclick="openApproveModal(\'' + appId + '\', \'' + fmtMoney(recommendedAmount) + '\')">✓ Approve</button>' +
            '</div>' +
            /* Row 2: Tools & Cleanup */
            '<div style="display:flex;gap:0.5rem;width:100%;">' +
                '<button type="button" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:500;color:#4f46e5;border:2px solid #4f46e5;background:rgba(79,70,229,0.08);cursor:pointer;" onclick="triggerAiScan(\'' + appId + '\')">⚡ AI Scan</button>' +
                '<button type="button" style="flex:1;border-radius:8px;padding:.6rem 1rem;font-size:.875rem;font-weight:500;color:var(--red);border:1px solid var(--red);background:rgba(239,68,68,0.05);cursor:pointer;" onclick="deleteApp(\'' + appId + '\')">🗑 Delete Application</button>' +
            '</div>' +
        '</div>' +
        /* Approve modal (inline, hidden until needed) */
        '<div id="approveModal" style="display:none;margin-top:1rem;padding:1rem;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;">' +
            '<h4 style="margin:0 0 .5rem;font-size:.9rem;font-weight:600;">Approve Application</h4>' +
            '<div style="margin-bottom:.5rem;">' +
                '<label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.2rem;">Approved Amount</label>' +
                '<input type="number" id="approvedAmount" style="width:100%;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-family:monospace;font-size:.9rem;" value="' + (Number(String(recommendedAmount).replace(/[₱,]/g, '')) || 0) + '" />' +
            '</div>' +
            '<div style="margin-bottom:.5rem;">' +
                '<label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.2rem;">Reason <span style="color:#ef4444;">(required if above recommended)</span></label>' +
                '<textarea id="approveReason" placeholder="e.g. Exceptional payment history..." style="width:100%;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-size:.85rem;min-height:60px;"></textarea>' +
            '</div>' +
            '<div style="display:flex;gap:.5rem;">' +
                '<button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:600;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;" onclick="confirmApprove(\'' + appId + '\')">Confirm Approve</button>' +
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
    var reason = document.getElementById('approveReason').value.trim();

    /* Recommended amount comes from the modal that is currently open */
    var recommended = Number(window._modalRecommended) || 0;
    var approvedAmount = parseFloat(approvedAmountStr);

    if (approvedAmount > recommended && !reason) {
        showToast('error', 'A reason is required when approving above the recommended amount.', '❌');
        return;
    }

    if (!approvedAmount || approvedAmount <= 0) {
        showToast('error', 'Please enter a valid approved amount.', '❌');
        return;
    }

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
        body: JSON.stringify({ approved_amount: approvedAmount, reason: reason })
    })
        .then(function (res) {
            if (!res.ok) return res.json().then(function (err) { throw err; });
            return res.json();
        })
        .then(function (data) {
            showToast('success', 'Application Approved ✓ (₱' + Number(approvedAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')', '✅');
            closeAllModals();
            setTimeout(function () { location.reload(); }, 700);
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Failed to approve application.';
            showToast('error', msg, '❌');
        });
};

/* ── Confirm Reject ── */
window.confirmReject = function (appId) {
    var reason = document.getElementById('rejectReason').value.trim();

    if (!reason) {
        showToast('error', 'A rejection reason is required.', '❌');
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
            showToast('success', 'Application Rejected.', '✅');
            closeAllModals();
            setTimeout(function () { location.reload(); }, 700);
        })
        .catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Failed to reject application.';
            showToast('error', msg, '❌');
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
            showToast('error', 'Application #' + id + ' deleted', '🗑');
            closeAllModals();
            setTimeout(function () { location.reload(); }, 700);
        })
        .catch(function () { showToast('error', 'Failed to delete application #' + id + '.', '❌'); });
};

/* ── AI Scan ── */
window.triggerAiScan = function (id) {
    if (!id || id === 'undefined' || id === 'null') {
        if (typeof showToast === 'function') showToast('error', 'Invalid application ID for AI scan', '❌');
        return;
    }

    if (!confirm('Re-run AI credit score scan for application #' + id + '?')) return;

    var scanBtn = null;
    var buttons = document.querySelectorAll('#appDetailBody button');
    for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].textContent && buttons[i].textContent.includes('AI Scan')) { scanBtn = buttons[i]; break; }
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
                showToast('error', (data && data.message) ? data.message : 'AI Scan failed.', '❌');
                if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ AI Scan'; }
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
                var scoreCells = tableRow.querySelectorAll('td.td-mono');
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

            showToast('success', 'AI Scan Complete • Score: ' + (score != null ? score : 'N/A') + ' • Risk: ' + riskLevel, '⚡');

            if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ AI Scan'; }
        })
        .catch(function (err) {
            console.error('AI Scan error:', err);
            showToast('error', 'AI Scan failed: ' + (err.message || 'check server logs'), '❌');
            if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ AI Scan'; }
        });
};
</script>