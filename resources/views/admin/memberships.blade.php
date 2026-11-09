<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Membership Applications</title>
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
    <div class="topbar-title" id="topbarTitle">Membership Applications <span>/ Membership</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon"><i class="fa-solid fa-magnifying-glass nav-icon"></i></span>
        <input type="text" id="searchMemberInput" placeholder="Search membership applications…" oninput="filterMembersSearch(this.value)" />
      </div>
    </div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        <i class="fa-solid fa-bell nav-icon"></i><span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings"><i class="fa-solid fa-gear nav-icon"></i></div>
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
          <h2 class="panel-title">Membership Applications</h2>
          <span class="records-count">Total Records: {{ $applications->count() }}</span>
        </div>
      </div>

      <div class="filter-row">
        <button class="filter-btn active" onclick="filterMembers('all',this)">All ({{ $applications->count() }})</button>
        <button class="filter-btn" onclick="filterMembers('approved',this)">Approved</button>
        <button class="filter-btn" onclick="filterMembers('pending',this)">Pending</button>
        <button class="filter-btn" onclick="filterMembers('rejected',this)">Rejected</button>
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>Ref</th>
              <th>Applicant</th>
              <th>Email</th>
              <th>Contact No.</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @if($applications->count() > 0)
              @foreach($applications as $app)
                <tr data-id="{{ $app->id }}" data-status="{{ strtolower($app->status) }}">
                  <td class="td-mono">#{{ str_pad($app->id, 5, '0', STR_PAD_LEFT) }}</td>
                  <td class="applicant-name">{{ $app->fullName }}</td>
                  <td>{{ $app->email }}</td>
                  <td class="td-mono">{{ $app->contact_number ?? '—' }}</td>
                  <td>
                    <span class="status-pill {{ strtolower($app->status) }}">
                      <span class="status-dot"></span>
                      {{ $app->statusLabel }}
                    </span>
                  </td>
                  <td class="td-date">{{ \Carbon\Carbon::parse($app->created_at)->format('M d, Y') }}</td>
                  <td>
                    <button class="btn-review" type="button" onclick="openMemberDetail({{ $app->id }})">View</button>
                  </td>
                </tr>
              @endforeach
            @else
              <tr>
                <td colspan="6" class="empty-table-msg">No membership applications found.</td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @include('admin.footer')
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<script>
  // ─── MEMBER APPLICATION DATA ───
  const membersData = {
    @foreach ($applications as $app)
      @php
        $address = implode(', ', array_filter([
            $app->perm_street,$app->perm_barangay,
            $app->perm_municipality,$app->perm_zip_code,
            $app->perm_province,
        ]));
        $refId = '#' . str_pad($app->id, 5, '0', STR_PAD_LEFT);
        $cdate = $app->created_at ? \Carbon\Carbon::parse($app->created_at)->format('M d, Y') : '—';
        $dob = $app->date_of_birth ? \Carbon\Carbon::parse($app->date_of_birth)->format('M d, Y') : null;
        $memberSince = null;
        // member_since column is added in Problem 2; guard here so the page
        // renders without error before that migration lands.
        if ($app->approvedMember && isset($app->approvedMember->member_since)) {
            $memberSince = $app->approvedMember->member_since
                ? \Carbon\Carbon::parse($app->approvedMember->member_since)->format('M d, Y')
                : 'Not yet';
        }
      @endphp
      "{{ $app->id }}": {
        id: {{ $app->id }},
        ref: @json($refId),
        name: @json($app->fullName),
        email: @json($app->email),
        contact: @json($app->contact_number ?? '—'),
        occupation: @json($app->occupation ?? '—'),
        residency: @json(ucfirst(str_replace('_', ' ', $app->residency_type ?? '—'))),
        address: @json($address),
        dateOfBirth: @json($dob),
        memberSince: @json($memberSince),
        province: @json($app->province ?? ''),
        municipality: @json($app->municipality ?? ''),
        barangay: @json($app->barangay ?? ''),
        addressLine: @json($app->street ?? ''),
        zipCode: @json($app->zip_code ?? ''),
        permStreet: @json($app->perm_street ?? ''),
        permBarangay: @json($app->perm_barangay ?? ''),
        permMunicipality: @json($app->perm_municipality ?? ''),
        permZipCode: @json($app->perm_zip_code ?? ''),
        permProvince: @json($app->perm_province ?? ''),
        permStayYears: @json($app->perm_stay_years ?? ''),
        permStayMonths: @json($app->perm_stay_months ?? ''),
        status: @json(strtolower($app->status)),
        date: @json($cdate),
        monthlyIncome: @json($app->monthly_income ?? ''),
        <?php
        $existingOblig = $app->existing_monthly_obligations ?? '';
        $existingObligDisplay = $existingOblig !== '' && $existingOblig !== null ? $existingOblig : '—';
        ?>
        existingMonthlyObligations: @json($existingObligDisplay),
        <?php
        $savings = $app->savings_balance ?? '';
        $savingsDisplay = $savings !== '' && $savings !== null ? $savings : '—';
        ?>
        savingsBalance: @json($savingsDisplay),
        <?php
        $income = (float) ($app->monthly_income ?? 0);
        $obligations = (float) ($app->existing_monthly_obligations ?? 0);
        $savings = (float) ($app->savings_balance ?? 0);
        $creditLimitService = app(\App\Services\CreditLimitService::class);
        $rec = $creditLimitService->calculateCreditLimitRaw($income, $obligations, $savings, config('loan_matrix.credit_limit.term', 36), config('loan_matrix.interest.monthly_rate', 0.01));
        ?>
        creditLimitRecommendation: @json(['limit' => (float) ($rec['limit'] ?? 0), 'cappedBy' => $rec['cappedBy'] ?? 'affordability']),
      }{{ !$loop->last ? ',' : '' }}
    @endforeach
  };

  // ─── POPULATE MEMBERSHIP BADGE IN SIDEBAR ───
  document.addEventListener('DOMContentLoaded', function () {
    const badge = document.getElementById('badge-memberships');
    const pendingCount = Object.values(membersData).filter(m => m.status === 'pending').length;
    if (badge && pendingCount > 0) {
      badge.textContent = pendingCount;
      badge.style.display = '';
    }
  });

  // ─── MEMBER DETAIL MODAL ───
  function openMemberDetail(id) {
    if (typeof closeAllDropdowns === 'function') closeAllDropdowns();
    const m = membersData[id];
    if (!m) {
      if (typeof showToast === 'function') showToast('error', 'Application not found', '<i class="fa-solid fa-xmark nav-icon"></i>');
      return;
    }

    const statusLabel = m.status.charAt(0).toUpperCase() + m.status.slice(1);

    const row = (label, value) => `
      <div style="display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid var(--border);font-size:.86rem;">
        <span style="color:var(--muted);flex-shrink:0;">${label}</span>
        <span style="font-weight:600;text-align:right;">${value}</span>
      </div>`;

    const bodyEl = document.getElementById('memberDetailBody');
    const titleEl = document.getElementById('memberDetailTitle');

    if (titleEl) titleEl.textContent = m.ref + ' — ' + m.name;

    if (bodyEl) {
      bodyEl.innerHTML = `
        ${row('Applicant', m.name)}
        ${row('Email', m.email)}
        ${row('Contact No.', m.contact)}
        ${row('Occupation', m.occupation)}
        ${row('Permanent Address', m.address || '—')}
        ${row('Birthdate', m.dateOfBirth ? m.dateOfBirth : '—')}
        ${row('Monthly Income', m.monthlyIncome ? '₱' + Number(m.monthlyIncome).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : '₱0.00')}
        ${row('Savings Balance (declared)', m.savingsBalance && m.savingsBalance !== '—' ? '₱' + Number(m.savingsBalance).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—')}
        ${row('Existing Monthly Obligations', m.existingMonthlyObligations && m.existingMonthlyObligations !== '—' ? '₱' + Number(m.existingMonthlyObligations).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—')}

        ${m.creditLimitRecommendation && m.creditLimitRecommendation.limit > 0 ? `
        <div style="margin-top:.8rem;">
          ${row('Recommended credit limit', '<span style="font-family:monospace;">₱' + Number(m.creditLimitRecommendation.limit).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>')}
          <div style="font-size:0.75rem;color:var(--muted);margin-top:.15rem;">Capped by: ${m.creditLimitRecommendation.cappedBy}</div>
        </div>
        ` : ''}

        <div style="margin-top:.8rem;">
          ${row('Status', '<span class="status-pill ' + m.status + '"><span class="status-dot"></span>' + statusLabel + '</span>')}
          ${row('Date', m.date)}
        </div>

        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          ${ (m.status === 'pending') ? `
            <div style="display:flex;gap:.7rem;">
              <button class="btn-cancel" type="button" style="width:auto;flex:1;color:var(--red);border-color:var(--red);"
                      onclick="openRejectMemberPanel(${m.id})"><i class="fa-solid fa-xmark nav-icon"></i> Reject</button>
              <button class="btn-submit" type="button" style="width:auto;flex:1;"
                      onclick="openApproveMemberPanel(${m.id})"><i class="fa-solid fa-check nav-icon"></i> Approve & Register</button>
            </div>
            <!-- Approve panel (hidden until opened) -->
            <div id="approveMemberPanel_${m.id}" style="display:none;margin-top:1rem;padding:1rem;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;box-sizing:border-box;">
              <h4 style="margin:0 0 .5rem;font-size:.9rem;font-weight:600;">Approve Membership</h4>
              <div style="margin-bottom:.5rem;">
                <label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.2rem;">Approved Credit Limit</label>
                <input type="number" id="memberApproveAmount_${m.id}" min="0" step="1000" max="${m.creditLimitRecommendation ? m.creditLimitRecommendation.limit : 0}" value="${m.creditLimitRecommendation ? m.creditLimitRecommendation.limit : 0}" style="width:100%;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-family:monospace;font-size:.9rem;box-sizing:border-box;" />
              </div>
              <div style="display:flex;gap:.5rem;">
                <button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:600;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;cursor:pointer;box-sizing:border-box;" onclick="confirmApproveMember(${m.id})">Confirm Approve</button>
                <button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:500;color:var(--muted);border:1px solid var(--border);background:transparent;cursor:pointer;box-sizing:border-box;" onclick="closeApproveMemberPanel(${m.id})">Cancel</button>
              </div>
            </div>
            <!-- Reject panel (hidden until opened) -->
            <div id="rejectMemberPanel_${m.id}" style="display:none;margin-top:1rem;padding:1rem;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;box-sizing:border-box;">
              <h4 style="margin:0 0 .5rem;font-size:.9rem;font-weight:600;">Reject Membership</h4>
              <div style="margin-bottom:.5rem;">
                <label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.2rem;">Rejection Reason <span style="color:#ef4444;">(required, min 10 chars)</span></label>
                <textarea id="memberRejectReason_${m.id}" placeholder="Reason for rejection" style="width:100%;padding:.4rem .6rem;border:1px solid var(--border);border-radius:6px;font-size:.85rem;min-height:60px;box-sizing:border-box;"></textarea>
              </div>
              <div style="display:flex;gap:.5rem;">
                <button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:600;background:var(--red);color:#fff;border:none;cursor:pointer;box-sizing:border-box;" onclick="confirmRejectMember(${m.id})">Confirm Reject</button>
                <button type="button" style="flex:1;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:500;color:var(--muted);border:1px solid var(--border);background:transparent;cursor:pointer;box-sizing:border-box;" onclick="closeRejectMemberPanel(${m.id})">Cancel</button>
              </div>
            </div>
          ` : '' }
        </div>
      `;
    }

    if (typeof openModal === 'function') openModal('memberDetail');
  }

  // ─── PANEL HELPERS ───
  function openApproveMemberPanel(id) {
    // Close reject panel if open
    var rejectPanel = document.getElementById('rejectMemberPanel_' + id);
    if (rejectPanel) rejectPanel.style.display = 'none';
    // Show approve panel
    var approvePanel = document.getElementById('approveMemberPanel_' + id);
    if (approvePanel) approvePanel.style.display = 'block';
  }

  function closeApproveMemberPanel(id) {
    var panel = document.getElementById('approveMemberPanel_' + id);
    if (panel) panel.style.display = 'none';
  }

  function openRejectMemberPanel(id) {
    // Close approve panel if open
    var approvePanel = document.getElementById('approveMemberPanel_' + id);
    if (approvePanel) approvePanel.style.display = 'none';
    // Show reject panel
    var rejectPanel = document.getElementById('rejectMemberPanel_' + id);
    if (rejectPanel) rejectPanel.style.display = 'block';
  }

  function closeRejectMemberPanel(id) {
    var panel = document.getElementById('rejectMemberPanel_' + id);
    if (panel) panel.style.display = 'none';
  }

  // ─── CONFIRM APPROVE MEMBER ───
  function confirmApproveMember(id) {
    var approvedAmountEl = document.getElementById('memberApproveAmount_' + id);

    var approvedAmount = approvedAmountEl ? approvedAmountEl.value : '';

    // Get recommended amount from the data
    var m = membersData[id];
    var recommended = (m && m.creditLimitRecommendation) ? m.creditLimitRecommendation.limit : 0;

    // Client-side validation
    var amountVal = approvedAmount === '' ? null : parseFloat(approvedAmount);
    if (amountVal === null || amountVal <= 0) {
      if (recommended === 0 && approvedAmount === '') {
        // blank allowed only when recommendation is 0
      } else {
        if (typeof showToast === 'function') showToast('error', 'Please enter a valid approved amount.', '<i class="fa-solid fa-xmark nav-icon"></i>');
        return;
      }
    }

    if (amountVal !== null && amountVal > recommended) {
      if (typeof showToast === 'function') showToast('error', 'Approved credit limit cannot exceed the recommended limit.', '<i class="fa-solid fa-xmark nav-icon"></i>');
      return;
    }

    fetch('/memberships/' + id + '/approve', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ approved_amount: approvedAmount })
    })
      .then(function (res) {
        if (!res.ok) return res.json().then(function (err) { throw err; });
        return res.json();
      })
      .then(function (data) {
        if (typeof showToast === 'function') showToast('success', 'Membership application approved', '<i class="fa-solid fa-check nav-icon"></i>');
        if (typeof closeAllModals === 'function') closeAllModals();
        setTimeout(function () { location.reload(); }, 700);
      })
      .catch(function (err) {
        var msg = (err && err.message) ? err.message : 'Failed to approve application.';
        if (typeof showToast === 'function') showToast('error', msg, '<i class="fa-solid fa-xmark nav-icon"></i>');
      });
  }

  // ─── CONFIRM REJECT MEMBER ───
  function confirmRejectMember(id) {
    var rejectReasonEl = document.getElementById('memberRejectReason_' + id);
    var rejectionReason = rejectReasonEl ? rejectReasonEl.value.trim() : '';

    // Client-side validation: min 10 chars
    if (rejectionReason.length < 10) {
      if (typeof showToast === 'function') showToast('error', 'Rejection reason must be at least 10 characters.', '<i class="fa-solid fa-xmark nav-icon"></i>');
      return;
    }

    fetch('/memberships/' + id + '/reject', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ rejection_reason: rejectionReason })
    })
      .then(function (res) {
        if (!res.ok) return res.json().then(function (err) { throw err; });
        return res.json();
      })
      .then(function (data) {
        if (typeof showToast === 'function') showToast('success', 'Membership application rejected', '<i class="fa-solid fa-check nav-icon"></i>');
        if (typeof closeAllModals === 'function') closeAllModals();
        setTimeout(function () { location.reload(); }, 700);
      })
      .catch(function (err) {
        var msg = (err && err.message) ? err.message : 'Failed to reject application.';
        if (typeof showToast === 'function') showToast('error', msg, '<i class="fa-solid fa-xmark nav-icon"></i>');
      });
  }

  // ─── FILTER ───
  function filterMembers(status, el) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');

    document.querySelectorAll('.app-table tbody tr[data-status]').forEach(row => {
      row.style.display = (status === 'all' || row.getAttribute('data-status') === status) ? '' : 'none';
    });
  }

  // ─── LIVE SEARCH ───
  function filterMembersSearch(q) {
    const term = q.trim().toLowerCase();
    document.querySelectorAll('.app-table tbody tr[data-id]').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = !term || text.includes(term) ? '' : 'none';
    });
  }

  </script>
  </body>
</html>