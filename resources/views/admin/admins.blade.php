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
    <div class="topbar-title" id="topbarTitle">Admin Users <span>/ System</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchAdminInput" placeholder="Search admins…" oninput="filterAdmins(this.value)" />
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
          <h2 class="panel-title">Admin Users</h2>
          <span class="records-count">Total Records: {{ $admins->count() }}</span>
        </div>
        <button class="btn-new" type="button" onclick="openModal('addAdmin')">+ Add Admin</button>
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>NAME</th>
              <th>EMAIL</th>
              <th>PHONE</th>
              <th>JOINED DATE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($admins as $admin)
              <tr data-id="{{ $admin->id }}">
                <td class="td-mono">#{{ $admin->id }}</td>
                <td class="applicant-name">{{ $admin->name }}</td>
                <td class="td-mono">{{ $admin->email }}</td>
                <td>{{ $admin->phone ?? 'N/A' }}</td>
                <td class="td-date">{{ $admin->created_at?->format('M d, Y') ?? '—' }}</td>
                <td>
                  <button class="btn-review" type="button" onclick="openAdminDetail({{ $admin->id }})">View</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;" onclick="editAdmin({{ $admin->id }})">Edit</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;color:var(--red);border-color:var(--red);" onclick="deleteAdmin({{ $admin->id }})">Delete</button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="empty-table-msg">No admin accounts found.</td>
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
  // ─── LIVE SEARCH ───
  function filterAdmins(q) {
    const term = q.trim().toLowerCase();
    document.querySelectorAll('.app-table tbody tr[data-id]').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = !term || text.includes(term) ? '' : 'none';
    });
  }

  // ─── ADMIN DETAIL MODAL ───
  function openAdminDetail(id) {
    closeAllDropdowns();
    const row = document.querySelector('.app-table tbody tr[data-id="' + id + '"]');
    if (!row) {
      showToast('error', 'Admin not found', '❌');
      return;
    }
    const cells = row.querySelectorAll('td');
    const name = cells[1].textContent.trim();
    const email = cells[2].textContent.trim();
    const phone = cells[3].textContent.trim();
    const joined = cells[4].textContent.trim();

    const titleEl = document.getElementById('adminDetailTitle');
    if (titleEl) titleEl.textContent = '#' + id + ' — ' + name;

    const bodyEl = document.getElementById('adminDetailBody');
    if (bodyEl) {
      const row2 = (label, value) => `
        <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">${label}</span>
          <span style="font-weight:600;">${value}</span>
        </div>`;

      bodyEl.innerHTML = `
        ${row2('Admin ID', '#' + id)}
        ${row2('Full Name', name)}
        ${row2('Email Address', email)}
        ${row2('Phone Number', phone)}
        ${row2('Joined Date', joined)}
        ${row2('Role', 'Administrator')}

        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          <button class="btn-review" type="button" style="width:100%;" onclick="editAdmin(${id})">✏️ Edit</button>
          <button class="btn-cancel" type="button" style="width:100%;color:var(--red);border-color:var(--red);background:rgba(239,68,68,0.05);" onclick="deleteAdmin(${id})">🗑️ Delete Account</button>
        </div>
      `;

      openModal('adminDetail');
    }
  }

  // ─── EDIT ADMIN ───
  function editAdmin(id) {
    const row = document.querySelector('.app-table tbody tr[data-id="' + id + '"]');
    if (!row) return;
    const cells = row.querySelectorAll('td');

    document.getElementById('editAdminId').value = id;
    document.getElementById('editAdminName').value = cells[1].textContent.trim();
    document.getElementById('editAdminEmail').value = cells[2].textContent.trim();
    document.getElementById('editAdminPhone').value = cells[3].textContent.trim() === 'N/A' ? '' : cells[3].textContent.trim();

    const form = document.getElementById('editAdminForm');
    form.action = '/admin/users/' + id;
    openModal('editAdmin');
  }

  // ─── SUBMIT ADD ADMIN ───
  function submitAddAdmin() {
    const form = new FormData(document.getElementById('addAdminForm'));
    const data = new URLSearchParams(form);

    fetch('/admin/users', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: data
    })
      .then(res => res.ok ? res.json() : res.json().then(Promise.reject))
      .then(() => {
        showToast('success', 'Admin account created', '✅');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(err => showToast('error', (err && err.message) || 'Failed to create admin.', '❌'));
  }

  // ─── SUBMIT EDIT ADMIN ───
  function submitEditAdmin() {
    const id = document.getElementById('editAdminId').value;
    const form = new FormData(document.getElementById('editAdminForm'));
    const data = new URLSearchParams(form);

    fetch('/admin/users/' + id, {
      method: 'PUT',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: data
    })
      .then(res => res.ok ? res.json() : res.json().then(Promise.reject))
      .then(() => {
        showToast('success', 'Admin account updated', '✅');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(err => showToast('error', (err && err.message) || 'Failed to update admin.', '❌'));
  }

  // ─── DELETE ADMIN ───
  function deleteAdmin(id) {
    if (!confirm('Are you sure you want to permanently delete this admin account?')) return;

    fetch('/admin/users/' + id, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      }
    })
      .then(res => res.ok ? res.json() : res.json().then(Promise.reject))
      .then(data => {
        if (data.success === false) {
          showToast('error', data.message || 'Failed to delete admin.', '❌');
          return;
        }
        showToast('error', 'Admin account deleted', '🗑️');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(err => showToast('error', (err && err.message) || 'Failed to delete admin.', '❌'));
  }
</script>

</body>
</html>