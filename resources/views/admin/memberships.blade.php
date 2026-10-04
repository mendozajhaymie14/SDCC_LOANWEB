<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Membership Applications</title>
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
    <div class="topbar-title" id="topbarTitle">Membership Applications <span>/ Membership</span></div>
    <div class="topbar-actions">
      <div class="icon-btn" onclick="toggleProfile()" data-tip="Settings">⚙️</div>
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
              <th>TIN</th>
              <th>Status</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($applications as $app)
  <tr data-status="{{ strtolower($app->status) }}">
    <td class="td-mono">#{{ str_pad($app->id, 5, '0', STR_PAD_LEFT) }}</td>
    <td class="applicant-name">{{ $app->fullName }}</td>
    <td>{{ $app->email }}</td>
    <td class="td-mono">{{ $app->contact_number ?? '—' }}</td>
    <td class="td-mono">{{ $app->tin ?? '—' }}</td>
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
@empty
  <tr>
    <td colspan="8" class="empty-table-msg">No membership applications found.</td>
  </tr>
@endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @include('admin.footer')
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

</body>
<script>
  // ─── MEMBER APPLICATION DATA ───
  const membersData = {
    @foreach ($applications as $app)
      @php
        $address = implode(', ', array_filter([
            $app->perm_house_no, $app->perm_street,
            $app->perm_barangay, $app->perm_municipality, $app->perm_zip_code,
        ]));
        $refId = '#' . str_pad($app->id, 5, '0', STR_PAD_LEFT);
      @endphp
      "{{ $app->id }}": {
        id: {{ $app->id }},
        ref: @json($refId),
        name: @json($app->fullName),
        email: @json($app->email),
        contact: @json($app->contact_number ?? '—'),
        birthdate: @json($app->birthdate ? $app->birthdate->format('M d, Y') : '—'),
        nationality: @json($app->nationality ?? '—'),
        placeOfBirth: @json($app->place_of_birth ?? '—'),
        gender: @json($app->gender ?? '—'),
        occupation: @json($app->occupation ?? '—'),
        civilStatus: @json(ucfirst(str_replace('_', ' ', $app->civil_status ?? '—'))),
        residency: @json(ucfirst(str_replace('_', ' ', $app->residency_type ?? '—'))),
        tin: @json($app->tin ?? '—'),
        address: @json($address),
        idPicture: @json($app->id_picture ? asset('storage/' . $app->id_picture) : null),
        proofOfBilling: @json($app->proof_of_billing ? asset('storage/' . $app->proof_of_billing) : null),
        refs: @json($app->character_references ?? []),
        memberStatus: @json($app->approved_member_id ? 'Yes' : 'No'),
        status: @json(strtolower($app->status)),
        date: @json($app->created_at->format('M d, Y'))
      },
    @endforeach
  };

  const csrfToken = @json(csrf_token());

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
    closeAllDropdowns();
    const m = membersData[id];
    if (!m) {
      showToast('error', 'Application not found', '❌');
      return;
    }

    const statusLabel = m.status.charAt(0).toUpperCase() + m.status.slice(1);

    const row = (label, value) => `
      <div style="display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid var(--border);font-size:.86rem;">
        <span style="color:var(--muted);flex-shrink:0;">${label}</span>
        <span style="font-weight:600;text-align:right;">${value}</span>
      </div>`;

    let refsHtml = '<span style="color:var(--muted);font-size:.82rem;">None provided</span>';
    if (m.refs && m.refs.length) {
      refsHtml = m.refs.map(function (r, i) {
        const line = function (v) { return v ? '<div style="font-size:.82rem;margin-top:2px;">' + v + '</div>' : ''; };
        return `
          <div style="border:1px solid var(--border);border-radius:10px;padding:.7rem .9rem;margin-bottom:.6rem;">
            <div style="font-weight:700;font-size:.85rem;">${i + 1}. ${r.full_name || 'Unnamed reference'}</div>
            ${line(r.address)}
            ${line(r.contact_number)}
          </div>`;
      }).join('');
    }

    const docs = [];
    if (m.idPicture) docs.push('<a href="' + m.idPicture + '" target="_blank" style="color:var(--accent);font-size:.84rem;font-weight:600;text-decoration:none;"><i class="fa-solid fa-image"></i> View ID Picture</a>');
    if (m.proofOfBilling) docs.push('<a href="' + m.proofOfBilling + '" target="_blank" style="color:var(--accent);font-size:.84rem;font-weight:600;text-decoration:none;"><i class="fa-solid fa-file-lines"></i> View Proof of Billing</a>');
    if (!docs.length) docs.push('<span style="color:var(--muted);font-size:.82rem;">No documents uploaded</span>');

    const bodyEl = document.getElementById('appDetailBody');
    document.getElementById('appDetailTitle').textContent = m.ref + ' — ' + m.name;

    bodyEl.innerHTML = `
      ${row('Applicant', m.name)}
      ${row('Email', m.email)}
      ${row('Contact No.', m.contact)}
      ${row('Birthdate', m.birthdate)}
      ${row('Nationality', m.nationality)}
      ${row('Place of Birth', m.placeOfBirth)}
      ${row('Gender', m.gender)}
      ${row('Occupation', m.occupation)}
      ${row('Civil Status', m.civilStatus)}
      ${row('Residency Type', m.residency)}
      ${row('TIN', m.tin)}
      ${row('Permanent Address', m.address || '—')}
      ${row('Coop Member Since', m.memberStatus === 'Yes' ? 'Approved' : 'Not yet')}

      <div style="display:flex;gap:1.2rem;margin:.9rem 0;flex-wrap:wrap;">
        ${docs.join('')}
      </div>

      <div style="margin:.9rem 0;">
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:.5rem;">Character References</div>
        ${refsHtml}
      </div>

      <div style="margin-top:.8rem;">
        ${row('Status', '<span class="status-pill ' + m.status + '"><span class="status-dot"></span>' + statusLabel + '</span>')}
        ${row('Date', m.date)}
      </div>

      <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
        ${ (m.status === 'pending') ? `
          <form id="member-approve-form-${m.id}" action="/memberships/${m.id}/approve" method="POST" style="display:none;">
            <input type="hidden" name="_token" value="${csrfToken}">
          </form>
          <form id="member-reject-form-${m.id}" action="/memberships/${m.id}/reject" method="POST" style="display:none;">
            <input type="hidden" name="_token" value="${csrfToken}">
          </form>
          <div style="display:flex;gap:.7rem;">
            <button class="btn-cancel" type="button" style="width:auto;flex:1;color:var(--red);border-color:var(--red);"
                    onclick="if(confirm('Reject this membership application?'))document.getElementById('member-reject-form-${m.id}').submit()">✗ Reject</button>
            <button class="btn-submit" type="button" style="width:auto;flex:1;"
                    onclick="if(confirm('Approve this applicant and add them to the member registry?'))document.getElementById('member-approve-form-${m.id}').submit()">✓ Approve & Register</button>
          </div>
        ` : '' }
        <button class="btn-cancel" type="button" style="width:100%;color:var(--red);border-color:var(--red);background:rgba(239,68,68,0.05);"
                onclick="deleteMember(${m.id})">🗑️ Delete Application</button>
      </div>
    `;

    openModal('appDetail');
  }

  // ─── DELETE MEMBER APPLICATION ───
  function deleteMember(id) {
    if (!confirm('Are you sure you want to permanently delete membership application #' + id + '?')) {
      return;
    }

    fetch(`/memberships/${id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      }
    })
      .then(res => {
        if (!res.ok) throw new Error('Delete failed');
        return res.json();
      })
      .then(() => {
        showToast('error', 'Application #' + id + ' deleted', '🗑️');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(() => {
        showToast('error', 'Failed to delete application #' + id + '.', '❌');
      });
  }

  // ─── FILTER ───
  function filterMembers(status, el) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');

    document.querySelectorAll('.app-table tbody tr[data-status]').forEach(row => {
      row.style.display = (status === 'all' || row.getAttribute('data-status') === status) ? '' : 'none';
    });

    showToast('info', 'Filtered: ' + (status === 'all' ? 'All membership applications' : status), '🔍');
  }
</script>
</html>
