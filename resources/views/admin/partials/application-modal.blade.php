<!-- Application Detail Modal Component -->
<div class="modal" 
     id="modal-applicationModal" 
     onclick="event.stopPropagation()" 
     style="position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important;">
    <div class="modal-header">
        <div class="modal-title" id="appDetailTitle">#APP-0000 — Application Detail</div>
        <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <div id="appDetailBody"></div>
</div>

<script>
  window.closeAllModals = function() {
    document.querySelectorAll('.modal').forEach(m => {
      m.classList.remove('open', 'show');
      m.setAttribute('style', 'display: none !important;');
    });

    document.querySelectorAll('.overlay').forEach(o => {
      o.classList.remove('open', 'show');
      o.setAttribute('style', 'display: none !important;');
    });

    document.querySelectorAll('.modal-backdrop, .backdrop, [class*="backdrop"]').forEach(b => {
      if (b && b.parentNode) {
        b.parentNode.removeChild(b);
      }
    });

    document.body.classList.remove('modal-open', 'overflow-hidden');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  };

  window.closeModal = function(modalId) {
    closeAllModals();
  };

  window.openModal = function(modalId) {
    closeAllModals();

    const targetModal = document.getElementById(modalId) || document.getElementById('modal-' + modalId);
    const targetOverlay = document.getElementById('overlay') || document.querySelector('.overlay');

    if (targetOverlay) {
      targetOverlay.classList.add('open');
      targetOverlay.setAttribute('style', 'display: flex !important;');
    }

    if (targetModal) {
      targetModal.classList.add('open');
      targetModal.setAttribute('style', 'position: fixed !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; margin: 0 !important; z-index: 9999 !important; display: block !important;');
    }

    document.body.classList.add('modal-open');
  };

  window.openApplicationModal = function(appDataOrId) {
    if (typeof closeAllDropdowns === 'function') {
      closeAllDropdowns();
    }

    let app = {}, appId = null, docs = [], aiNotes = null;

    if (typeof appDataOrId === 'object' && appDataOrId !== null) {
      app = appDataOrId;
      appId = app.id || app.app_id || app.application_id;
      docs = app.documents || [];
      aiNotes = app.ai_analysis_note || app.ai_notes || null;
    } else {
      appId = appDataOrId;
      const appsRef = (typeof window !== 'undefined' && window.apps) ? window.apps : (typeof apps !== 'undefined' ? apps : null);
      
      if (appsRef && appsRef[appId]) {
        app = appsRef[appId];
        docs = app.documents || [];
        aiNotes = app.ai_notes || app.ai_analysis_note || null;
      } else {
        const btn = event?.currentTarget;
        if (btn) {
          try { docs = JSON.parse(btn.dataset.documents || '[]'); } catch (e) { docs = []; }
          aiNotes = btn.dataset.aiNotes || null;
        }
      }
    }

    const applicantName = app.applicant_name || app.borrower_name || app.applicant || app.name || 'Applicant';
    const loanType = app.loan_type || app.type || 'educational';
    const rawAmount = app.amount || 0;
    const amountStr = typeof rawAmount === 'number' ? '₱' + rawAmount.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : rawAmount;
    const rawScore = app.ai_score ?? app.score ?? null;
    const statusStr = (app.status || 'pending').toLowerCase();
    const statusLabel = statusStr.charAt(0).toUpperCase() + statusStr.slice(1);
    const dateStr = app.created_at || app.date || 'Oct 13, 2026';

    const scanTargetId = String(app.app_id || appId || '').trim();
    const cleanDisplayId = String(appId || app.app_id || app.id || '0000').replace(/^(#?APP-?)+/i, '');

    const titleEl = document.getElementById('appDetailTitle');
    if (titleEl) titleEl.textContent = '#APP-' + cleanDisplayId + ' — ' + applicantName;

    const bodyEl = document.getElementById('appDetailBody');
    if (bodyEl) {
      const row = (label, value) => `
        <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">${label}</span>
          <span style="font-weight:600;">${value}</span>
        </div>`;

      let docsHtml = '';
      if (!docs || docs.length === 0) {
        docsHtml = `<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">Uploaded Documents</span>
          <span style="color:var(--muted);font-style:italic;">No attached documents found.</span>
        </div>`;
      } else {
        const badges = docs.map(doc => {
          const fileName = typeof doc === 'string' ? doc.split('/').pop() : 'document';
          const docPath = typeof doc === 'string' ? doc : (doc.path || '#');
          return `<span style="display:inline-flex;align-items:center;gap:0.2rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;border-radius:6px;padding:0.35rem 0.65rem;font-size:0.75rem;margin:0.2rem;">
            📄 <a href="/storage/${docPath}" target="_blank" style="color:inherit;text-decoration:none;">${fileName}</a>
          </span>`;
        }).join('');
        docsHtml = `<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">Uploaded Documents</span>
          <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">${badges}</div>
        </div>`;
      }

      let scoreC = 'var(--muted)';
      if (rawScore !== null) {
        scoreC = rawScore >= 80 ? 'var(--accent3)' : (rawScore >= 50 ? 'var(--gold)' : 'var(--red)');
      }

      bodyEl.innerHTML = `
        ${row('Applicant', applicantName)}
        ${row('Loan Type', loanType)}
        ${row('Amount', `<span style="font-family:'DM Sans',sans-serif;">${amountStr}</span>`)}
        ${row('AI Score', `<span id="modalAiScore" style="font-family:'DM Sans',sans-serif;color:${scoreC};font-weight:700;">${rawScore ?? 'N/A'}</span>`)}
        ${row('Date', dateStr)}
        ${row('Status', `<span class="status-pill ${statusStr}"><span class="status-dot"></span>${statusLabel}</span>`)}
        ${docsHtml}

        <div id="aiScanNoteSection" style="margin-top: 1rem; padding: 0.8rem; background-color: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; border-radius: 8px;">
          <div style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #1e293b; margin-bottom: 0.4rem;">AI Scan Analysis Note</div>
          <div id="modalAiNotes" style="font-size: 0.85rem; color: #1e293b; line-height: 1.5;">
            ${aiNotes && aiNotes !== 'null' && String(aiNotes).trim() !== '' ? aiNotes : 'Pending AI Scan. Click "⚡ AI Scan" below to evaluate attached documents.'}
          </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 0.5rem; width: 100%; margin-top: 1.2rem;">
          ${(statusStr === 'pending' || statusStr === 'review') ? `
            <div style="display: flex; gap: 0.5rem; width: 100%;">
              <button type="button" style="flex: 1; width: 100%; border-radius: 8px; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 500; color: var(--red); border: 1px solid var(--red); background: transparent; cursor:pointer;" onclick="rejectApp('${scanTargetId}')">✗ Reject</button>
              <button type="button" style="flex: 1; width: 100%; border-radius: 8px; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 700; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #ffffff; border: none; cursor:pointer;" onclick="approveApp('${scanTargetId}')">✓ Approve</button>
            </div>` : ''
          }
          <div style="display: flex; gap: 0.5rem; width: 100%;">
            <button type="button" style="flex: 1; width: 100%; border-radius: 8px; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 500; color: #4f46e5; border: 2px solid #4f46e5; background: rgba(79, 70, 229, 0.08); cursor:pointer;" onclick="triggerAiScan('${scanTargetId}')">⚡ AI Scan</button>
            <button type="button" style="flex: 1; width: 100%; border-radius: 8px; padding: 0.6rem 1rem; font-size: 0.875rem; font-weight: 500; color: var(--red); border: 1px solid var(--red); background: rgba(239, 68, 68, 0.05); cursor:pointer;" onclick="deleteApp('${scanTargetId}')">🗑 Delete Application</button>
          </div>
        </div>
      `;
    }

    openModal('applicationModal');
  };

  window.openAppDetail = function(eventOrId) {
    if (typeof eventOrId === 'object' && eventOrId.currentTarget) {
      const btn = eventOrId.currentTarget;
      const appId = btn.dataset.appId;
      openApplicationModal(appId);
    } else {
      openApplicationModal(eventOrId);
    }
  };

  window.triggerAiScan = function(id) {
    if (!id || id === 'undefined' || id === 'null') {
      if (typeof showToast === 'function') showToast('error', 'Invalid application ID for AI scan', '❌');
      return;
    }

    if (!confirm('Re-run AI credit score scan for application #' + id + '?\nThis may take a moment to re-evaluate documents.')) {
      return;
    }

    const scanBtn = Array.from(document.querySelectorAll('#appDetailBody button'))
      .find(b => b.textContent.trim().includes('AI Scan'));
    if (scanBtn) {
      scanBtn.disabled = true;
      scanBtn.innerHTML = 'Scanning...';
    }

    const aiScoreEl = document.getElementById('modalAiScore');
    if (aiScoreEl) {
      aiScoreEl.textContent = 'Analyzing...';
      aiScoreEl.style.color = 'var(--muted)';
    }

    let token = document.querySelector('meta[name="csrf-token"]')?.content ||
                (typeof window !== 'undefined' && window.csrfToken ? window.csrfToken : '') ||
                (typeof csrfToken !== 'undefined' ? csrfToken : '');

    const rawId = String(id).trim();
    const cleanNumeric = rawId.replace(/[^0-9]/g, '');
    const paddedId = cleanNumeric ? 'APP-' + cleanNumeric.padStart(4, '0') : rawId;

    const tryScan = (targetId) => {
      return fetch(`/admin/applications/${targetId}/scan`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
    };

    tryScan(paddedId)
      .then(res => {
        if (res.status === 404 && paddedId !== rawId) {
          return tryScan(rawId);
        }
        if (res.status === 404 && cleanNumeric) {
          return tryScan(cleanNumeric);
        }
        return res;
      })
      .then(res => {
        if (!res.ok) throw new Error('HTTP status ' + res.status);
        return res.json();
      })
      .then(data => {
        if (!data || !data.success) {
          if (typeof showToast === 'function') showToast('error', (data && data.message) ? data.message : 'AI Scan failed.', '❌');
          if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ AI Scan'; }
          return;
        }
        if (typeof showToast === 'function') showToast('success', data.message || 'AI Scan completed.', '⚡');

        if (aiScoreEl) {
          const score = data.score;
          aiScoreEl.textContent = score != null ? score : 'N/A';
          aiScoreEl.style.color = score >= 80 ? 'var(--accent3)' : (score >= 50 ? 'var(--gold)' : 'var(--red)');
        }

        const aiNotesEl = document.getElementById('modalAiNotes');
        if (aiNotesEl) {
          aiNotesEl.textContent = data.analysis_note || (data.has_risk_flags
            ? `⚠️ Risk flag detected`
            : 'Verified Educational Loan requirements matched successfully.');
        }

        if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ AI Scan'; }
      })
      .catch(err => {
        console.error('AI Scan error:', err);
        if (typeof showToast === 'function') showToast('error', 'AI Scan failed: ' + (err.message || 'Check server logs'), '❌');
        if (scanBtn) { scanBtn.disabled = false; scanBtn.innerHTML = '⚡ AI Scan'; }
      });
  };
</script>