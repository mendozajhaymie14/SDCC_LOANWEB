@extends('admin.index')

@section('admin_content')
<style>
  /* ───────── System Settings page (scoped to .settings-page) ───────── */
  .settings-page { font-family: 'DM Sans', sans-serif; }

  .settings-form {
    display: grid;
    gap: 1.25rem;
    width: 100%;
    max-width: 980px;
  }

  /* Cards */
  .settings-card {
    background: var(--surface, #fff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 14px;
    padding: 1.4rem 1.6rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
  }
  .settings-card.is-readonly {
    background: #f8fafc;
    border-style: dashed;
  }

  .settings-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.1rem;
    padding-bottom: 0.9rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
  }
  .settings-card-title {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--text, #0f172a);
  }
  .settings-card-desc {
    margin: 0.25rem 0 0;
    font-size: 0.82rem;
    color: var(--muted, #64748b);
    line-height: 1.45;
  }

  /* Small badges */
  .settings-badge {
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 0.28rem 0.7rem;
    border-radius: 50px;
    white-space: nowrap;
  }
  .settings-badge.editable { background: rgba(5, 150, 105, 0.12); color: #047857; }
  .settings-badge.locked   { background: #e2e8f0; color: #475569; }

  /* Field grid: two columns on desktop, one on small screens */
  .settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem 1.25rem;
  }
  .settings-grid .span-2 { grid-column: 1 / -1; }

  .setting-row { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; }
  .setting-row br { display: none; }

  .setting-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--muted2, #334155);
  }

  .setting-input,
  .setting-textarea {
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 0.9rem;
    color: var(--text, #0f172a);
    background: #fff;
    border: 1px solid var(--border2, #cbd5e1);
    border-radius: 10px;
    padding: 0.6rem 0.8rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
  }
  .setting-textarea {
    min-height: 92px;
    resize: vertical;
    line-height: 1.5;
  }
  .setting-input:hover,
  .setting-textarea:hover { border-color: #94a3b8; }

  .setting-input:focus,
  .setting-textarea:focus {
    outline: none;
    border-color: var(--accent, #0284c7);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
  }

  .setting-input[readonly],
  select.setting-input[readonly] {
    background: #f1f5f9;
    color: #64748b;
    border-color: #e2e8f0;
    cursor: not-allowed;
    pointer-events: none;
  }

  .settings-page .text-muted,
  .settings-page small.text-muted {
    font-size: 0.75rem;
    color: var(--muted, #64748b);
  }
  .settings-page p.text-muted.small {
    margin: 0 0 1rem;
    font-size: 0.82rem;
    line-height: 1.5;
  }
  .settings-page .placeholder-chip {
    display: inline-block;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.75rem;
    background: #eef2ff;
    color: #4338ca;
    padding: 0.05rem 0.45rem;
    border-radius: 6px;
  }

  /* Alerts */
  .settings-alert {
    max-width: 980px;
    margin-bottom: 1.1rem;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.86rem;
    font-weight: 600;
  }
  .settings-alert.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
  .settings-alert.error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
  .settings-alert ul { margin: 0.35rem 0 0 1.1rem; padding: 0; font-weight: 500; }

  /* Action bar */
  .settings-actions {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.9rem;
    padding: 0.9rem 0;
    background: linear-gradient(to top, #f8fafc 70%, rgba(248, 250, 252, 0));
  }
  .settings-btn-save {
    background: linear-gradient(135deg, var(--accent, #0284c7), var(--accent2, #4338ca));
    color: #fff;
    font-family: inherit;
    font-weight: 700;
    font-size: 0.88rem;
    padding: 0.65rem 1.6rem;
    border: none;
    border-radius: 50px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
    transition: opacity 0.2s ease, transform 0.2s ease;
  }
  .settings-btn-save:hover { opacity: 0.92; transform: translateY(-1px); }
  .settings-btn-save:focus-visible { outline: 3px solid rgba(2, 132, 199, 0.35); outline-offset: 2px; }

  .settings-btn-cancel {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--muted, #64748b);
    text-decoration: none;
    padding: 0.6rem 1.2rem;
    border: 1px solid var(--border2, #cbd5e1);
    border-radius: 50px;
    background: #fff;
    transition: background 0.15s ease, color 0.15s ease;
  }
  .settings-btn-cancel:hover { background: #f1f5f9; color: var(--text, #0f172a); }

  @media (max-width: 700px) {
    .settings-grid { grid-template-columns: 1fr; }
    .settings-card { padding: 1.1rem 1rem; }
    .settings-card-head { flex-direction: column; gap: 0.5rem; }
    .settings-actions { justify-content: stretch; }
    .settings-actions .settings-btn-save,
    .settings-actions .settings-btn-cancel { flex: 1; text-align: center; }
  }
</style>

<div class="content-body settings-page">
  <div class="panel app-panel">
    <div class="page-header">
      <div>
        <h2 class="panel-title">System Settings</h2>
        <span class="records-count">Administration</span>
      </div>
    </div>

    @if (session('success'))
      <div class="settings-alert success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
      <div class="settings-alert error">
        Please fix the following:
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ url('/admin/settings') }}" class="settings-form">
      @csrf

      {{-- Editable Settings (read from Setting table) --}}
      <div class="settings-card">
        <div class="settings-card-head">
          <div>
            <h3 class="settings-card-title">Cooperative Profile</h3>
            <p class="settings-card-desc">Basic details shown on emails and printed reports.</p>
          </div>
          <span class="settings-badge editable">Editable</span>
        </div>

        <div class="settings-grid">
          <div class="setting-row span-2">
            <label class="setting-label">Cooperative Name</label>
            <input type="text" name="cooperative_name" class="setting-input" value="{{ $editable['cooperative_name'] }}" required>
          </div>

          <div class="setting-row">
            <label class="setting-label">Contact Email</label>
            <input type="email" name="cooperative_email" class="setting-input" value="{{ $editable['cooperative_email'] }}" required>
          </div>

          <div class="setting-row">
            <label class="setting-label">Contact Number</label>
            <input type="text" name="cooperative_phone" class="setting-input" value="{{ $editable['cooperative_phone'] }}">
          </div>

          <div class="setting-row span-2">
            <label class="setting-label">Address</label>
            <input type="text" name="cooperative_address" class="setting-input" value="{{ $editable['cooperative_address'] }}">
          </div>
        </div>
      </div>

      <div class="settings-card">
        <div class="settings-card-head">
          <div>
            <h3 class="settings-card-title">Loan Parameters</h3>
            <p class="settings-card-desc">Only the interest rate can be changed here. The other values come from the system configuration.</p>
          </div>
          <span class="settings-badge editable">Partly editable</span>
        </div>

        <div class="settings-grid">
          <div class="setting-row">
            <label class="setting-label">Annual Interest Rate (%)</label>
            <input type="number" step="any" name="annual_interest_rate" class="setting-input" value="{{ $editable['annual_interest_rate'] }}" required>
            <small class="text-muted">e.g., 12 for 12%</small>
          </div>

          <div class="setting-row">
            <label class="setting-label">Default Term</label>
            <input type="number" name="default_loan_term" class="setting-input" value="{{ $readonly['default_loan_term'] }}" required readonly>
          </div>

          <div class="setting-row">
            <label class="setting-label">Penalty Rate</label>
            <input type="number" step="any" name="penalty_rate" class="setting-input" value="{{ $readonly['penalty_rate'] }}" required readonly>
          </div>
        </div>
      </div>

      <div class="settings-card">
        <div class="settings-card-head">
          <div>
            <h3 class="settings-card-title">AI Scoring Thresholds</h3>
            <p class="settings-card-desc">Rule-based scoring limits used for the risk indicator.</p>
          </div>
          <span class="settings-badge locked">Read-only</span>
        </div>

        <div class="settings-grid">
          <div class="setting-row">
            <label class="setting-label">Minimum Score</label>
            <input type="number" name="min_approval_score" class="setting-input" value="{{ $readonly['min_approval_score'] }}" required readonly>
          </div>

          <div class="setting-row">
            <label class="setting-label">High Risk Threshold</label>
            <input type="number" name="high_risk_threshold" class="setting-input" value="{{ $readonly['high_risk_threshold'] }}" required readonly>
          </div>
        </div>
      </div>

      <div class="settings-card">
        <div class="settings-card-head">
          <div>
            <h3 class="settings-card-title">Notification Templates</h3>
            <p class="settings-card-desc">Messages sent to members by email and shown in their notifications.</p>
          </div>
          <span class="settings-badge editable">Editable</span>
        </div>

        <p class="text-muted small">
          Placeholders
          <span class="placeholder-chip">{member_name}</span>
          <span class="placeholder-chip">{amount}</span>
          <span class="placeholder-chip">{due_date}</span>
          are replaced at send time.
        </p>

        <div class="settings-grid">
          <div class="setting-row span-2">
            <label class="setting-label">Template: Due Soon</label>
            <textarea name="template_due_soon" class="setting-textarea">{{ $editable['template_due_soon'] }}</textarea>
          </div>

          <div class="setting-row span-2">
            <label class="setting-label">Template: Due Today</label>
            <textarea name="template_due_today" class="setting-textarea">{{ $editable['template_due_today'] }}</textarea>
          </div>

          <div class="setting-row span-2">
            <label class="setting-label">Template: Overdue</label>
            <textarea name="template_overdue" class="setting-textarea">{{ $editable['template_overdue'] }}</textarea>
          </div>

          <div class="setting-row">
            <label class="setting-label">Template: Approved</label>
            <textarea name="template_approval" class="setting-textarea">{{ $editable['template_approval'] }}</textarea>
          </div>

          <div class="setting-row">
            <label class="setting-label">Template: Rejected</label>
            <textarea name="template_rejection" class="setting-textarea">{{ $editable['template_rejection'] }}</textarea>
          </div>
        </div>
      </div>

      {{-- Read-Only Config-Driven Settings (selection criteria per paper) --}}
      <div class="settings-card is-readonly">
        <div class="settings-card-head">
          <div>
            <h3 class="settings-card-title">Selection Criteria (config-driven)</h3>
            <p class="settings-card-desc">
              These values come from the loan_matrix and loan_evaluation config. They are shown for reference and cannot be edited here.
            </p>
          </div>
          <span class="settings-badge locked">Read-only</span>
        </div>

        <div class="settings-grid">
          <div class="setting-row">
            <label class="setting-label">Default Term</label>
            <input type="number" class="setting-input" value="{{ $readonly['default_loan_term'] }}" readonly>
            <small class="text-muted">from config('loan_matrix.default_term')</small>
          </div>

          <div class="setting-row">
            <label class="setting-label">Penalty Rate</label>
            <input type="number" class="setting-input" value="{{ $readonly['penalty_rate'] }}" readonly>
            <small class="text-muted">from config('loan_matrix.penalty_rate')</small>
          </div>

          <div class="setting-row">
            <label class="setting-label">Minimum Score</label>
            <input type="number" class="setting-input" value="{{ $readonly['min_approval_score'] }}" readonly>
            <small class="text-muted">from config('loan_matrix.min_score')</small>
          </div>

          <div class="setting-row">
            <label class="setting-label">High Risk Threshold</label>
            <input type="number" class="setting-input" value="{{ $readonly['high_risk_threshold'] }}" readonly>
            <small class="text-muted">from config('loan_matrix.high_risk')</small>
          </div>

          <div class="setting-row">
            <label class="setting-label">Grace Period</label>
            <input type="number" class="setting-input" value="{{ $readonly['grace_period_days'] }}" readonly>
            <small class="text-muted">from config('loan_matrix.grace_days')</small>
          </div>

          <div class="setting-row">
            <label class="setting-label">Reminder Frequency</label>
            <select class="setting-input" readonly disabled>
              <option value="weekly" {{ $readonly['reminder_frequency'] === 'weekly' ? 'selected' : '' }}>Weekly</option>
              <option value="daily" {{ $readonly['reminder_frequency'] === 'daily' ? 'selected' : '' }}>Daily</option>
            </select>
            <small class="text-muted">from config('loan_matrix.reminder_freq')</small>
          </div>
        </div>
      </div>

      <div class="settings-actions">
        <a href="{{ url('/admin') }}" class="settings-btn-cancel">Cancel</a>
        <button type="submit" class="settings-btn-save">Save Settings</button>
      </div>
    </form>
  </div>
</div>
@endsection