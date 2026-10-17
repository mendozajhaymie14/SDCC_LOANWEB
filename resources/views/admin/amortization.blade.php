<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Amortization Schedule</title>
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
    <div class="topbar-title" id="topbarTitle">Amortization <span>/ Schedule</span></div>
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
          <h2 class="panel-title">Amortization Schedule</h2>
        </div>
      </div>

      <!-- Loan Application Info -->
      <div class="p-4 mb-4 bg-gray-50 rounded-lg border border-gray-200">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b;">Application</div>
            <div style="color:#6b7280;">{{ $application->app_id ?? $application->id }}</div>
          </div>
          <div>
            <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b;">Applicant</div>
            <div style="color:#6b7280;">{{ $application->applicant ?? 'N/A' }}</div>
          </div>
          <div>
            <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b;">Loan Amount</div>
            <div style="color:#6b7280;">₱{{ number_format($application->amount ?? 0, 2) }}</div>
          </div>
          <div>
            <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b;">Term</div>
            <div style="color:#6b7280;">{{ $loanApp?->term_months ?? 'N/A' }} months</div>
          </div>
        </div>
      </div>

      <!-- Amortization Schedule Table -->
      @if(!empty($schedule))
        <div class="table-responsive">
          <table class="min-w-full divide divide-gray-200">
            <thead>
              <tr>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b font-mono">Month</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Due Date</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Payment</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Principal</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Interest</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Remaining Balance</th>
              </tr>
            </thead>
            <tbody>
              @foreach($schedule as $row)
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="py-3 px-4 font-medium text-gray-800 font-mono whitespace-nowrap">{{ $row['month'] }}</td>
                  <td class="py-3 px-4 font-medium text-gray-800">{{ \Carbon\Carbon::parse($row['due_date'])->format('M d, Y') }}</td>
                  <td class="py-3 px-4 font-medium text-gray-800">₱{{ number_format($row['payment'], 2) }}</td>
                  <td class="py-3 px-4 font-medium text-gray-800">₱{{ number_format($row['principal'], 2) }}</td>
                  <td class="py-3 px-4 font-medium text-gray-800">₱{{ number_format($row['interest'], 2) }}</td>
                  <td class="py-3 px-4 font-medium text-gray-800">₱{{ number_format($row['remaining_balance'], 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <!-- Summary Panel -->
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mt-4">
          <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
              <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b; font-size:0.875rem;">Monthly EMI</div>
              <div style="color:#0891b2; font-weight:700; font-size:1.5rem;">₱{{ number_format($summary['emi'], 2) }}</div>
            </div>
            <div>
              <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b; font-size:0.875rem;">Total Interest</div>
              <div style="color:#0891b2; font-weight:700; font-size:1.5rem;">₱{{ number_format($summary['total_interest'], 2) }}</div>
            </div>
            <div>
              <div style="font-family:'DM Sans',sans-serif;font-weight:600; color:#1e293b; font-size:0.875rem;">Total Paid</div>
              <div style="color:#0891b2; font-weight:700; font-size:1.5rem;">₱{{ number_format($summary['total_paid'], 2) }}</div>
            </div>
          </div>
        </div>
      @else
        <div class="p-4 text-center text-gray-500">
          <p>No amortization schedule available. The application may not have a linked loan application with term data.</p>
        </div>
      @endif
    </div>
  </div>
</div>

<!-- GLOBAL OVERLAY FOR MODALS -->
<div class="overlay" id="overlay" onclick="closeAllModals()"></div>

</body>
</html>