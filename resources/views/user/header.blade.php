<nav id="navbar" class="scrolled">
  <a class="nav-logo" href="{{ route('user.dashboard') }}">
    <img src="{{ asset('images/Logo.jpg') }}" alt="San Dionisio Credit Cooperative Logo" class="logo-img">
    <div class="nav-name">SAN DIONISIO<br>CREDIT<br>COOPERATIVE</div>
  </a>

  <ul class="nav-links">
    <li><a href="{{ route('user.dashboard') }}" class="{{ request()->routeIs('user.dashboard') ? 'is-current' : '' }}">My Account</a></li>
    <li><a href="{{ route('user.loans.create') }}" class="{{ request()->routeIs('user.loans.create') ? 'is-current' : '' }}">Apply</a></li>
    <li><a href="{{ url('/') }}">Home</a></li>
    <li><a href="{{ url('/#contact') }}">Contact</a></li>
  </ul>

  <div class="nav-auth">
    <div class="user-menu-wrapper">
      <span class="welcome-user" title="Welcome, {{ Auth::user()->name }}">Welcome, <strong>{{ Auth::user()->name }}</strong></span>

      <button type="button" class="notif-btn" id="notifToggleBtn" aria-label="Notifications" aria-expanded="false">
        <i class="fa-regular fa-bell"></i>
        @if(($unreadNotifications ?? collect())->isNotEmpty())
          <span class="notif-badge">{{ $unreadNotifications->count() }}</span>
        @endif
      </button>

      <!-- Notification Inbox Dropdown -->
      <div id="member-notif-dropdown" class="notif-dropdown" style="position: absolute; right: 0; top: 100%; min-width: 200px; margin-top: 0.5rem; opacity: 0; visibility: hidden; transition: opacity 0.2s; z-index: 100;">
        <div class="notif-head">
          <div class="notif-head-title">My Notifications</div>
          <div class="notif-mark-all" onclick="markMemberNotificationsRead()">Mark all read</div>
        </div>
        <div class="notif-list" id="member-notif-list">
          <!-- Loaded via AJAX -->
        </div>
      </div>
    </div>

      <form method="POST" action="{{ route('logout') }}" class="nav-logout">
        @csrf
        <button type="submit" class="nav-logout-btn">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
      </form>
    </div>

    <button type="button" class="nav-toggle" id="sidebarOpenBtn"
            aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
</nav>

<script>
function toggleMemberNotifications() {
  const dropdown = document.getElementById('member-notif-dropdown');
  if (dropdown.style.visibility === 'hidden' || dropdown.style.visibility === '') {
    dropdown.style.visibility = 'visible';
    dropdown.style.opacity = '1';
    loadMemberNotifications();
  } else {
    dropdown.style.visibility = 'hidden';
    dropdown.style.opacity = '0';
  }
}

async function loadMemberNotifications() {
  try {
    const response = await fetch('/api/member/notifications');
    const data = await response.json();
    const list = document.getElementById('member-notif-list');

    if (!data || data.length === 0) {
      list.innerHTML = '<p class="text-center text-muted py-4">No new notifications.</p>';
      return;
    }

    let html = '';
    data.forEach(notif => {
      const date = notif.created_at ? new Date(notif.created_at) : new Date();
      const timeAgo = formatTimeAgo(date);
      const statusClass = notif.status === 'read' ? 'notif-read' : 'notif-unread';
      html += `
        <div class="notif-item ${statusClass}" onclick="markNotificationRead('${notif.id}')">
          <div class="notif-content">
            <strong>${notif.title}</strong>
            <p>${notif.message || ''}</p>
          </div>
          <div class="notif-time">${timeAgo}</div>
        </div>
      `;
    });

    list.innerHTML = html;
  } catch (error) {
    document.getElementById('member-notif-list').innerHTML = '<p class="text-center text-danger">Error loading notifications.</p>';
  }
}

function formatTimeAgo(date) {
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins} min ago`;
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
  return date.toLocaleDateString();
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
  const dropdown = document.getElementById('member-notif-dropdown');
  const trigger = document.getElementById('notifToggleBtn');
  if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
    dropdown.style.visibility = 'hidden';
    dropdown.style.opacity = '0';
  }
});
</script>