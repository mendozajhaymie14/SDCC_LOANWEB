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
        $rec = $creditLimitService->calculateCreditLimitRaw($income, $obligations, $savings, 36, 0.01);
        ?>
        creditLimitRecommendation: @json(['limit' => (float) ($rec['limit'] ?? 0), 'cappedBy' => $rec['cappedBy'] ?? 'affordability']),
      }{{ !$loop->last ? ',' : '' }}
    @endforeach
  };

  csrfToken = @json(csrf_token());

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
          ${row('Recommended credit limit', '₱' + Number(m.creditLimitRecommendation.limit).toLocaleString('en-PH', {minimumFractionDigits:0, maximumFractionDigits:0}) + ' (' + m.creditLimitRecommendation.cappedBy + ')')}
          <input type="number" id="approved_amount_${m.id}" min="0" step="1000" value="${m.creditLimitRecommendation.limit}" style="width:100%;padding:6px;font-size:0.86rem;" oninput="toggleApproveReason(${m.id})">
          <textarea id="approve_reason_${m.id}" rows="2" placeholder="Reason for amount above recommendation (required if above)" style="width:100%;padding:6px;font-size:0.86rem;resize:vertical;display:none;margin-top:.5rem;"></textarea>
        </div>
        ` : ''}

        <div style="margin-top:.8rem;">
          <label style="font-size:0.82rem;color:var(--muted);">Rejection reason (min 10 chars)</label>
          <textarea id="rejection_reason_${m.id}" rows="2" placeholder="Reason for rejection" style="width:100%;padding:6px;font-size:0.86rem;resize:vertical;"></textarea>
        </div>

        <div style="margin-top:.8rem;">
          ${row('Status', '<span class="status-pill ' + m.status + '"><span class="status-dot"></span>' + statusLabel + '</span>')}
          ${row('Date', m.date)}
        </div>

        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          ${ (m.status === 'pending') ? `
            <div style="display:flex;gap:.7rem;">
              <button class="btn-cancel" type="button" style="width:auto;flex:1;color:var(--red);border-color:var(--red);"
                      onclick="if(confirm('Reject this membership application?'))rejectMember(${m.id})"><i class="fa-solid fa-xmark nav-icon"></i> Reject</button>
              <button class="btn-submit" type="button" style="width:auto;flex:1;"
                      onclick="if(confirm('Approve this applicant and add them to the member registry?'))approveMember(${m.id})"><i class="fa-solid fa-check nav-icon"></i> Approve & Register</button>
            </div>
          ` : '' }
        </div>
      `;
    }

    if (typeof openModal === 'function') openModal('memberDetail');
  }

  // ─── APPROVE MEMBER ───
  function approveMember(id) {
    const approvedAmountEl = document.getElementById('approved_amount_' + id);
    const approvedAmount = approvedAmountEl ? approvedAmountEl.value : '';
    const approveReasonEl = document.getElementById('approve_reason_' + id);
    const approvalReason = approveReasonEl ? approveReasonEl.value : '';

    fetch('/memberships/' + id + '/approve', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ approved_amount: approvedAmount, approve_reason: approvalReason })
    })
      .then(res => res.ok ? res.json() : Promise.reject())
      .then(data => {
        if (typeof showToast === 'function') showToast('success', 'Membership application approved', '<i class="fa-solid fa-check nav-icon"></i>');
        if (typeof closeAllModals === 'function') closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(() => {
        if (typeof showToast === 'function') showToast('error', 'Failed to approve application.', '<i class="fa-solid fa-xmark nav-icon"></i>');
      });
  }

  function toggleApproveReason(id) {
    const amountEl = document.getElementById('approved_amount_' + id);
    const reasonEl = document.getElementById('approve_reason_' + id);
    if (!amountEl || !reasonEl) return;
    const recommended = parseFloat(amountEl.defaultValue) || 0;
    const current = parseFloat(amountEl.value) || 0;
    reasonEl.style.display = current > recommended ? 'block' : 'none';
  }

  // ─── REJECT MEMBER ───
  function rejectMember(id) {
    const rejectionReasonEl = document.getElementById('rejection_reason_' + id);
    const rejectionReason = rejectionReasonEl ? rejectionReasonEl.value : '';

    fetch('/memberships/' + id + '/reject', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ rejection_reason: rejectionReason })
    })
      .then(res => res.ok ? res.json() : Promise.reject())
      .then(data => {
        if (typeof showToast === 'function') showToast('success', 'Membership application rejected', '<i class="fa-solid fa-xmark nav-icon"></i>');
        if (typeof closeAllModals === 'function') closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(() => {
        if (typeof showToast === 'function') showToast('error', 'Failed to reject application.', '<i class="fa-solid fa-xmark nav-icon"></i>');
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