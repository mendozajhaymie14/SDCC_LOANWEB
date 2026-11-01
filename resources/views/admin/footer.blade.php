@php
    // Shared admin chrome. Every modal below posts to a route the viewing role
    // may not own, so each one is rendered only when its permission is held.
    $perm = fn (string $p) => \App\Support\Permissions::has(auth()->user(), $p);
@endphp

<script>
    // Client-side flags for the JS-built modals. The server enforces the same
    // permissions; these only stop a role from being shown a button that 403s.
    window.__memberPermissions = {
        edit: {{ $perm(\App\Support\Permissions::EDIT_MEMBER_PROFILES) ? 'true' : 'false' }},
    };

    // ─── DUPLICATE MEMBER LIVE CHECK ───
    // Blur on name/email/contact calls POST /admin/check-duplicate.
    // Red error for email/contact match; amber warning for name-only match.
    // Blocks submit on error. exclude_id avoids flagging the edited member.
    // Single source of truth for the whole footer: this file has two <script>
    // blocks sharing one global scope, so declaring it twice is a SyntaxError
    // that kills the second block (modals, notifications, etc.).
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
      || document.querySelector('input[name="_token"]')?.value
      || @json(csrf_token());

    function setupDuplicateCheck(config) {
      const { form, nameFields, emailEl, contactEl, excludeIdEl } = config;

      let hasError = false;

      function getFullName() {
        return nameFields
          .map(f => (f ? f.value.trim() : ''))
          .filter(Boolean)
          .join(' ');
      }

      function getExcludeId() {
        if (!excludeIdEl) return null;
        const v = excludeIdEl.value;
        return v ? parseInt(v, 10) : null;
      }

      function clearMsg(fieldEl) {
        const parent = fieldEl.parentElement;
        if (parent) parent.querySelectorAll('.dup-check-msg').forEach(m => m.remove());
        fieldEl.style.borderColor = '';
      }

      function showMessage(fieldEl, msg, type) {
        const parent = fieldEl.parentElement;
        if (!parent) return;
        parent.querySelectorAll('.dup-check-msg').forEach(m => m.remove());
        const div = document.createElement('div');
        div.className = 'dup-check-msg';
        div.style.cssText = 'margin-top:4px; font-size:0.75rem; padding:4px 8px; border-radius:4px;';
        if (type === 'error') {
          div.style.color = '#b91c1c'; div.style.background = '#fef2f2'; div.style.border = '1px solid #fca5a5';
          fieldEl.style.borderColor = '#ef4444';
        } else {
          div.style.color = '#92400e'; div.style.background = '#fffbeb'; div.style.border = '1px solid #f59e0b';
        }
        div.textContent = msg;
        parent.appendChild(div);
      }

      function checkField(fieldEl) {
        const fullName = getFullName();
        const email = emailEl ? emailEl.value.trim() : '';
        const contact = contactEl ? contactEl.value.trim() : '';
        if (!fullName && !email && !contact) { clearMsg(fieldEl); return; }
        const body = new URLSearchParams();
        body.append('full_name', fullName);
        body.append('email', email);
        body.append('phone', contact);
        const excl = getExcludeId();
        if (excl) body.append('exclude_id', excl);
        body.append('_token', csrfToken);
        fetch('/admin/check-duplicate', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: body,
        })
          .then(r => r.json())
          .then(result => {
            if (result.is_duplicate) { hasError = true; showMessage(fieldEl, result.message, 'error'); }
            else if (result.is_warning) { showMessage(fieldEl, result.message, 'warning'); }
            else { clearMsg(fieldEl); }
          })
          .catch(() => {});
      }

      const tracked = [emailEl, contactEl, ...nameFields].filter(Boolean);
      tracked.forEach(el => el.addEventListener('blur', () => checkField(el)));

      form.addEventListener('submit', function (e) {
        if (hasError) {
          e.preventDefault();
          if (typeof showToast === 'function') showToast('error', 'Please fix the duplicate member error before submitting.', '❌');
        }
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      const addAMemberForm = document.querySelector('#modal-addActiveMember form');
      if (addAMemberForm) {
        setupDuplicateCheck({
          form: addAMemberForm,
          nameFields: [document.getElementById('addAMemberName')],
          emailEl: document.getElementById('addAMemberEmail'),
          contactEl: document.getElementById('addAMemberPhone'),
          excludeIdEl: document.getElementById('addAMemberExcludeId'),
        });
      }
      const addMAppForm = document.querySelector('#modal-addMember form');
      if (addMAppForm) {
        setupDuplicateCheck({
          form: addMAppForm,
          nameFields: [
            document.getElementById('addMAppFirstName'),
            document.getElementById('addMAppMiddleName'),
            document.getElementById('addMAppSurname'),
          ],
          emailEl: document.getElementById('addMAppEmail'),
          contactEl: document.getElementById('addMAppContact'),
          excludeIdEl: document.getElementById('addMAppExcludeId'),
        });
      }
    });
</script>

<!-- PROFILE & SETTINGS DROPDOWN -->
<div class="profile-dropdown" id="profileDropdown">
    @if($perm(\App\Support\Permissions::MANAGE_USERS))
    <a href="{{ route('admins.index') }}" class="pd-item">👥 Staff Users</a>
    <div class="pd-divider"></div>
    @endif
    <div class="pd-item danger" onclick="confirmLogout()">🚪 Log Out</div>

    <!-- Hidden form for Laravel authentication -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- OVERLAY CONTAINER -->
