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
    <div class="topbar-title" id="topbarTitle">Loan Release Records <span>/ Loan Management</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="disbursementSearchInput" placeholder="Search approved loans…" oninput="handleDisbursementSearch(this.value)" onfocus="showDisbursementSearchResults()" onblur="setTimeout(hideDisbursementSearchResults,200)"/>
      </div>
      <div class="search-results" id="disbursementSearchResults"></div>
    </div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="content-body">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Loan Release Records</h2>
          <span class="records-count">Total Records: {{ $disbursements->count() }}</span>
        </div>
      </div>

      <div class="filter-row">
        <button class="filter-btn active" onclick="filterDisbursements('all',this)">All ({{ $disbursements->count() }})</button>
        <button class="filter-btn" onclick="filterDisbursements('approved',this)">Approved</button>
        <button class="filter-btn" onclick="filterDisbursements('recorded',this)">Recorded</button>
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Application ID</th>
              <th>Borrower Name</th>
              <th>Loan Type</th>
              <th>Amount</th>
              <th>Record Status</th>
              <th>Release Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($disbursements as $disb)
  <tr data-status="{{ strtolower($disb->status) }}">
    <td class="td-mono">#{{ $disb->app_id ?? $disb->application_id ?? $disb->id }}</td>
    <td class="applicant-name">{{ $disb->applicant_name ?? $disb->applicant ?? $disb->borrower_name ?? 'N/A' }}</td>
    <td><span class="loan-type-tag">{{ $disb->loan_type ?? 'Personal Loan' }}</span></td>
    <td>₱{{ number_format($disb->amount, 2) }}</td>
    <td>
      <span class="status-pill {{ strtolower($disb->status) }}">
        {{ ucfirst($disb->status) }}
      </span>
    </td>
    <td class="td-date">{{ \Carbon\Carbon::parse($disb->created_at)->format('M d, Y') }}</td>
    <td>
      <button class="btn-review" type="button" onclick="openReleaseRecordModal('{{ $disb->app_id ?? $disb->application_id ?? $disb->id }}')">Record Release</button>
    </td>
  </tr>
@empty
  <tr>
    <td colspan="7" class="empty-table-msg">No approved loans found.</td>
  </tr>
@endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

<!-- Record Release Modal -->
<div class="modal" id="modal-recordRelease" onclick="event.stopPropagation()">
  <div class="modal-header">
    <div class="modal-title">📋 Record Release</div>
    <div class="modal-close" onclick="closeAllModals()">✕</div>
  </div>
  <form id="recordReleaseForm" action="{{ route('disbursements.process') }}" method="POST">
    @csrf
    <input type="hidden" name="app_id" id="releaseAppId"/>
    <input type="hidden" name="loan_application_id" id="releaseLoanApplicationId"/>
    <div class="form-group"><label class="form-label">Application ID</label><input class="form-input" id="releaseAppIdDisplay" placeholder="#SCC-XXXXX" readonly/></div>
    <div class="form-group"><label class="form-label">Borrower Name</label><input class="form-input" id="releaseBorrowerName" placeholder="Full name" readonly/></div>
    <div class="form-group"><label class="form-label">Release Amount (₱)</label><input class="form-input" id="releaseAmountDisplay" placeholder="0.00" readonly/></div>
    <div class="form-group"><label class="form-label">Release Date</label><input name="release_date" class="form-input" placeholder="YYYY-MM-DD" required/></div>
    <div style="display:flex;gap:1rem;margin-top:1.5rem;">
      <button class="btn-submit" type="submit">Record Release</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </div>
  </form>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

@include('admin.footer')

</body>
</html>