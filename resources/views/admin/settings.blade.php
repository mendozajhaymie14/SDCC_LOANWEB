<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  @include ('admin.css')
</head>

<body>

<!-- SIDEBAR -->
@include ('admin.sidebar')

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">System Settings <span>/ Administration</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="settingsSearchInput" placeholder="Search settings…" oninput="handleSettingsSearch(this.value)"/>
      </div>
    </div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="content-body" id="page-settings">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">System Settings</h2>
          <span class="records-count">Administration</span>
        </div>
      </div>

      <form id="settingsForm" action="{{ route('settings.update') }}" method="POST">
        @csrf

        <!-- Cooperative Profile Settings -->
        <div class="panel">
          <div class="panel-title" style="margin-bottom:1.2rem;">Cooperative Profile Settings</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Cooperative Name</label>
              <input type="text" name="cooperative_name" class="form-input" value="{{ $settings['cooperative_name'] }}" required/>
            </div>
            <div class="form-group">
              <label class="form-label">Contact Email</label>
              <input type="email" name="cooperative_email" class="form-input" value="{{ $settings['cooperative_email'] }}"/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Address</label>
              <input type="text" name="cooperative_address" class="form-input" value="{{ $settings['cooperative_address'] }}"/>
            </div>
            <div class="form-group">
              <label class="form-label">Contact Number</label>
              <input type="text" name="cooperative_phone" class="form-input" value="{{ $settings['cooperative_phone'] }}"/>
            </div>
          </div>
        </div>

        <!-- Loan & Interest Parameters -->
        <div class="panel">
          <div class="panel-title" style="margin-bottom:1.2rem;">Loan & Interest Parameters</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Standard Annual Interest Rate (%)</label>
              <input type="number" step="0.01" name="annual_interest_rate" class="form-input" value="{{ $settings['annual_interest_rate'] }}" required/>
            </div>
            <div class="form-group">
              <label class="form-label">Default Loan Term (Months)</label>
              <input type="number" name="default_loan_term" class="form-input" value="{{ $settings['default_loan_term'] }}" required/>
            </div>
            <div class="form-group">
              <label class="form-label">Penalty Rate (% per late payment)</label>
              <input type="number" step="0.01" name="penalty_rate" class="form-input" value="{{ $settings['penalty_rate'] }}" required/>
            </div>
          </div>
        </div>

        <!-- AI Credit Scoring Thresholds -->
        <div class="panel">
          <div class="panel-title" style="margin-bottom:1.2rem;">AI Credit Scoring Thresholds</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Minimum Approval Score</label>
              <input type="number" name="min_approval_score" class="form-input" value="{{ $settings['min_approval_score'] }}" required/>
            </div>
            <div class="form-group">
              <label class="form-label">High Risk Threshold</label>
              <input type="number" name="high_risk_threshold" class="form-input" value="{{ $settings['high_risk_threshold'] }}" required/>
            </div>
          </div>
        </div>

        <!-- Automated Reminder Settings -->
        <div class="panel">
          <div class="panel-title" style="margin-bottom:1.2rem;">Automated Reminder Settings</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Grace Period Days</label>
              <input type="number" name="grace_period_days" class="form-input" value="{{ $settings['grace_period_days'] }}" required/>
            </div>
            <div class="form-group">
              <label class="form-label">SMS / Email Reminder Frequency</label>
              <select name="reminder_frequency" class="form-select">
                <option value="daily" @selected($settings['reminder_frequency'] === 'daily')>Daily</option>
                <option value="weekly" @selected($settings['reminder_frequency'] === 'weekly')>Weekly</option>
              </select>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:1rem;margin-top:1.5rem;">
          <button class="btn-submit" type="submit">Save Configuration</button>
          <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

@include('admin.footer')

</body>
</html>