<div class="overlay" id="overlay" onclick="closeAllModals()">

  <!-- SHARED APPLICATION DETAIL MODAL -->
  @include('admin.partials.application-modal')

  <!-- New Loan Application Modal -->
  @if($perm(\App\Support\Permissions::ENCODE_APPLICATIONS))
  <div class="modal" id="modal-newApp" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">📋 New Loan Application</div>
      <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <form action="{{ route('applications.store') }}" method="POST">
      @csrf
      <div class="form-group">
        <label class="form-label">Applicant Name</label>
        <input type="text" name="applicant" placeholder="e.g. Maria Reyes" required class="form-input"/>
      </div>
      <div class="form-group">
        <label class="form-label">Loan Type</label>
        <select name="loan_type" required class="form-select">
          <option value="">Select Loan Type</option>
          <option value="Personal Loan">Personal Loan</option>
          <option value="Business Loan">Business Loan</option>
          <option value="Salary Loan">Salary Loan</option>
          <option value="Home Loan">Home Loan</option>
          <option value="Auto Loan">Auto Loan</option>
          <option value="Emergency Loan">Emergency Loan</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Loan Amount (₱)</label>
        <input type="number" step="0.01" name="amount" placeholder="150000" required class="form-input" data-validate="positive"/>
      </div>
      <button class="btn-submit" type="submit">Submit Application</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>
  @endif

  <!-- Active Member Detail / Activate-Suspend-Delete Modal -->
  <div class="modal" id="modal-activeMemberDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="activeMemberDetailTitle">Member Detail</div>
      <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <div id="activeMemberDetailBody"></div>
  </div>

  <!-- 2. Add Active Member Modal -->
  @if($perm(\App\Support\Permissions::EDIT_MEMBER_PROFILES))
  <div class="modal" id="modal-addActiveMember" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">👤 Add New Member</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <form action="{{ route('active-members.store') }}" method="POST">
      @csrf

      <div class="form-group">
        <label class="form-label">Member ID</label>
        <input type="text" name="member_id" id="addAMemberId" class="form-input" placeholder="e.g. SDCC-2024-0001" required/>
      </div>

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" id="addAMemberName" class="form-input" placeholder="e.g. Juan Santos" required/>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" id="addAMemberEmail" class="form-input" placeholder="juan@email.com" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" id="addAMemberPhone" class="form-input" placeholder="+63 9XX XXX XXXX" data-validate="phone"/>
        </div>
      </div>

      <input type="hidden" name="exclude_id" id="addAMemberExcludeId" value=""/>

      <div class="form-group">
        <label class="form-label">Date of Birth</label>
        <input type="date" name="date_of_birth" class="form-input" required/>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Website Account</label>
          <select name="is_registered" class="form-select">
            <option value="0">No</option>
            <option value="1">Yes</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>

      <button class="btn-submit" type="submit">Register Member</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>
  @endif

  <!-- 2d. Add Staff Modal -->
  @if($perm(\App\Support\Permissions::MANAGE_USERS))
  <div class="modal" id="modal-addAdmin" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">👤 Add Staff User</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <form id="addAdminForm" action="/admin/users" method="POST">
      @csrf
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" id="addAdminName" class="form-input" placeholder="e.g. Juan Santos" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" id="addAdminEmail" class="form-input" placeholder="juan@email.com" required/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" id="addAdminPhone" class="form-input" placeholder="+63 9XX XXX XXXX" data-validate="phone"/>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" id="addAdminRole" class="form-select" required>
            @foreach(\App\Support\Permissions::STAFF_ROLES as $staffRole)
              <option value="{{ $staffRole }}">{{ \App\Support\Permissions::label($staffRole) }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" id="addAdminPassword" class="form-input" placeholder="Leave blank to generate one"/>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:.6rem;margin-top:.35rem;flex-wrap:wrap;">
          <span style="font-size:.75rem;color:var(--muted);">Min. 8 characters. Leave blank and the system generates one.</span>
          <button class="btn-review" type="button" onclick="generatePasswordForMe()">Generate for me</button>
        </div>
      </div>
      <button class="btn-submit" type="button" onclick="submitAddAdmin()">Create Staff Account</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>
  @endif

  <!-- 2e. Edit Staff Modal -->
  @if($perm(\App\Support\Permissions::MANAGE_USERS))
  <div class="modal" id="modal-editAdmin" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Staff User</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <form id="editAdminForm" method="POST" action="">
      @csrf
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" id="editAdminId" name="id" value="">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" id="editAdminName" name="name" class="form-input" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" id="editAdminEmail" name="email" class="form-input" required/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" id="editAdminPhone" name="phone" class="form-input" placeholder="+63 9XX XXX XXXX" data-validate="phone"/>
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" id="editAdminRole" class="form-select" required>
            @foreach(\App\Support\Permissions::STAFF_ROLES as $staffRole)
              <option value="{{ $staffRole }}">{{ \App\Support\Permissions::label($staffRole) }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="password" id="editAdminPassword" class="form-input" placeholder="Leave blank to keep current password"/>
      </div>

      <button class="btn-submit" type="button" onclick="submitEditAdmin()">Save Changes</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>
  @endif

  <!-- 2f. Staff Detail Modal -->
  @if($perm(\App\Support\Permissions::MANAGE_USERS))
  <div class="modal" id="modal-adminDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="adminDetailTitle">Staff Detail</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <div id="adminDetailBody"></div>
  </div>
  @endif

  <!-- 2c2. Member Detail Modal -->
  @if($perm(\App\Support\Permissions::APPROVE_MEMBERSHIPS))
  <div class="modal" id="modal-memberDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="memberDetailTitle">Membership Application Detail</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <div id="memberDetailBody"></div>
  </div>
  @endif

  <!-- 2c. Edit Membership Application Modal -->
  @if($perm(\App\Support\Permissions::APPROVE_MEMBERSHIPS))
  <div class="modal" id="modal-editMember" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Membership Application</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <form id="editMemberForm" method="POST" action="">
      @csrf
      @method('PUT')
      <input type="hidden" name="_method" value="PUT">
      <input type="hidden" id="editMemberId" name="id" value="">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input type="text" id="editFirstName" name="first_name" class="form-input" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Middle Name</label>
          <input type="text" id="editMiddleName" name="middle_name" class="form-input"/>
        </div>
        <div class="form-group">
          <label class="form-label">Surname</label>
          <input type="text" id="editSurname" name="surname" class="form-input" required/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" id="editEmail" name="email" class="form-input" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Contact Number</label>
          <input type="text" id="editContact" name="contact_number" class="form-input" data-validate="phone"/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">TIN</label>
          <input type="text" id="editTin" name="tin" class="form-input"/>
        </div>
        <div class="form-group">
          <label class="form-label">Nationality</label>
          <input type="text" id="editNationality" name="nationality" class="form-input"/>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Place of Birth</label>
          <input type="text" id="editPlaceOfBirth" name="place_of_birth" class="form-input"/>
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select id="editGender" name="gender" class="form-select">
            <option value="">—</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Occupation</label>
          <input type="text" id="editOccupation" name="occupation" class="form-input"/>
        </div>
        <div class="form-group">
          <label class="form-label">Civil Status</label>
          <select id="editCivilStatus" name="civil_status" class="form-select">
            <option value="">—</option>
            <option value="single">Single</option>
            <option value="married">Married</option>
            <option value="divorced">Divorced</option>
            <option value="widowed">Widowed</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Residency Type</label>
        <select id="editResidency" name="residency_type" class="form-select">
          <option value="">—</option>
          <option value="renter">Renter</option>
          <option value="homeowner">Homeowner</option>
          <option value="family_home">With Family</option>
        </select>
      </div>

      <div style="margin-top:1rem;">
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:.5rem;">Permanent Address</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">House No.</label>
            <input type="text" id="editPermHouseNo" name="perm_house_no" class="form-input"/>
          </div>
          <div class="form-group">
            <label class="form-label">Street</label>
            <input type="text" id="editPermStreet" name="perm_street" class="form-input"/>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Barangay</label>
            <input type="text" id="editPermBarangay" name="perm_barangay" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Municipality</label>
            <input type="text" id="editPermMunicipality" name="perm_municipality" class="form-input"/>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Zip Code</label>
            <input type="text" id="editPermZipCode" name="perm_zip_code" class="form-input"/>
          </div>
          <div class="form-group">
            <label class="form-label">Stay (Years)</label>
            <input type="number" id="editPermStayYears" name="perm_stay_years" class="form-input" min="0"/>
          </div>
          <div class="form-group">
            <label class="form-label">Stay (Months)</label>
            <input type="number" id="editPermStayMonths" name="perm_stay_months" class="form-input" min="0" max="11"/>
          </div>
        </div>
      </div>

      <button class="btn-submit" type="button" onclick="submitEditMember()">Save Changes</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>
  @endif

  <!-- 2b. Add Membership Application Modal -->
  @if($perm(\App\Support\Permissions::APPROVE_MEMBERSHIPS))
  <div class="modal" id="modal-addMember" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">📋 Add Membership Application</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <form id="addMemberForm" action="/memberships" method="POST">
      @csrf
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input type="text" name="first_name" id="addMAppFirstName" class="form-input" placeholder="e.g. Juan" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" id="addMAppMiddleName" class="form-input" placeholder="e.g. Dela"/>
        </div>
        <div class="form-group">
          <label class="form-label">Surname</label>
          <input type="text" name="surname" id="addMAppSurname" class="form-input" placeholder="e.g. Cruz" required/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" id="addMAppEmail" class="form-input" placeholder="juan@email.com" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact_number" id="addMAppContact" class="form-input" placeholder="+63 9XX XXX XXXX" data-validate="phone"/>
        </div>
      </div>

      <input type="hidden" name="exclude_id" id="addMAppExcludeId" value=""/>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">TIN</label>
          <input type="text" name="tin" class="form-input" placeholder="1234567890"/>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
      <button class="btn-submit" type="submit">Save Application</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>
  @endif

  <!-- 3. Generate Report Modal -->
  @if($perm(\App\Support\Permissions::VIEW_REPORTS))
  <div class="modal" id="modal-generateReport" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">📊 Generate Report</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <div class="form-group">
      <label class="form-label">Report Type</label>
      <select class="form-select">
        <option>Monthly Summary</option>
        <option>Quarterly Report</option>
        <option>Annual Report</option>
        <option>Disbursement Report</option>
        <option>Repayment Report</option>
        <option>Risk Report</option>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">From Date</label><input class="form-input" type="date"/></div>
      <div class="form-group"><label class="form-label">To Date</label><input class="form-input" type="date"/></div>
    </div>
    <div class="form-group">
      <label class="form-label">Export Format</label>
      <select class="form-select">
        <option>PDF</option>
        <option>Excel (.xlsx)</option>
        <option>CSV</option>
      </select>
    </div>
    <button class="btn-submit" type="button" onclick="submitReport()">Generate & Download</button>
    <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
  </div>
  @endif

</div>

<script>
  // ─── DATA ───
  const apps = {
    @foreach (\App\Models\Application::with('loanApplication')->latest()->get() as $a)
      @php
        $aiScore = $a->ai_score ?? ($a->loanApplication ? $a->loanApplication->ai_score : null);
        $docs = $a->loanApplication ? ($a->loanApplication->documents ?? []) : [];
        $aiNotes = $a->loanApplication ? ($a->loanApplication->ai_analysis_notes ?? null) : null;
      @endphp
      "{{ $a->id }}": {
        name: @json($a->applicant),
        type: @json($a->loan_type),
        amount: @json('₱' . number_format($a->amount, 2)),
        score: {{ $aiScore ?? 'null' }},
        status: @json(strtolower($a->status)),
        date: @json(optional($a->created_at)->format('M d, Y')),
        documents: @json($docs),
        ai_notes: @json($aiNotes ?? null)
      },
    @endforeach
  };

  const activeMembersData = {
    @foreach (\App\Models\CoopMember::with('user')->latest()->get() as $m)
      @php
        $latestBorrower = \App\Models\Borrowers::where('full_name', $m->full_name)->latest()->first();
        $income = $latestBorrower ? number_format($latestBorrower->monthly_income, 2) : '';
        $aiScore = $latestBorrower ? $latestBorrower->ai_credit_score : null;
        $rawScore = $aiScore !== null ? round((($aiScore - 300) * 100) / 550) : null;
      @endphp
      "{{ $m->id }}": {
        id: @json($m->id),
        member_id: @json($m->member_id),
        name: @json($m->full_name),
        email: @json($m->email),
        income: @json($income),
        aiScore: @json($rawScore),
        status: @json(strtolower($m->status)),
        is_registered: @json($m->is_registered),
        date: @json(optional($m->created_at)->format('M d, Y'))
      },
    @endforeach
  };

  const notifications = [
    @foreach (\App\Models\AppNotification::staffTypes()->latest()->take(8)->get() as $n)
      @php
        $userName  = $n->data['applicant_name'] ?? $n->data['user_name'] ?? ($n->applicant ?? '');
        $appNumber = $n->data['application_number'] ?? $n->data['loan_id'] ?? ($n->app_id ?? '');
        $prefix    = is_numeric($appNumber) ? 'APP-' : '';
        $type      = $n->type;
        // Determine icon and color by notification type
        $icon      = match ($type) {
          'new_loan_application'   => 'N',
          'new_membership_application' => 'M',
          'reminder_failed'        => '!',
          'reminder_summary'       => 'S',
          default                  => 'i',
        };
        $bgColor   = match ($type) {
          'new_loan_application'   => 'rgba(52,211,153,0.15)',
          'new_membership_application' => 'rgba(99,102,241,0.15)',
          'reminder_failed'        => 'rgba(248,113,113,0.15)',
          'reminder_summary'       => 'rgba(251,191,36,0.15)',
          default                  => 'rgba(107,114,128,0.15)',
        };
        $textColor = match ($type) {
          'new_loan_application'   => 'var(--accent3)',
          'new_membership_application' => 'var(--accent2)',
          'reminder_failed'        => 'var(--red)',
          'reminder_summary'       => 'var(--accent)',
          default                  => 'var(--muted)',
        };
        // Build link to related page
        $link      = '';
        $linkText  = '';
        if ($type === 'new_loan_application' && $n->loan_application_id) {
          $link = `{{ route('applications.show', $n->loan_application_id) }}`;
          $linkText = 'View Application';
        } elseif ($type === 'new_membership_application' && $n->loan_application_id) {
          $link = `{{ route('members.show', $n->loan_application_id) }}`;
          $linkText = 'View Member';
        } elseif ($n->data['application_number']) {
          $link = `{{ route('applications.show', $n->data['application_number']) }}`;
          $linkText = 'View Application';
        }
      @endphp
      {
        type: @json($type),
        message: @json($n->message),
        appId: @json($prefix . $appNumber),
        applicant: @json($userName),
        time: @json($n->created_at->diffForHumans()),
        unread: {{ $n->read_at ? 'false' : 'true' }},
        link: @json($link),
        linkText: @json($linkText),
        icon: @json($icon),
        bgColor: @json($bgColor),
        textColor: @json($textColor),
      },
    @endforeach
  ];
  const unreadNotifCount = {{ \App\Models\AppNotification::staffTypes()->whereNull('read_at')->count() }};

  const statusColor = {approved:'var(--accent3)',pending:'var(--gold)',review:'var(--accent)',rejected:'var(--red)'};
  const scoreColor = s => s>=51?'var(--accent3)':s>=0?'var(--red)':'var(--muted)';

  function documentsSection(documents) {
    if (!documents || documents.length === 0) {
      return `<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
        <span style="color:var(--muted);">Uploaded Documents</span>
        <span style="color:var(--muted);font-style:italic;">No attached documents found.</span>
      </div>`;
    }

    const badges = documents.map(doc => {
      const fileName = doc.split('/').pop();
      return `<span style="display:inline-flex;align-items:center;gap:0.2rem;background:#f8fafc;color:#1e293b;border:1px solid #cbd5e1;border-radius:6px;padding:0.35rem 0.65rem;font-size:0.75rem;margin:0.2rem;">
        📄 <a href="/storage/${doc}" target="_blank" style="color:inherit;text-decoration:none;">${fileName}</a>
      </span>`;
    }).join('');

    return `<div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
      <span style="color:var(--muted);">Uploaded Documents</span>
      <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">${badges}</div>
    </div>`;
  }

  // ─── PAGE SWITCHING ───
  function switchPage(id, el) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const pg = document.getElementById('page-' + id);
    if (pg) pg.classList.add('active');
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if (el) el.classList.add('active');
    
    const titles = {
      dashboard:'Dashboard', analytics:'Analytics', applications:'All Applications',
      pending:'Pending Review', approved:'Approved Loans', rejected:'Rejected',
      repayments:'Repayments', members:'Active Members',
      credit:'Credit Assessment', risk:'Risk Flags', settings:'Settings',
      reports:'Reports'
    };
    
    const topbarTitle = document.getElementById('topbarTitle');
    if (topbarTitle) {
      topbarTitle.innerHTML = (titles[id] || id) + ' <span>/ ' + (id === 'dashboard' ? 'Overview' : id.charAt(0).toUpperCase() + id.slice(1)) + '</span>';
    }
    closeAllDropdowns();
  }

  // ─── DELETE APPLICATION ACTION ───
  function deleteApp(id) {
    if (!confirm(`Are you sure you want to permanently delete application #${id}?`)) {
      return;
    }

    fetch(`/applications/${id}`, {
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
        showToast('error', `Application #${id} deleted`, '🗑️');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(() => {
        showToast('error', `Failed to delete application #${id}.`, '❌');
      });
  }

  // ─── ACTIVE MEMBER DETAIL MODAL ───
  function openActiveMemberDetail(id) {
    closeAllDropdowns();
    const mp = window.__memberPermissions || { edit: false };
    const m = activeMembersData[id];
    if (!m) {
      showToast('error', 'Member record not found', '❌');
      return;
    }

    const statusLabel = m.status.charAt(0).toUpperCase() + m.status.slice(1);
    const scoreC = m.aiScore != null ? (m.aiScore >= 600 ? '#10b981' : '#ef4444') : 'var(--muted)';

    const titleEl = document.getElementById('activeMemberDetailTitle');
    if (titleEl) titleEl.textContent = '#' + m.member_id + ' — ' + m.name;

    const bodyEl = document.getElementById('activeMemberDetailBody');
    if (bodyEl) {
      const row = (label, value) => `
        <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">${label}</span>
          <span style="font-weight:600;">${value}</span>
        </div>`;

      // Compute Member Financial Profile data
      const memberApps = Object.values(apps).filter(a => a.name === m.name);
      const activeLoans = memberApps.filter(a => a.status !== 'rejected');
      const totalLoans = activeLoans.length;
      const totalBalance = activeLoans.reduce((sum, a) => sum + (parseFloat(a.amount?.replace(/[₱,]/g, '')) || 0), 0);

      bodyEl.innerHTML = `
        ${row('Member ID', m.member_id)}
        ${row('Full Name', m.name)}
        ${row('Email Address', m.email)}
        ${row('Monthly Income', `<span style="font-family:'DM Sans',sans-serif;">${m.income || '₱0.00'}</span>`)}
        ${row('Rule-Based Score', `<span style="font-family:'DM Sans',sans-serif;color:${scoreC};">${m.aiScore ?? 'N/A'}</span>`)}
        ${row('Date Joined', m.date)}
        ${row('Status', `<span class="status-pill ${m.status}"><span class="status-dot"></span>${statusLabel}</span>`)}

        <!-- Member Financial Profile -->
        <div style="margin-top: 1rem; padding: 0.8rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
          <div style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 0.4rem;">Member Financial Profile</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; font-size: 0.85rem;">
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 0.2rem;">Verified Monthly Income</div>
              <div style="font-family:'DM Sans',sans-serif;">${m.income || '₱0.00'}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 0.2rem;">Total Active Loans</div>
              <div style="font-family:'DM Sans',sans-serif;">${totalLoans} ${totalLoans === 1 ? 'loan' : 'loans'}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 0.2rem;">Total Remaining Balance</div>
              <div style="font-family:'DM Sans',sans-serif;">${totalBalance > 0 ? '₱' + Number(totalBalance).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00'}</div>
            </div>
            <div>
              <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); margin-bottom: 0.2rem;">Payment Reliability</div>
              <div style="font-family:'DM Sans',sans-serif;">${totalLoans > 0 ? `${totalLoans} ${totalLoans === 1 ? 'repaid' : 'repayments'}` : 'No repayments recorded'}</div>
            </div>
          </div>
        </div>

        <!-- Recent Loan Applications -->
        <div style="margin-top: 1rem;">
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:.5rem;">Recent Loan Applications</div>
          <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
            <thead>
              <tr style="border-bottom: 1px solid #eaecf0;">
                <th style="padding: 0.4rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Rule-Based Score</th>
                <th style="padding: 0.4rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">App ID</th>
                <th style="padding: 0.4rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Borrower</th>
                <th style="padding: 0.4rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Status</th>
              </tr>
            </thead>
            <tbody>
              ${activeLoans.slice(0,5).map(app => {
                const appStatus = app.status.charAt(0).toUpperCase() + app.status.slice(1);
                const rowScoreC = app.score != null ? (app.score >= 51 ? '#10b981' : '#ef4444') : 'var(--muted)';
                return `
                  <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 0.4rem 0.5rem; font-size: 0.8rem; color: #344054;">
                      <span style="font-family:'DM Sans',sans-serif;color:${rowScoreC};">${app.score ?? 'N/A'}</span>
                    </td>
                    <td style="padding: 0.4rem 0.5rem; font-size: 0.8rem; color: #344054;">${app.app_id || 'N/A'}</td>
                    <td style="padding: 0.4rem 0.5rem; font-size: 0.8rem; color: #344054;">${app.name || app.applicant || 'N/A'}</td>
                    <td style="padding: 0.4rem 0.5rem; font-size: 0.8rem; color: #344054;">
                      <span class="status-pill ${app.status}"><span class="status-dot"></span>${appStatus}</span>
                    </td>
                  </tr>`;
              }).join('') || '<tr><td colspan="4" style="text-align:center;padding:1rem;color:var(--muted);">No loan applications found.</td></tr>'}
            </tbody>
          </table>
        </div>

        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          ${(mp.edit && (m.status === 'inactive' || m.status === 'suspended')) ? `
            <form id="activate-form-${m.id}" action="/active-members/${m.id}/approve" method="POST" style="display:none;">
              <input type="hidden" name="_token" value="${csrfToken}">
            </form>
            <form id="suspend-form-${m.id}" action="/active-members/${m.id}/reject" method="POST" style="display:none;">
              <input type="hidden" name="_token" value="${csrfToken}">
            </form>
            <div style="display:flex;gap:.7rem;">
              <button class="btn-cancel" type="button" style="width:auto;flex:1;color:var(--red);border-color:var(--red);" onclick="document.getElementById('suspend-form-${m.id}').submit()">✗ Suspend</button>
              <button class="btn-submit" type="button" style="width:auto;flex:1;" onclick="document.getElementById('activate-form-${m.id}').submit()">✓ Activate</button>
            </div>` : ''
          }
          ${mp.edit ? `<button class="btn-cancel" type="button" style="width:100%;color:var(--red);border-color:var(--red);background:rgba(239,68,68,0.05);" onclick="deleteActiveMember('${m.id}')">🗑️ Delete Member</button>` : ''}
        </div>
      `;
    }

    openModal('activeMemberDetail');
  }

  // ─── DELETE ACTIVE MEMBER ACTION ───
  function deleteActiveMember(id) {
    if (!confirm('Are you sure you want to permanently delete this member?')) {
      return;
    }

    fetch(`/active-members/${id}`, {
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
        showToast('error', 'Member deleted', '🗑️');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(() => {
        showToast('error', 'Failed to delete member.', '❌');
      });
  }

  function closeDrawer() {
    closeAllModals();
  }

  // ─── NOTIFICATIONS ───
  let notifOpen = false;
  function toggleNotif() {
    notifOpen = !notifOpen;
    const notifDropdown = document.getElementById('notifDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (notifDropdown) notifDropdown.classList.toggle('open', notifOpen);
    if (profileDropdown) profileDropdown.classList.remove('open');
  }

  function renderNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    if (!dropdown) return;

    dropdown.querySelectorAll('.notif-item, .notif-empty').forEach(el => el.remove());

    if (!notifications.length) {
      const empty = document.createElement('div');
      empty.className = 'notif-empty';
      empty.style.cssText = 'padding:2rem 1.2rem;text-align:center;color:var(--muted);font-size:0.85rem;';
      empty.textContent = 'No notifications yet.';
      dropdown.appendChild(empty);
    } else {
      notifications.forEach(n => {
        const item = document.createElement('div');
        item.className = 'notif-item' + (n.unread ? ' unread' : '');
        if (n.unread) item.style.background = 'rgba(56,189,248,0.05)';
        const linkHtml = n.link ? `<a href="${n.link}" style="color:${n.textColor};font-size:0.72rem;font-weight:600;text-decoration:none;">${n.linkText} →</a>` : '';
        item.innerHTML = `
          <div style="width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:0.95rem;background:${n.bgColor};">
            ${n.icon}
          </div>
          <div style="flex:1;">
            <div style="font-size:0.83rem;color:var(--text);line-height:1.4;font-weight:600;">${n.message}</div>
            <div style="font-size:0.75rem;color:var(--muted2);margin-top:0.1rem;">${n.applicant} · #${n.appId}</div>
            <div class="notif-time">${n.time}</div>
            ${linkHtml}
          </div>
          ${n.unread ? `<span class="notif-unread-dot" style="width:8px;height:8px;border-radius:50%;background:${n.textColor};flex-shrink:0;margin-top:4px;"></span>` : ''}
        `;
        dropdown.appendChild(item);
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderNotifications();
    const notifDot = document.getElementById('notifDot');
    if (notifDot) notifDot.style.display = unreadNotifCount > 0 ? 'block' : 'none';
  });

  function markAllRead() {
    fetch('{{ route('notifications.markRead') }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      }
    })
      .then(res => {
        if (!res.ok) throw new Error('Request failed');
        document.querySelectorAll('.notif-item.unread').forEach(n => {
          n.classList.remove('unread');
          n.style.background = '';
        });
        document.querySelectorAll('.notif-unread-dot').forEach(d => d.remove());
        const notifDot = document.getElementById('notifDot');
        if (notifDot) notifDot.style.display = 'none';
        showToast('success', 'All notifications marked as read', '✅');
      })
      .catch(() => {
        showToast('error', 'Failed to mark notifications as read', '❌');
      });
  }

  // ─── PROFILE & SETTINGS DROPDOWNS ───
  function toggleSettingsDropdown() {
    const pd = document.getElementById('profileDropdown');
    const notifDropdown = document.getElementById('notifDropdown');
    const btn = document.getElementById('settingsBtn');

    if (notifDropdown) notifDropdown.classList.remove('open');

    if (pd && btn) {
        if (pd.classList.contains('open') && pd.dataset.openedFrom === 'topbar') {
            pd.classList.remove('open');
            return;
        }

        const rect = btn.getBoundingClientRect();
        pd.style.position = 'fixed';
        pd.style.top = (rect.bottom + 8) + 'px';
        pd.style.right = (window.innerWidth - rect.right) + 'px';
        pd.style.left = 'auto';
        pd.style.bottom = 'auto';
        pd.dataset.openedFrom = 'topbar';
        pd.classList.add('open');
    }
  }

  function toggleProfile() {
    const pd = document.getElementById('profileDropdown');
    const notifDropdown = document.getElementById('notifDropdown');
    const pill = document.querySelector('.admin-pill');

    if (notifDropdown) notifDropdown.classList.remove('open');

    if (pd && pill) {
        if (pd.classList.contains('open') && pd.dataset.openedFrom === 'sidebar') {
            pd.classList.remove('open');
            return;
        }

        const rect = pill.getBoundingClientRect();
        pd.style.position = 'fixed';
        pd.style.bottom = (window.innerHeight - rect.top + 8) + 'px';
        pd.style.left = rect.left + 'px';
        pd.style.top = 'auto';
        pd.style.right = 'auto';
        pd.dataset.openedFrom = 'sidebar';
        pd.classList.add('open');
    }
  }

  function closeAllDropdowns() {
    const notifDropdown = document.getElementById('notifDropdown');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (notifDropdown) notifDropdown.classList.remove('open');
    if (profileDropdown) profileDropdown.classList.remove('open');
    notifOpen = false;
  }

  document.addEventListener('click', e => {
    if (!e.target.closest('#notifBtn') && !e.target.closest('#notifDropdown')) {
      const notifDropdown = document.getElementById('notifDropdown');
      if (notifDropdown) notifDropdown.classList.remove('open');
      notifOpen = false;
    }
    if (!e.target.closest('#settingsBtn') && !e.target.closest('.admin-pill') && !e.target.closest('#profileDropdown')) {
      const profileDropdown = document.getElementById('profileDropdown');
      if (profileDropdown) profileDropdown.classList.remove('open');
    }
  });

  // ─── SEARCH ───
  const searchData = [];

  function handleSearch(q) {
    const box = document.getElementById('searchResults');
    if (!box) return;
    if (!q) { box.classList.remove('open'); return; }
    
    const res = searchData.filter(d => d.name.toLowerCase().includes(q.toLowerCase()) || d.id.toLowerCase().includes(q.toLowerCase()));
    if (!res.length) { box.classList.remove('open'); return; }
    
    box.innerHTML = res.map(r => `
      <div class="search-result-item" onmousedown="openApplicationModal(apps['${r.id}']);hideSearchResults()">
        <div style="flex:1"><strong>${r.name}</strong><div style="font-size:.75rem;color:var(--muted);">#${r.id}</div></div>
        <span class="sr-badge">${r.type}</span>
        <span class="status-pill ${r.status}" style="font-size:.7rem;">${r.status}</span>
      </div>
    `).join('');
    box.classList.add('open');
  }

  function showSearchResults() { 
    const input = document.getElementById('searchInput');
    const box = document.getElementById('searchResults');
    if (input && box && input.value) box.classList.add('open'); 
  }

  function hideSearchResults() { 
    const box = document.getElementById('searchResults');
    if (box) box.classList.remove('open'); 
  }

  // ─── APP ACTIONS ───
  function setAppStatus(id, action, successMsg, failMsg) {
    fetch(`/applications/${id}/${action}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
      }
    })
      .then(res => {
        if (!res.ok) throw new Error('Request failed');
        return res.json();
      })
      .then(data => {
        // Update the "Total Approved Loans" KPI without waiting for reload
        if (data && data.total_approved != null) {
          const el = document.getElementById('totalApproved');
          if (el) el.textContent = '₱' + Number(data.total_approved).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        showToast(action === 'approve' ? 'success' : 'error', successMsg, action === 'approve' ? '✅' : '❌');
        closeAllModals();
        setTimeout(() => location.reload(), 700);
      })
      .catch(() => {
        showToast('error', failMsg, '❌');
      });
  }

  function approveApp(id) {
    setAppStatus(id, 'approve',
      'Application Approved',
      'Failed to approve application #' + id + '.');
  }

  function rejectApp(id) {
    setAppStatus(id, 'reject',
      'Application Rejected',
      'Failed to reject application #' + id + '.');
  }

  // ─── FILTER ───
  function filterApps(status, el) {
    // Mark the clicked tab as active, un-mark the rest.
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (el) el.classList.add('active');

    // Determine which table to filter:
    //   - standalone /applications page uses .app-table
    //   - dashboard #page-applications uses its own table
    let rows;
    const appPage = document.querySelector('.app-table tbody tr[data-status]');
    if (appPage) {
      rows = document.querySelectorAll('.app-table tbody tr[data-status]');
    } else {
      const dashPage = document.getElementById('page-applications');
      rows = dashPage ? dashPage.querySelectorAll('tbody tr[data-status]') : [];
    }

    rows.forEach(row => {
      const rowStatus = row.getAttribute('data-status');
      row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
    });

    showToast('info', 'Filtered: ' + (status === 'all' ? 'All applications' : status), '🔍');
  }

  // ─── FORM SUBMISSIONS ───
  function submitPayout() {
    showToast('success', 'Release recorded successfully', '✅');
    closeAllModals();
  }

  // ─── DISBURSEMENTS / RELEASE RECORDS: open the record-release modal for a specific loan ───
  function openPayoutModal(loanId) {
    const form = document.getElementById('processPayoutForm');
    if (form) {
      form.querySelector('[name="loan_application_id"]').value = loanId || '';
      form.querySelector('[name="app_id"]').value = loanId || '';
    }
    openModal('processPayout');
  }

  // ─── REPAYMENTS: auto-fill borrower when an active loan is selected ───
  function loadLoanInfo() {
    const select = document.getElementById('activeLoanSelect');
    if (!select) return;
    const opt = select.options[select.selectedIndex];
    if (!opt) return;

    const borrower = document.getElementById('borrowerName');
    if (borrower && opt.dataset.borrower) {
      borrower.value = opt.dataset.borrower;
    }
  }

  // ─── CREDIT ASSESSMENT: filter applicant scoring table ───
  function filterCredit(status, btnEl) {
    document.querySelectorAll('#page-credit .filter-row .filter-btn')
      .forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    document.querySelectorAll('#page-credit tbody tr[data-status]')
      .forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
      });
  }

  function handleCreditSearch(q) {
    const box = document.getElementById('creditSearchInput');
    if (!box) return;
    const term = q.toLowerCase();
    document.querySelectorAll('#page-credit tbody tr[data-status]').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(term) ? '' : 'none';
    });
  }

  // ─── CREDIT ASSESSMENT: open the evaluate/detail modal ───
  function openCreditDetail(id) {
    const form = document.getElementById('processPayoutForm');
    if (form) {
      form.querySelector('[name="loan_application_id"]').value = id || '';
      form.querySelector('[name="app_id"]').value = id || '';
    }
    showToast('info', 'Opening credit assessment for loan #' + id, '🔍');
    closeAllModals();
  }

  // ─── RISK FLAGS: filter flagged accounts table ───
  function filterRisk(status, btnEl) {
    document.querySelectorAll('#page-risk .filter-row .filter-btn')
      .forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    document.querySelectorAll('#page-risk tbody tr[data-status]')
      .forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        row.style.display = (status === 'all' || rowStatus === status) ? '' : 'none';
      });
  }

  function handleRiskSearch(q) {
    const term = q.toLowerCase();
    document.querySelectorAll('#page-risk tbody tr[data-status]').forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(term) ? '' : 'none';
    });
  }

  // ─── RISK FLAGS: notify / send reminder ───
  function sendReminder(id) {
    showToast('success', 'Reminder sent to flagged account #' + id, '🔔');
  }

  // ─── SETTINGS: search settings fields ───
  function handleSettingsSearch(q) {
    const term = q.toLowerCase();
    document.querySelectorAll('#page-settings .panel').forEach(panel => {
      const text = panel.textContent.toLowerCase();
      panel.style.display = text.includes(term) ? '' : 'none';
    });
  }

  function submitReport() {
    showToast('info', 'Report is being generated, download will start shortly…', '📊');
    closeAllModals();
  }

  function confirmLogout() {
    if (confirm("Are you sure you want to log out?")) {
      const logoutForm = document.getElementById('logout-form');
      if (logoutForm) logoutForm.submit();
    }
  }

  // ─── BAR CHART ───
  function showBarToast(month, type, val) {
    showToast('info', month + ' ' + type + ': ' + val, '📊');
  }

  // ─── TOAST ───
  function showToast(type, msg, icon) {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-msg">${msg}</span><span class="toast-close" onclick="this.parentElement.remove()">✕</span>`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  // ─── MODALS ───
  function openModal(modalId) {
  const overlay = document.getElementById('overlay');
  const modal = document.getElementById('modal-' + modalId);

  if (overlay && modal) {
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.classList.add('open');
    modal.classList.add('open');
  }
}

  function closeAllModals() {
    const overlay = document.getElementById('overlay');
    if (overlay) {
      overlay.classList.remove('open');
    }

    document.querySelectorAll('.modal').forEach(modal => {
      modal.classList.remove('open');
    });
  }

  </script>