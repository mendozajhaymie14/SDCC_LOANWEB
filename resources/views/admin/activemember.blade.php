<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Admin Dashboard</title>
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
    <div class="topbar-title" id="topbarTitle">Active Members <span>/ Client Management</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchMembersInput" placeholder="Search members…" oninput="filterMembers(this.value)" />
      </div>
    </div>
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
          <h2 class="panel-title">Active Members</h2>
          <span class="records-count">Total Records: {{ $members->count() }}</span>
        </div>
        <button class="btn-new" type="button" onclick="openModal('addMember')">+ Add Member</button>
      </div>

      <div class="table-wrap">
        <table class="app-table" id="membersTable">
          <thead>
            <tr>
              <th>MEMBER ID</th>
              <th>NAME</th>
              <th>EMAIL</th>
              <th>STATUS</th>
              <th>MONTHLY INCOME</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($members as $member)
              <?php
                  // Get latest borrower record for monthly income
                  $latestBorrower = \App\Models\Borrowers::where('full_name', $member->full_name)
                      ->latest()
                      ->first();
                  $monthlyIncome = $latestBorrower ? number_format($latestBorrower->monthly_income, 2) : '';
              ?>
              <tr data-id="{{ $member->id }}">
                <td class="td-mono">#{{ $member->member_id }}</td>
                <td class="applicant-name">{{ $member->full_name }}</td>
                <td>{{ $member->user_id ? $member->email : '' }}</td>
                <td>
                  <span class="status-pill {{ $member->is_registered ? 'approved' : 'pending' }}">
  {{ $member->is_registered ? 'Approved' : 'Pending' }}
</span>
                </td>
                <td class="td-mono">
                  {{ $monthlyIncome ? '₱' . $monthlyIncome : '—' }}
                </td>
                <td>
                  <button class="btn-review" type="button" onclick="openActiveMemberDetail('{{ $member->id }}')">View</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;" onclick="editMember('{{ $member->id }}')">Edit</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;color:var(--red);border-color:var(--red);" onclick="deleteMember('{{ $member->id }}')">Delete</button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="empty-table-msg">No active members found.</td>
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

<script>
  function filterMembers(q) {
    const rows = document.querySelectorAll('#membersTable tbody tr[data-id]');
    const term = q.trim().toLowerCase();
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = !term || text.includes(term) ? '' : 'none';
    });
  }

  function editMember(id) {
    const row = document.querySelector('#membersTable tbody tr[data-id="' + id + '"]');
    if (!row) return;
    const cells = row.querySelectorAll('td');
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    set('#member_id', cells[0].textContent.trim().replace('#', ''));
    set('#name', cells[1].textContent.trim());
    set('#email', cells[2].textContent.trim());
    set('#status', cells[3].querySelector('.status-pill')?.textContent.trim() || 'pending');
    set('#income', cells[4].textContent.trim().replace(/[₱,]/g, '') === '—' ? '' : cells[4].textContent.trim().replace(/[₱,]/g, ''));
    openModal('addMember');
  }

  function deleteMember(id) {
    if (!confirm('Are you sure you want to permanently delete this member?')) return;
    fetch('/active-members/' + id, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
    })
      .then(res => res.ok ? res.json() : Promise.reject())
      .then(() => { showToast('error', 'Member deleted', '🗑️'); setTimeout(() => location.reload(), 700); })
      .catch(() => { showToast('error', 'Failed to delete member.', '❌'); });
  }
</script>

</body>
</html>