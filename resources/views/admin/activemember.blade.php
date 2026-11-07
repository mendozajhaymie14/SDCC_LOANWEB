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
        <span class="search-icon"><i class="fa-solid fa-magnifying-glass nav-icon"></i></span>
        <input type="text" id="searchMembersInput" placeholder="Search members…" oninput="filterMembers(this.value)" />
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
          <h2 class="panel-title">Active Members</h2>
          <span class="records-count">Approved Members: {{ $members->where('is_registered', true)->count() }}</span>
        </div>
        @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::EDIT_MEMBER_PROFILES))
        <button class="btn-new" type="button" onclick="resetAddForm(); openModal('addActiveMember')">+ Add Member</button>
        @endif
      </div>

      {{-- Flash messages (errors, success, warnings) --}}
      @if (session('success'))
        <div style="background:#d1f0c3;border:1px solid #84cc16;color:#22540c;padding:.75rem 1.25rem;border-radius:8px;margin:0 0 1rem 0;">
          {{ session('success') }}
        </div>
      @endif
      @if (session('warning'))
        <div style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;padding:.75rem 1.25rem;border-radius:8px;margin:0 0 1rem 0;">
          {{ session('warning') }}
        </div>
      @endif
      {{-- When an ADD fails the modal re-opens and shows the errors itself;
           show this banner for edit errors or when there is no old input. --}}
      @if ($errors->any() && (old('_method') === 'PUT' || empty(old())))
        <div style="background:#fecaca;border:1px solid #fca5a5;color:#991b1b;padding:.75rem 1.25rem;border-radius:8px;margin:0 0 1rem 0;">
          <strong>The member could not be saved:</strong>
          <ul style="margin:.4rem 0 0 1.2rem;padding:0;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

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
                  // Monthly income: the member's own record first, then the latest borrower record
                  $income = $member->monthly_income;
                  if ($income === null || $income === '') {
                      $latestBorrower = \App\Models\Borrowers::where('full_name', $member->full_name)
                          ->latest()
                          ->first();
                      $income = $latestBorrower?->monthly_income;
                  }
                  $monthlyIncome = ($income !== null && $income !== '' && (float) $income > 0)
                      ? number_format((float) $income, 2)
                      : '';
              ?>
              <tr data-id="{{ $member->id }}"
      data-member-id="{{ $member->member_id }}"
      data-full-name="{{ $member->full_name }}"
      data-email="{{ $member->email ?? $member->user?->email ?? '' }}"
      data-contact-number="{{ $member->contact_number ?? '' }}"
      data-date-of-birth="{{ $member->date_of_birth ?? '' }}"
      data-address="{{ $member->address ?? '' }}"
      data-occupation="{{ $member->occupation ?? '' }}"
      data-monthly-income="{{ $member->monthly_income ?? '' }}"
      data-existing-monthly-obligations="{{ $member->existing_monthly_obligations ?? '' }}"
      data-savings-balance="{{ $member->savings_balance ?? '' }}"
      data-approved-credit-limit="{{ $member->approved_credit_limit ?? '' }}"
      data-status="{{ $member->status ?? '' }}">
                <td class="td-mono">#{{ $member->member_id }}</td>
                <td class="applicant-name">{{ $member->full_name }}</td>
                <td>{{ $member->email ?? $member->user?->email ?? '' }}</td>
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
                  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::EDIT_MEMBER_PROFILES))
                  <button class="btn-review" type="button" style="margin-left:0.4rem;" onclick="editMember('{{ $member->id }}')">Edit</button>
                  <button class="btn-review" type="button" style="margin-left:0.4rem;color:var(--red);border-color:var(--red);" onclick="deleteMember('{{ $member->id }}')">Delete</button>
                  @endif
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
    const set = (sel, val) => { const el = document.querySelector(sel); if (el) el.value = val; };
    // Fill from data-attributes on the table row
    set('#addAMemberId', row.dataset.memberId);
    set('#addAMemberName', row.dataset.fullName);
    set('#addAMemberEmail', row.dataset.email);
    set('#addAMemberPhone', row.dataset.contactNumber);
    set('#addAMemberDateOfBirth', row.dataset.dateOfBirth);
    set('#addAMemberAddress', row.dataset.address);
    set('#addAMemberOccupation', row.dataset.occupation);
    set('#addAMemberMonthlyIncome', row.dataset.monthlyIncome);
    set('#addAMemberExistingMonthlyObligations', row.dataset.existingMonthlyObligations);
    set('#addAMemberSavingsBalance', row.dataset.savingsBalance);
    set('#addAMemberApprovedCreditLimit', row.dataset.approvedCreditLimit);
    set('#addAMemberExcludeId', id);

    // Switch form to PUT (edit) mode
    const form = document.getElementById('addActiveMemberForm');
    if (form) {
      form.action = '/active-members/' + id;
      const methodInput = document.getElementById('addAMemberMethod');
      if (methodInput) methodInput.value = 'PUT';
      const idInput = document.getElementById('addAMemberId');
      if (idInput) idInput.readOnly = true;
      const submitBtn = document.getElementById('addAMemberSubmitBtn');
      if (submitBtn) submitBtn.textContent = 'Update Member';
      const titleEl = document.getElementById('addActiveMemberTitle');
      if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-pen nav-icon"></i> Edit Member';
    }
    openModal('addActiveMember');
  }

  function deleteMember(id) {
    if (!confirm('Are you sure you want to permanently delete this member?')) return;
    fetch('/active-members/' + id, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
    })
      .then(res => res.ok ? res.json() : Promise.reject())
      .then(() => { showToast('error', 'Member deleted', '<i class="fa-solid fa-trash-can nav-icon"></i>'); setTimeout(() => location.reload(), 700); })
      .catch(() => { showToast('error', 'Failed to delete member.', '<i class="fa-solid fa-xmark nav-icon"></i>'); });
  }

  function resetAddForm() {
    const form = document.getElementById('addActiveMemberForm');
    if (!form) return;
    form.action = '{{ route('active-members.store') }}';
    const methodInput = document.getElementById('addAMemberMethod');
    if (methodInput) methodInput.value = '';
    const idInput = document.getElementById('addAMemberId');
    if (idInput) {
      idInput.readOnly = false;
      idInput.value = '';
    }
    const excludeIdInput = document.getElementById('addAMemberExcludeId');
    if (excludeIdInput) excludeIdInput.value = '';
    // Clear all form fields
    form.querySelectorAll('input, select, textarea').forEach(el => {
      // Never blank hidden inputs: they hold the CSRF _token (clearing it causes 419 Page Expired)
      if (el.type === 'hidden') return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = false;
      } else if (el.tagName === 'SELECT') {
        el.selectedIndex = 0;
      } else {
        el.value = '';
      }
    });
    // Reset select defaults
    const statusSelect = document.getElementById('addAMemberStatus');
    if (statusSelect) statusSelect.value = 'active';
    const isRegSelect = document.getElementById('addAMemberIsRegistered');
    if (isRegSelect) isRegSelect.value = '0';
    const submitBtn = document.getElementById('addAMemberSubmitBtn');
    if (submitBtn) submitBtn.textContent = 'Register Member';
    const titleEl = document.getElementById('addActiveMemberTitle');
    if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-user nav-icon"></i> Add New Member';
    // Clear validation states
    form.querySelectorAll('.fv-valid,.fv-invalid,.fv-warn').forEach(el => {
      el.classList.remove('fv-valid', 'fv-invalid', 'fv-warn');
      el.removeAttribute('aria-invalid');
      const msg = el._fvMsg;
      if (msg) msg.remove();
      el._fvMsg = null;
      el.removeAttribute('aria-describedby');
      el.style.borderColor = '';
    });
  }

  // ---- Member ID must look like SDCC-YYYY-0000 ----
  document.addEventListener('DOMContentLoaded', function () {
    const idInput = document.getElementById('addAMemberId');
    if (!idInput) return;
    const re = /^SDCC-(19|20)\d{2}-\d{4}$/;

    idInput.setAttribute('maxlength', '14');
    idInput.setAttribute('placeholder', 'SDCC-2026-0016');
    idInput.setAttribute('title', 'Format: SDCC-YYYY-0000 (example: SDCC-2026-0016)');

    function check() {
      idInput.value = idInput.value.toUpperCase().replace(/\s+/g, '');
      if (idInput.readOnly || idInput.value === '' || re.test(idInput.value)) {
        idInput.setCustomValidity('');
      } else {
        idInput.setCustomValidity('Use the format SDCC-YYYY-0000, for example SDCC-2026-0016.');
      }
    }
    idInput.addEventListener('input', check);
    idInput.addEventListener('blur', check);
  });

  // ---- Re-open the Add Member modal with the officer's input and the errors ----
  @if ($errors->any() && old('_method') !== 'PUT' && ! empty(old()))
  window.addEventListener('load', function () {
    const oldInput = @json(old());
    const messages = @json($errors->all());

    resetAddForm();
    openModal('addActiveMember');

    const form = document.getElementById('addActiveMemberForm');
    if (!form) return;

    // Put back what the officer typed
    Object.keys(oldInput).forEach(function (name) {
      if (name === '_token' || name === '_method') return;
      const el = form.querySelector('[name="' + name + '"]');
      if (el && el.type !== 'hidden') el.value = oldInput[name];
    });

    // Show the reasons at the top of the form
    const box = document.createElement('div');
    box.setAttribute('role', 'alert');
    box.style.cssText = 'background:#fecaca;border:1px solid #fca5a5;color:#991b1b;padding:.65rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.85rem;';
    const title = document.createElement('strong');
    title.textContent = 'The member could not be saved:';
    box.appendChild(title);
    const list = document.createElement('ul');
    list.style.cssText = 'margin:.35rem 0 0 1.1rem;padding:0;';
    messages.forEach(function (m) {
      const li = document.createElement('li');
      li.textContent = m;
      list.appendChild(li);
    });
    box.appendChild(list);
    form.insertBefore(box, form.firstChild);
  });
  @endif
</script>

</body>
</html>