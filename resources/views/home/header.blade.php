<nav id="navbar">
  <a class="nav-logo" href="#home">
    <img src="{{ asset('images/Logo.jpg') }}" alt="San Dionisio Credit Cooperative Logo" class="logo-img">
    <div class="nav-name">SAN DIONISIO<br>CREDIT<br>COOPERATIVE</div>
  </a>

  <ul class="nav-links">
    <li><a href="#features">About</a></li>
    <li><a href="#how">How</a></li>
    <li><a href="#contact">Contact</a></li>
  </ul>

  <div class="nav-auth">
    @if (Route::has('login'))
      @auth
        <div class="user-menu-wrapper">
          <div class="profile-dropdown">
            <button type="button" class="fb-profile-btn" id="profileDropdownBtn" aria-haspopup="true" aria-expanded="false">
              <img src="{{ Auth::user()->profile_photo_url ?? asset('images/default-avatar.jpg') }}"
                   alt="Profile picture" class="fb-avatar-img"
                   onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=1a5c2a&background=e8f9eb';">
              <span class="arrow-badge"><i class="fa-solid fa-chevron-down"></i></span>
            </button>

            <div class="dropdown-menu-custom" id="profileDropdown">
              <div class="dropdown-header">
                <div class="dropdown-name">{{ Auth::user()->name }}</div>
                <div class="dropdown-email">{{ Auth::user()->email }}</div>
              </div>
              <a href="{{ url('/user/dashboard') }}" class="dropdown-menu-item"><i class="fa-solid fa-gauge"></i> Dashboard</a>
              <a href="{{ url('/user/loans/apply') }}" class="dropdown-menu-item"><i class="fa-solid fa-hand-holding-dollar"></i> Apply for a loan</a>
              <a href="{{ url('/user/profile') }}" class="dropdown-menu-item"><i class="fa-solid fa-user-pen"></i> Edit profile</a>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-menu-item logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
              </form>
            </div>
          </div>
        </div>
      @else
      @endif
    @endif

    <button type="button" class="nav-toggle" id="sidebarOpenBtn"
            aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
</nav>

<script>
  // ─── Profile dropdown toggle ───
  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('profileDropdownBtn');
    const menu = document.getElementById('profileDropdown');
    if (!btn || !menu) return;

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const open = menu.classList.toggle('show');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!menu.contains(e.target) && e.target !== btn) {
        menu.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  });
</script>