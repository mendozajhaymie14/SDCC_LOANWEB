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

    <form method="POST" action="{{ url('/admin/settings') }}" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; width: 100%;">
      @csrf

      <!-- LEFT COLUMN: Settings Form (2/3 width) -->
      <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Cooperative Profile Settings -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
          <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Cooperative Profile Settings</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Cooperative Name</label>
              <input type="text" name="cooperative_name" class="form-input" value="{{ $settings['cooperative_name'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Contact Email</label>
              <input type="email" name="cooperative_email" class="form-input" value="{{ $settings['cooperative_email'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Address</label>
              <input type="text" name="cooperative_address" class="form-input" value="{{ $settings['cooperative_address'] }}" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Contact Number</label>
              <input type="text" name="cooperative_phone" class="form-input" value="{{ $settings['cooperative_phone'] }}" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
          </div>
        </div>

        <!-- Loan & Interest Parameters -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
          <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Loan & Interest Parameters</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Standard Annual Interest Rate (%)</label>
              <input type="number" step="any" name="annual_interest_rate" class="form-input" value="{{ $settings['annual_interest_rate'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
              <small class="text-muted" style="font-size: 0.75rem; color: #718096;">(e.g., 12 for 12%)</small>
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Default Loan Term (Months)</label>
              <input type="number" name="default_loan_term" class="form-input" value="{{ $settings['default_loan_term'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Penalty Rate (% per late payment)</label>
              <input type="number" step="any" name="penalty_rate" class="form-input" value="{{ $settings['penalty_rate'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
          </div>
        </div>

        <!-- AI Credit Scoring Thresholds -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
          <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">AI Credit Scoring Thresholds</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Minimum Approval Score</label>
              <input type="number" name="min_approval_score" class="form-input" value="{{ $settings['min_approval_score'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">High Risk Threshold</label>
              <input type="number" name="high_risk_threshold" class="form-input" value="{{ $settings['high_risk_threshold'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
          </div>
        </div>

        <!-- Automated Reminder Settings -->
        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
          <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Automated Reminder Settings</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">Grace Period Days</label>
              <input type="number" name="grace_period_days" class="form-input" value="{{ $settings['grace_period_days'] }}" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
            </div>
            <div class="form-group">
              <label class="form-label" style="font-size: 0.85rem; color: #4a5568;">SMS / Email Reminder Frequency</label>
              <select name="reminder_frequency" class="form-select" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; color: #374151; outline: none; background: #fff;">
                <option value="daily" {{ $settings['reminder_frequency'] === 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ $settings['reminder_frequency'] === 'weekly' ? 'selected' : '' }}>Weekly</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
          <button type="submit" style="background-color: #2563eb; color: #ffffff; padding: 0.6rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; width: 100%; max-width: 200px;">Save Settings</button>
        </div>
      </div>

      <!-- RIGHT COLUMN: Backup & Quick Tools (1/3 width) -->
      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; height: fit-content;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">One-Click Database Backup</h3>
        <div style="margin-bottom: 1rem;">
          <div style="font-size: 0.85rem; color: #4a5568; margin-bottom: 0.5rem;">Export Full Database Dump</div>
          <div style="font-size: 0.75rem; color: #718096;">Generates a downloadable SQL dump of all tables (users, loan_applications, payments, audit_logs, etc.).</div>
        </div>
        <a href="{{ route('admin.backup') }}" download class="btn-new" style="width: 100%; text-align: left; text-decoration:none; color: #2b6cb0;">
          <i class="fas fa-cloud-download-alt mr-2"></i>Backup Database
        </a>
      </div>

      <!-- Quick Admin Tools -->
      <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; margin-top: 1rem;">
        <h4 style="margin: 0 0 0.75rem 0; font-size: 0.9rem; font-weight: 600; color: #1e293b;">Quick Admin Tools</h4>
        <div style="display: space-between; gap: 0.5rem;">
          @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VIEW_AUDIT_LOGS))
          <a href="{{ url('/admin/audit-logs') }}" style="color: #2b6cb0; font-size: 0.85rem; text-decoration:none;">Audit Logs</a>
          @endif
          @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VIEW_REPORTS))
          <a href="{{ url('/reports') }}" style="color: #2b6cb0; font-size: 0.85rem; text-decoration:none;">Reports</a>
          @endif
          @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::MANAGE_BACKUPS))
          <a href="{{ url('/admin/backup') }}" style="color: #2b6cb0; font-size: 0.85rem; text-decoration:none;">Database Backup</a>
          @endif
        </div>
      </div>
    </form>
  </div>
</div>
@endsection