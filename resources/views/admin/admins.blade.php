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
    <div class="topbar-title" id="topbarTitle">Staff Users <span>/ System</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchAdminInput" placeholder="Search staff…" oninput="filterAdmins(this.value)" />
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
    {{-- A generated password is shown here exactly once; it is never stored in clear text. --}}
    <div id="generatedPasswordBox" class="panel app-panel" style="display:none;border-left:4px solid var(--accent3);">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
        <div>
          <div style="font-weight:700;font-size:.95rem;">Temporary password (shown once)</div>
          <div style="font-size:.8rem;color:var(--muted);margin-top:.2rem;">Copy it now — it is not stored in plain text and cannot be retrieved later.</div>
        </div>
        <code id="generatedPasswordValue" style="font-size:1rem;font-weight:700;background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:.5rem .8rem;"></code>
      </div>
    </div>

    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Staff Users</h2>
          <span class="records-count">Total Records: {{ $admins->count() }}</span>
        </div>
        @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::MANAGE_USERS))
        <button class="btn-new" type="button" onclick="openModal('addAdmin')">+ Add Staff</button>
        @endif
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>NAME</th>
              <th>EMAIL</th>
              <th>PHONE</th>
              <th>ROLE</th>
              <th>JOINED DATE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($admins as $admin)
              @php
                $roleKey = \App\Support\Permissions::role($admin);
                $roleLabel = \App\Support\Permissions::label($roleKey);
              @endphp
              <tr data-id="{{ $admin->id }}">
                <td class="td-mono">#{{ $admin->id }}</td>
                <td class="applicant-name">{{ $admin->name }}</td>
                <td class="td-mono">{{ $admin->email }}</td>
                <td>{{ $admin->phone ?? 'N/A' }}</td>
                <td data-role="{{ $roleKey }}">{{ $roleLabel }}</td>
                <td class="td-date">{{ $admin->created_at?->format('M d, Y') ?? '—' }}</td>
                <td>
                  <button class="btn-review" type="button" onclick="openAdminDetail({{ $admin->id }})">View</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;" onclick="editAdmin({{ $admin->id }})">Edit</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;color:var(--red);border-color:var(--red);" onclick="deleteAdmin({{ $admin->id }})">Delete</button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="empty-table-msg">No staff accounts found.</td>
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

  // ─── GENERATE PASSWORD FOR ME ───
  // The password is generated on the server (hashed before it is stored) and
  // returned once. So "generate for me" just means "leave it blank".
  function generatePasswordForMe() {
    const field = document.getElementById('addAdminPassword');
    if (field) {
      field.value = '';
      field.placeholder = 'A password will be generated and shown once';
    }
  }

  // ─── STAFF DETAIL MODAL ───
  function openAdminDetail(id) {
    closeAllDropdowns();
    const row = document.querySelector('.app-table tbody tr[data-id="' + id + '"]');
    if (!row) {
      showToast('error', 'Staff account not found', '❌');
      return;
    }
    const cells = row.querySelectorAll('td');
    const name = cells[1].textContent.trim();
    const email = cells[2].textContent.trim();
    const phone = cells[3].textContent.trim();
    const role = cells[4].textContent.trim();
    const joined = cells[5].textContent.trim();

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
        ${row2('Staff ID', '#' + id)}
        ${row2('Full Name', name)}
        ${row2('Email Address', email)}
        ${row2('Phone Number', phone)}
        ${row2('Role', role)}
        ${row2('Joined Date', joined)}

        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          <button class="btn-review" type="button" style="width:100%;" onclick="editAdmin(${id})">✏️ Edit</button>
          <button class="btn-cancel" type="button" style="width:100%;color:var(--red);border-color:var(--red);background:rgba(239,68,68,0.05);" onclick="deleteAdmin(${id})">🗑️ Delete Account</button>
        </div>
      `;

      openModal('adminDetail');
    }
  }

  // ─── EDIT STAFF ───
  function editAdmin(id) {
    const row = document.querySelector('.app-table tbody tr[data-id="' + id + '"]');
    if (!row) return;
    const cells = row.querySelectorAll('td');

    document.getElementById('editAdminId').value = id;
    document.getElementById('editAdminName').value = cells[1].textContent.trim();
    document.getElementById('editAdminEmail').value = cells[2].textContent.trim();
    document.getElementById('editAdminPhone').value = cells[3].textContent.trim() === 'N/A' ? '' : cells[3].textContent.trim();
    const roleSelect = document.getElementById('editAdminRole');
    if (roleSelect) roleSelect.value = cells[4].getAttribute('data-role') || '';

    const form = document.getElementById('editAdminForm');
    form.action = '/admin/users/' + id;
    openModal('editAdmin');
  }

  // ─── SUBMIT ADD STAFF ───
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
      .then(json => {
        showToast('success', 'Staff account created', '✅');
        closeAllModals();
        // A generated password is returned exactly once and never stored in
        // clear text — surface it here, then let the page reload.
        if (json && json.password) {
          const box = document.getElementById('generatedPasswordBox');
          const val = document.getElementById('generatedPasswordValue');
          if (box && val) {
            val.textContent = json.password;
            box.style.display = 'block';
          }
          setTimeout(() => location.reload(), 6000);
          return;
        }
        setTimeout(() => location.reload(), 700);
      })
      .catch(err => showToast('error', (err && err.message) || 'Failed to create staff account.', '❌'));
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

  // ─── DELETE STAFF ───
  function deleteAdmin(id) {
    if (!confirm('Are you sure you want to permanently delete this staff account?')) return;

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
          showToast('error', data.message || 'Failed to delete staff account.', '❌');
          return;
        }
        showToast('error', 'Staff account deleted', '🗑️');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(err => showToast('error', (err && err.message) || 'Failed to delete staff account.', '❌'));
  }
</script>

</body>
</html>