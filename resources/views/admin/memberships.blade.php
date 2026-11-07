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
        $address = implode(', ', array_filter([$app->perm_house_no,
            $app->perm_street,$app->perm_barangay,
            $app->perm_municipality,$app->perm_zip_code,
        ]));
        $refId = '#' . str_pad($app->id, 5, '0', STR_PAD_LEFT);
        $cdate = $app->created_at ? \Carbon\Carbon::parse($app->created_at)->format('M d, Y') : '—';
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
        province: @json($app->province ?? ''),
        municipality: @json($app->municipality ?? ''),
        barangay: @json($app->barangay ?? ''),
        addressLine: @json($app->street ?? ''),
        zipCode: @json($app->zip_code ?? ''),
        permHouseNo: @json($app->perm_house_no ?? ''),
        permStreet: @json($app->perm_street ?? ''),
        permBarangay: @json($app->perm_barangay ?? ''),
        permMunicipality: @json($app->perm_municipality ?? ''),
        permZipCode: @json($app->perm_zip_code ?? ''),
        permStayYears: @json($app->perm_stay_years ?? ''),
        permStayMonths: @json($app->perm_stay_months ?? ''),
        status: @json(strtolower($app->status)),
        date: @json($cdate),
        monthlyIncome: @json($app->monthly_income ?? ''),
        existingMonthlyObligations: @json($app->existing_monthly_obligations ?? '')
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
        ${row('Residency Type', m.residency)}
        ${row('Permanent Address', m.address || '—')}
        ${row('Monthly Income', m.monthlyIncome ? '₱' + Number(m.monthlyIncome).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2}) : '₱0.00')}
        ${row('Coop Member Since', m.memberStatus === 'Yes' ? 'Approved' : 'Not yet')}

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
    fetch('/memberships/' + id + '/approve', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      }
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

  // ─── REJECT MEMBER ───
  function rejectMember(id) {
    fetch('/memberships/' + id + '/reject', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      }
    })
      .then(res => res.ok ? res.json() : Promise.reject())
      .then(data => {
        if (typeof showToast === 'function') showToast('error', 'Membership application rejected', '<i class="fa-solid fa-xmark nav-icon"></i>');
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