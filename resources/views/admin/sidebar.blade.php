@php
    $riskFlagsCount = $riskFlagsCount ?? \App\Models\Application::where('risk_level', 'High')->count();
    $hasDashboardAccess = \App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VIEW_DASHBOARD);
@endphp
<aside class="sidebar">
  <a href="{{ url('/dashboard') }}" class="sidebar-logo" title="Main overview">
    <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="sidebar-custom-logo">
    <div class="logo-text">
      <div class="logo-line">SAN DIONISIO</div>
      <div class="logo-line">CREDIT</div>
      <div class="logo-line">COOPERATIVE</div>
    </div>
  </a>

  @if($hasDashboardAccess)
  <div class="sidebar-section">MAIN MENU</div>
  <a href="{{ url('/dashboard') }}" class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}" data-tip="Main overview">
    <i class="fa-solid fa-chart-pie nav-icon"></i>
    <span class="nav-label">Dashboard</span>
  </a>

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::ENCODE_APPLICATIONS))
  <a href="{{ url('/applications') }}" class="nav-item {{ request()->is('applications*') ? 'active' : '' }}" data-tip="Loan Applications">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">Loan Applications</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::RECORD_PAYMENTS))
  <a href="{{ url('/repayments') }}" class="nav-item {{ request()->is('repayments*') ? 'active' : '' }}" data-tip="Repayments">
    <i class="fa-solid fa-arrow-rotate-left nav-icon"></i>
    <span class="nav-label">Repayments</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::PROCESS_DISBURSEMENTS))
  <a href="{{ url('/disbursements') }}" class="nav-item {{ request()->is('disbursements*') ? 'active' : '' }}" data-tip="Loan Release Records">
    <i class="fa-solid fa-file-circle-check nav-icon"></i>
    <span class="nav-label">Release Records</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::APPROVE_MEMBERSHIPS))
  <a href="{{ url('/memberships') }}" class="nav-item {{ request()->is('memberships*') ? 'active' : '' }}" data-tip="Membership Applications">
    <i class="fa-solid fa-id-card nav-icon"></i>
    <span class="nav-label">Membership </span>
  </a>
  @endif

  <div class="sidebar-section">RISK & MEMBERS</div>
  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VIEW_MEMBER_PROFILES))
  <a href="{{ url('/active-members') }}" class="nav-item {{ request()->is('active-members') ? 'active' : '' }}" data-tip="Active Members">
    <i class="fa-solid fa-users nav-icon"></i>
    <span class="nav-label">Active Members</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VERIFY_DOCUMENTS))
  <a href="{{ url('/admin/risk-flags') }}" class="nav-item {{ request()->is('admin/risk-flags*') ? 'active' : '' }}" data-tip="Risk Flags">
    <i class="fa-solid fa-triangle-exclamation nav-icon"></i>
    <span class="nav-label">Risk Flags</span>
    <span class="nav-badge red">{{ $riskFlagsCount }}</span>
  </a>
  @endif

  <div class="sidebar-section">ADMINISTRATION</div>
  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::MANAGE_SETTINGS))
  <a href="{{ url('/admin/settings') }}" class="nav-item {{ request()->is('admin/settings*') ? 'active' : '' }}" data-tip="Settings">
    <i class="fa-solid fa-gear nav-icon"></i>
    <span class="nav-label">Settings</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VIEW_AUDIT_LOGS))
  <a href="{{ url('/admin/audit-logs') }}" class="nav-item {{ request()->is('admin/audit-logs*') ? 'active' : '' }}" data-tip="Audit Logs">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">Audit Logs</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::STAFF_ACTIVITY))
  <a href="{{ url('/admin/notifications-log') }}" class="nav-item {{ request()->is('admin/notifications-log*') ? 'active' : '' }}" data-tip="Notification Outbox">
    <i class="fa-solid fa-bell nav-icon"></i>
    <span class="nav-label">Notification Outbox</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::VIEW_REPORTS))
  <a href="{{ url('/reports') }}" class="nav-item {{ request()->is('reports*') ? 'active' : '' }}" data-tip="Reports">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">Reports</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::MANAGE_BACKUPS))
  <a href="{{ url('/admin/backup') }}" class="nav-item {{ request()->is('admin/backup*') ? 'active' : '' }}" data-tip="Backup">
    <i class="fa-solid fa-database nav-icon"></i>
    <span class="nav-label">Backup</span>
  </a>
  @endif

  @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::MANAGE_USERS))
  <a href="{{ url('/admin/users') }}" class="nav-item {{ request()->is('admin/users*') ? 'active' : '' }}" data-tip="User Management">
    <i class="fa-solid fa-users-gear nav-icon"></i>
    <span class="nav-label">User Management</span>
  </a>
  @endif

  <div class="sidebar-footer">
    <div class="admin-pill" onclick="toggleProfile()">
      <div class="admin-avatar-icon"><i class="fa-solid fa-user-gear"></i></div>
      <div class="admin-details">
        <div class="admin-name">{{ auth()->user()->name ?? 'Staff' }}</div>
        <div class="admin-role">{{ \App\Support\Permissions::label(\App\Support\Permissions::role(auth()->user())) }}</div>
      </div>
      <div class="admin-more">⋮</div>
    </div>
  </div>
  @endif
</aside>