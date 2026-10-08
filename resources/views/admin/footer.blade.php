<!-- PROFILE & SETTINGS DROPDOWN -->
<div class="profile-dropdown" id="profileDropdown">
    <a href="{{ route('admins.index') }}" class="pd-item">👥 Admin Users</a>
    <div class="pd-divider"></div>
    <div class="pd-item danger" onclick="confirmLogout()">🚪 Log Out</div>

    <!-- Hidden form for Laravel authentication -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- OVERLAY CONTAINER -->
<div class="overlay" id="overlay" onclick="closeAllModals()">

  <!-- 1. New Loan Application Modal -->
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
        <input type="number" step="0.01" name="amount" placeholder="150000" required class="form-input"/>
      </div>
      <button class="btn-submit" type="submit">Submit Application</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>

  <!-- Application Detail / Approve-Reject-Delete Modal -->
  <div class="modal" id="modal-appDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="appDetailTitle">Application Detail</div>
      <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <div id="appDetailBody"></div>
  </div>

  <!-- Active Member Detail / Activate-Suspend-Delete Modal -->
  <div class="modal" id="modal-activeMemberDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="activeMemberDetailTitle">Member Detail</div>
      <div class="modal-close" type="button" onclick="closeAllModals()">✕</div>
    </div>
    <div id="activeMemberDetailBody"></div>
  </div>

  <!-- 2. Add Active Member Modal -->
  <div class="modal" id="modal-addActiveMember" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">👤 Add New Member</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <form action="{{ route('active-members.store') }}" method="POST">
      @csrf

      <div class="form-group">
        <label class="form-label">Member ID</label>
        <input type="text" name="member_id" class="form-input" placeholder="e.g. SDCC-2024-0001" required/>
      </div>

      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-input" placeholder="e.g. Juan Santos" required/>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input" placeholder="juan@email.com" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-input" placeholder="+63 9XX XXX XXXX"/>
        </div>
      </div>

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

  <!-- 2d. Add Admin Modal -->
  <div class="modal" id="modal-addAdmin" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">👤 Add Admin User</div>
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
          <input type="text" name="phone" id="addAdminPhone" class="form-input" placeholder="+63 9XX XXX XXXX"/>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" id="addAdminPassword" class="form-input" placeholder="Min. 6 characters" required/>
        </div>
      </div>
      <button class="btn-submit" type="button" onclick="submitAddAdmin()">Create Admin Account</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>

  <!-- 2e. Edit Admin Modal -->
  <div class="modal" id="modal-editAdmin" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Admin User</div>
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
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <input type="text" id="editAdminPhone" name="phone" class="form-input" placeholder="+63 9XX XXX XXXX"/>
      </div>

      <button class="btn-submit" type="button" onclick="submitEditAdmin()">Save Changes</button>
      <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
    </form>
  </div>

  <!-- 2f. Admin Detail Modal -->
  <div class="modal" id="modal-adminDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="adminDetailTitle">Admin Detail</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <div id="adminDetailBody"></div>
  </div>

  <!-- 2c2. Member Detail Modal -->
  <div class="modal" id="modal-memberDetail" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="memberDetailTitle">Membership Application Detail</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <div id="memberDetailBody"></div>
  </div>

  <!-- 2c. Edit Membership Application Modal -->
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
          <input type="text" id="editContact" name="contact_number" class="form-input"/>
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

  <!-- 2b. Add Membership Application Modal -->
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
          <input type="text" name="first_name" class="form-input" placeholder="e.g. Juan" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middle_name" class="form-input" placeholder="e.g. Dela"/>
        </div>
        <div class="form-group">
          <label class="form-label">Surname</label>
          <input type="text" name="surname" class="form-input" placeholder="e.g. Cruz" required/>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-input" placeholder="juan@email.com" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact_number" class="form-input" placeholder="+63 9XX XXX XXXX"/>
        </div>
      </div>
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

  <!-- 3. Process Payout Modal -->
  <div class="modal" id="modal-processPayout" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title">💸 Process Payout</div>
      <div class="modal-close" onclick="closeAllModals()">✕</div>
    </div>
    <div class="form-group"><label class="form-label">Application ID</label><input class="form-input" placeholder="#SCC-XXXXX"/></div>
    <div class="form-group"><label class="form-label">Borrower Name</label><input class="form-input" placeholder="Full name"/></div>
    <div class="form-group"><label class="form-label">Payout Amount (₱)</label><input class="form-input" placeholder="150000" type="number"/></div>
    <div class="form-group">
      <label class="form-label">Disbursement Channel</label>
      <select class="form-select">
        <option>BPI Bank Transfer</option>
        <option>BDO Bank Transfer</option>
        <option>GCash</option>
        <option>Maya</option>
        <option>UnionBank</option>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Account Number</label><input class="form-input" placeholder="XXXX-XXXX-XXXX"/></div>
    <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" placeholder="Optional notes…"></textarea></div>
    <button class="btn-submit" type="button" onclick="submitPayout()">Confirm & Disburse</button>
    <button class="btn-cancel" type="button" onclick="closeAllModals()">Cancel</button>
  </div>

  <!-- 4. Generate Report Modal -->
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

