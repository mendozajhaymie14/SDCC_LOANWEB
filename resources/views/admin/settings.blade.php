@extends('admin.index')

@section('admin_content')
<div class="content-body">
  <div class="panel app-panel">
    <div class="page-header">
      <div>
        <h2 class="panel-title">System Settings</h2>
        <span class="records-count">Administration</span>
      </div>
    </div>

    <form method="POST" action="{{ url('/admin/settings') }}" style="display: grid; gap: 1.5rem; width: 100%;">
      @csrf

      {{-- Editable Settings (read from Setting table) --}}
      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Cooperative Profile</h3>

        <div class="setting-row">
          <label class="setting-label">Cooperative Name</label>
          <input type="text" name="cooperative_name" class="setting-input" value="{{ $editable['cooperative_name'] }}" required>
        </div>

        <div class="setting-row">
          <label class="setting-label">Contact Email</label>
          <input type="email" name="cooperative_email" class="setting-input" value="{{ $editable['cooperative_email'] }}" required>
        </div>

        <div class="setting-row">
          <label class="setting-label">Address</label>
          <input type="text" name="cooperative_address" class="setting-input" value="{{ $editable['cooperative_address'] }}">
        </div>

        <div class="setting-row">
          <label class="setting-label">Contact Number</label>
          <input type="text" name="cooperative_phone" class="setting-input" value="{{ $editable['cooperative_phone'] }}">
        </div>
      </div>

      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Loan Parameters</h3>

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

      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">AI Scoring Thresholds</h3>

        <div class="setting-row">
          <label class="setting-label">Minimum Score</label>
          <input type="number" name="min_approval_score" class="setting-input" value="{{ $readonly['min_approval_score'] }}" required readonly>
        </div>

        <div class="setting-row">
          <label class="setting-label">High Risk Threshold</label>
          <input type="number" name="high_risk_threshold" class="setting-input" value="{{ $readonly['high_risk_threshold'] }}" required readonly>
        </div>
      </div>

      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Notification Templates</h3>

        <p class="text-muted small">These templates use placeholders {member_name}, {amount}, {due_date} that are replaced at send time.</p>

        <div class="setting-row">
          <label class="setting-label">Template: Due Soon</label><br>
          <textarea name="template_due_soon" class="setting-textarea">{{ $editable['template_due_soon'] }}</textarea>
        </div>

        <div class="setting-row">
          <label class="setting-label">Template: Due Today</label><br>
          <textarea name="template_due_today" class="setting-textarea">{{ $editable['template_due_today'] }}</textarea>
        </div>

        <div class="setting-row">
          <label class="setting-label">Template: Overdue</label><br>
          <textarea name="template_overdue" class="setting-textarea">{{ $editable['template_overdue'] }}</textarea>
        </div>

        <div class="setting-row">
          <label class="setting-label">Template: Approved</label><br>
          <textarea name="template_approval" class="setting-textarea">{{ $editable['template_approval'] }}</textarea>
        </div>

        <div class="setting-row">
          <label class="setting-label">Template: Rejected</label><br>
          <textarea name="template_rejection" class="setting-textarea">{{ $editable['template_rejection'] }}</textarea>
        </div>
      </div>

      {{-- Read-Only Config-Driven Settings (selection criteria per paper) --}}
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Selection Criteria (config-driven)</h3>

        <p class="text-muted small">These values are derived from loan_matrix and loan_evaluation config. They are displayed for reference but cannot be edited here.</p>

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
          <select class="setting-input" readonly>
            <option value="weekly" {{ $readonly['reminder_frequency'] === 'weekly' ? 'selected' : '' }}>Weekly</option>
            <option value="daily" {{ $readonly['reminder_frequency'] === 'daily' ? 'selected' : '' }}>Daily</option>
          </select>
          <small class="text-muted">from config('loan_matrix.reminder_freq')</small>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1rem;">
        <button type="submit" style="background-color: #2563eb; color: #ffffff; padding: 0.6rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">Save Settings</button>
        <a href="{{ url('/admin') }}" style="color: #64748b; font-size: 0.9rem; text-decoration:none;">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection