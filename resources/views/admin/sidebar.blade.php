<aside class="sidebar">
  <a href="{{ route('dashboard') }}" class="sidebar-logo">
    <img src="{{ asset('images/logo.jpg') }}" alt="Logo" class="sidebar-custom-logo">
    <div class="logo-text">SCC<span>.</span>AI</div>
  </a>

  <div class="sidebar-section">Overview</div>
  <a href="{{ route('dashboard') }}" class="nav-item {{ request()->is('dashboard', 'home', '/') ? 'active' : '' }}" data-tip="Main overview">
    <span class="nav-icon">◈</span>
    <span class="nav-label">Dashboard</span>
  </a>

  <div class="sidebar-section">Loan Management</div>
  <a href="{{ url('/applications') }}" class="nav-item {{ request()->is('applications') ? 'active' : '' }}">
    <span class="nav-icon">📋</span>
    <span class="nav-label">All Applications</span>
    <span class="nav-badge" id="badge-apps"></span>
  </a>
  <a href="#disbursements" class="nav-item {{ request()->is('disbursements') ? 'active' : '' }}">
    <span class="nav-icon">💰</span>
    <span class="nav-label">Disbursements</span>
    <span class="nav-badge gold"></span>
  </a>
  <a href="#repayments" class="nav-item {{ request()->is('repayments') ? 'active' : '' }}">
    <span class="nav-icon">🔄</span>
    <span class="nav-label">Repayments</span>
  </a>

  <div class="sidebar-section">Clients</div>
<a href="{{ route('borrowers.index') }}" class="nav-item {{ request()->routeIs('borrowers.index') ? 'active' : '' }}">
  <span class="nav-icon">👤</span>
  <span class="nav-label">Borrowers</span>
</a>
  <a href="#credit" class="nav-item {{ request()->is('credit') ? 'active' : '' }}">
    <span class="nav-icon">🔍</span>
    <span class="nav-label">Credit Assessment</span>
  </a>
  <a href="#risk" class="nav-item {{ request()->is('risk') ? 'active' : '' }}">
    <span class="nav-icon">⚠️</span>
    <span class="nav-label">Risk Flags</span>
    <span class="nav-badge red"></span>
  </a>

  <div class="sidebar-section">System</div>
  <a href="#settings" class="nav-item {{ request()->is('settings') ? 'active' : '' }}">
    <span class="nav-icon">⚙️</span>
    <span class="nav-label">Settings</span>
  </a>
  <a href="{{ route('admins.index') }}" class="nav-item {{ request()->routeIs('admins.index') ? 'active' : '' }}">
    <span class="nav-icon">👥</span>
    <span class="nav-label">Admin Users</span>
  </a>

  <!-- Inside sidebar.blade.php at the bottom -->
<div class="sidebar-footer">
  <div class="admin-pill" onclick="toggleProfile()">
    <!-- Change 'admin_logo.png' below to your actual filename -->
    <img src="{{ asset('images/admin_logo.png') }}" alt="Admin" class="admin-avatar-img">
    <div class="admin-details">
      <div class="admin-role">Administrator</div>
    </div>
    <div class="admin-more">⋮</div>
  </div>
</div>

</aside>