</div>

<script>
  // ─── DATA ───
  const apps = {
    @foreach (\App\Models\Application::latest()->get() as $a)
      "{{ $a->app_id }}": {
        name: @json($a->applicant),
        type: @json($a->loan_type),
        amount: @json('₱' . number_format($a->amount, 2)),
        score: {{ $a->ai_score ?? 'null' }},
        status: @json(strtolower($a->status)),
        date: @json(optional($a->created_at)->format('M d, Y'))
      },
    @endforeach
  };

  const activeMembersData = {
    @foreach (\App\Models\CoopMember::with('user')->latest()->get() as $m)
      @php
        $latestBorrower = \App\Models\Borrowers::where('full_name', $m->full_name)->latest()->first();
        $income = $latestBorrower ? number_format($latestBorrower->monthly_income, 2) : '';
        $aiScore = $latestBorrower ? $latestBorrower->ai_credit_score : null;
        $rawScore = $aiScore !== null ? (int) round(($aiScore - 300) / 550 * 100) : null;
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

  const csrfToken = @json(csrf_token());

  const notifications = [
    @foreach (\App\Models\AppNotification::latest()->take(8)->get() as $n)
      {
        type: @json($n->type),
        message: @json($n->message),
        appId: @json($n->app_id),
        applicant: @json($n->applicant),
        time: @json($n->created_at->diffForHumans()),
        unread: {{ $n->read_at ? 'false' : 'true' }}
      },
    @endforeach
  ];
  const unreadNotifCount = {{ \App\Models\AppNotification::whereNull('read_at')->count() }};

  const statusColor = {approved:'var(--accent3)',pending:'var(--gold)',review:'var(--accent)',rejected:'var(--red)'};
  const scoreColor = s => s>=90?'var(--accent3)':s>=80?'var(--accent)':s>=70?'var(--gold)':'var(--red)';

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
      disbursements:'Disbursements', repayments:'Repayments', members:'Active Members',
      credit:'Credit Assessment', risk:'Risk Flags', settings:'Settings',
      reports:'Reports'
    };
    
    const topbarTitle = document.getElementById('topbarTitle');
    if (topbarTitle) {
      topbarTitle.innerHTML = (titles[id] || id) + ' <span>/ ' + (id === 'dashboard' ? 'Overview' : id.charAt(0).toUpperCase() + id.slice(1)) + '</span>';
    }
    closeAllDropdowns();
  }

  // ─── APPLICATION DETAIL MODAL ───
  function openAppDetail(id) {
    closeAllDropdowns();
    const app = apps[id];
    if (!app) {
      showToast('error', 'Application #' + id + ' not found', '❌');
      return;
    }

    const statusLabel = app.status.charAt(0).toUpperCase() + app.status.slice(1);
    const scoreC = app.score != null ? scoreColor(app.score) : 'var(--muted)';

    const titleEl = document.getElementById('appDetailTitle');
    if (titleEl) titleEl.textContent = '#' + id + ' — ' + app.name;

    const bodyEl = document.getElementById('appDetailBody');
    if (bodyEl) {
      const row = (label, value) => `
        <div style="display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.88rem;">
          <span style="color:var(--muted);">${label}</span>
          <span style="font-weight:600;">${value}</span>
        </div>`;

      bodyEl.innerHTML = `
        ${row('Applicant', app.name)}
        ${row('Loan Type', app.type)}
        ${row('Amount', `<span style="font-family:'DM Sans',sans-serif;">${app.amount}</span>`)}
        ${row('AI Score', `<span style="font-family:'DM Sans',sans-serif;color:${scoreC};">${app.score ?? 'N/A'}</span>`)}
        ${row('Date', app.date)}
        ${row('Status', `<span class="status-pill ${app.status}"><span class="status-dot"></span>${statusLabel}</span>`)}
        
        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          ${(app.status === 'pending' || app.status === 'review') ? `
            <div style="display:flex;gap:.7rem;">
              <button class="btn-cancel" type="button" style="width:auto;flex:1;color:var(--red);border-color:var(--red);" onclick="rejectApp('${id}')">✗ Reject</button>
              <button class="btn-submit" type="button" style="width:auto;flex:1;" onclick="approveApp('${id}')">✓ Approve</button>
            </div>` : ''
          }
          <button class="btn-cancel" type="button" style="width:100%;color:var(--red);border-color:var(--red);background:rgba(239,68,68,0.05);" onclick="deleteApp('${id}')">🗑️ Delete Application</button>
        </div>
      `;
    }

    openModal('appDetail');
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

      bodyEl.innerHTML = `
        ${row('Member ID', m.member_id)}
        ${row('Full Name', m.name)}
        ${row('Email Address', m.email)}
        ${row('Monthly Income', `<span style="font-family:'DM Sans',sans-serif;">${m.income}</span>`)}
        ${row('AI Score', `<span style="font-family:'DM Sans',sans-serif;color:${scoreC};">${m.aiScore ?? 'N/A'}</span>`)}
        ${row('Date Joined', m.date)}
        ${row('Status', `<span class="status-pill ${m.status}"><span class="status-dot"></span>${statusLabel}</span>`)}

        <div style="display:flex;flex-direction:column;gap:.7rem;margin-top:1.2rem;">
          ${(m.status === 'inactive' || m.status === 'suspended') ? `
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
          <button class="btn-cancel" type="button" style="width:100%;color:var(--red);border-color:var(--red);background:rgba(239,68,68,0.05);" onclick="deleteActiveMember('${m.id}')">🗑️ Delete Member</button>
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
        const isApproved = n.type === 'approved';
        const item = document.createElement('div');
        item.className = 'notif-item' + (n.unread ? ' unread' : '');
        if (n.unread) item.style.background = 'rgba(56,189,248,0.05)';
        item.innerHTML = `
          <div style="width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:0.95rem;background:${isApproved ? 'rgba(52,211,153,0.15)' : 'rgba(248,113,113,0.15)'};">
            ${isApproved ? '✅' : '❌'}
          </div>
          <div style="flex:1;">
            <div style="font-size:0.83rem;color:var(--text);line-height:1.4;font-weight:600;">${n.message}</div>
            <div style="font-size:0.75rem;color:var(--muted2);margin-top:0.1rem;">${n.applicant} · #${n.appId}</div>
            <div class="notif-time">${n.time}</div>
          </div>
          ${n.unread ? `<span class="notif-unread-dot" style="width:8px;height:8px;border-radius:50%;background:${isApproved ? 'var(--accent3)' : 'var(--red)'};flex-shrink:0;margin-top:4px;"></span>` : ''}
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
      <div class="search-result-item" onmousedown="openAppDetail('${r.id}');hideSearchResults()">
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
        // Update the "Total Loan Disbursed" KPI without waiting for reload
        if (data && data.total_disbursed != null) {
          const el = document.getElementById('totalDisbursed');
          if (el) el.textContent = '₱' + Number(data.total_disbursed).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    showToast('success', 'Payout processed and disbursed!', '💸');
    closeAllModals();
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