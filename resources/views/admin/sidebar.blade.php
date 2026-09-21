<aside class="sidebar">
  <a href="{{ route('dashboard') }}" class="sidebar-logo">
    <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="sidebar-custom-logo">
    <div class="logo-text">
      <div class="logo-line">SAN DIONISIO</div>
      <div class="logo-line">CREDIT</div>
      <div class="logo-line">COOPERATIVE</div>
    </div>
  </a>

  <div class="sidebar-section">Overview</div>
  <a href="{{ route('dashboard') }}" class="nav-item {{ request()->is('dashboard', 'home', '/') ? 'active' : '' }}" data-tip="Main overview">
    <i class="fa-solid fa-chart-pie nav-icon"></i>
    <span class="nav-label">Dashboard</span>
  </a>

  <div class="sidebar-section">Loan Management</div>
  <a href="{{ url('/applications') }}" class="nav-item {{ request()->is('applications') ? 'active' : '' }}">
    <i class="fa-solid fa-file-lines nav-icon"></i>
    <span class="nav-label">All Applications</span>
    <span class="nav-badge" id="badge-apps"></span>
  </a>
  <a href="#disbursements" class="nav-item {{ request()->is('disbursements') ? 'active' : '' }}">
    <i class="fa-solid fa-money-bill-transfer nav-icon"></i>
    <span class="nav-label">Disbursements</span>
    <span class="nav-badge gold"></span>
  </a>
  <a href="#repayments" class="nav-item {{ request()->is('repayments') ? 'active' : '' }}">
    <i class="fa-solid fa-arrow-rotate-left nav-icon"></i>
    <span class="nav-label">Repayments</span>
  </a>

  <div class="sidebar-section">Clients</div>
<a href="{{ route('borrowers.index') }}" class="nav-item {{ request()->routeIs('borrowers.index') ? 'active' : '' }}">
  <i class="fa-solid fa-users nav-icon"></i>
  <span class="nav-label">Borrowers</span>
</a>
  <a href="#credit" class="nav-item {{ request()->is('credit') ? 'active' : '' }}">
    <i class="fa-solid fa-magnifying-glass-chart nav-icon"></i>
    <span class="nav-label">Credit Assessment</span>
  </a>
  <a href="#risk" class="nav-item {{ request()->is('risk') ? 'active' : '' }}">
    <i class="fa-solid fa-triangle-exclamation nav-icon"></i>
    <span class="nav-label">Risk Flags</span>
    <span class="nav-badge red"></span>
  </a>

  <div class="sidebar-section">System</div>
  <a href="#settings" class="nav-item {{ request()->is('settings') ? 'active' : '' }}">
    <i class="fa-solid fa-gear nav-icon"></i>
    <span class="nav-label">Settings</span>
  </a>
  <a href="{{ route('admins.index') }}" class="nav-item {{ request()->routeIs('admins.index') ? 'active' : '' }}">
    <i class="fa-solid fa-user-shield nav-icon"></i>
    <span class="nav-label">Admin Users</span>
  </a>

  <!-- Inside sidebar.blade.php at the bottom -->
<div class="sidebar-footer">
  <div class="admin-pill" onclick="toggleProfile()">
    <div class="admin-avatar-icon"><i class="fa-solid fa-user-gear"></i></div>
    <div class="admin-details">
      <div class="admin-role">Administrator</div>
    </div>
    <div class="admin-more">⋮</div>
  </div>
</div>

</aside>
