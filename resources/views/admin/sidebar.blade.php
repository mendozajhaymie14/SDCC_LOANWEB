<aside class="sidebar">
  <a href="{{ url('/dashboard') }}" class="sidebar-logo" title="Main overview">
    <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="sidebar-custom-logo">
    <div class="logo-text">
      <div class="logo-line">SAN DIONISIO</div>
      <div class="logo-line">CREDIT</div>
      <div class="logo-line">COOPERATIVE</div>
    </div>
  </a>

  <div class="sidebar-section">MAIN MENU</div>
  <a href="{{ url('/dashboard') }}" class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}" data-tip="Main overview">
    <i class="fa-solid fa-chart-pie nav-icon"></i>
    <span class="nav-label">Dashboard</span>
  </a>
  <a href="{{ url('/applications') }}" class="nav-item {{ request()->is('applications*') ? 'active' : '' }}" data-tip="Loan Applications">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">Loan Applications</span>
    <span class="nav-badge" id="badge-apps"></span>
  </a>
  <a href="{{ url('/repayments') }}" class="nav-item {{ request()->is('repayments*') ? 'active' : '' }}" data-tip="Repayments">
    <i class="fa-solid fa-arrow-rotate-left nav-icon"></i>
    <span class="nav-label">Repayments</span>
  </a>
  <a href="{{ url('/memberships') }}" class="nav-item {{ request()->is('memberships*') ? 'active' : '' }}" data-tip="Membership Applications">
    <i class="fa-solid fa-id-card nav-icon"></i>
    <span class="nav-label">Membership Applications</span>
    <span class="nav-badge" id="badge-memberships"></span>
  </a>

  <div class="sidebar-section">RISK & MEMBERS</div>
  <a href="{{ url('/active-members') }}" class="nav-item {{ request()->is('active-members') ? 'active' : '' }}" data-tip="Active Members">
    <i class="fa-solid fa-users nav-icon"></i>
    <span class="nav-label">Active Members</span>
  </a>
  <a href="{{ url('/admin/risk-flags') }}" class="nav-item {{ request()->is('risk-flags.index') ? 'active' : '' }}" data-tip="Risk Flags">
    <i class="fa-solid fa-triangle-exclamation nav-icon"></i>
    <span class="nav-label">Risk Flags</span>
    <span class="nav-badge red"></span>
  </a>

  <div class="sidebar-section">ADMINISTRATION</div>
  <a href="{{ url('/admin/settings') }}" class="nav-item {{ request()->is('admin/settings*') ? 'active' : '' }}" data-tip="Settings">
    <i class="fa-solid fa-gear nav-icon"></i>
    <span class="nav-label">Settings</span>
  </a>
  <a href="{{ url('/admin/audit-logs') }}" class="nav-item {{ request()->is('admin/audit-logs*') ? 'active' : '' }}" data-tip="Audit Logs">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">Audit Logs</span>
  </a>
  <a href="{{ url('/admin/notifications-log') }}" class="nav-item {{ request()->is('admin/notifications-log*') ? 'active' : '' }}" data-tip="Notification Outbox">
    <i class="fa-solid fa-bell nav-icon"></i>
    <span class="nav-label">Notification Outbox</span>
  </a>
  <a href="{{ url('/reports') }}" class="nav-item {{ request()->is('reports*') ? 'active' : '' }}" data-tip="Reports">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">Reports</span>
  </a>

  <div class="sidebar-footer">
    <div class="admin-pill" onclick="toggleProfile()">
      <div class="admin-avatar-icon"><i class="fa-solid fa-user-gear"></i></div>
      <div class="admin-details">
        <div class="admin-name">{{ auth()->user()->name ?? 'Administrator' }}</div>
        <div class="admin-role">Administrator</div>
      </div>
      <div class="admin-more">⋮</div>
    </div>
  </div>
</aside>