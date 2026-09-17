<nav id="navbar">
  <a class="nav-logo" href="#home">
    <img src="{{ asset('images/Logo.jpg') }}" alt="San Dionisio Credit Cooperative Logo" class="logo-img">
    <div class="nav-name">SAN DIONISIO<br>CREDIT<br>COOPERATIVE</div>
  </a>

  <ul class="nav-links">
    <li><a href="#home">Home</a></li>
    <li><a href="#features">About</a></li>
    <li><a href="#how">How</a></li>
    <li><a href="#contact">Contact</a></li>
  </ul>

  <div class="nav-auth">
    @if (Route::has('login'))
      @auth
        <div class="user-menu-wrapper" style="position: relative;">
          <span class="welcome-user">Welcome, <strong>{{ Auth::user()->name }}</strong></span>

          <div class="profile-dropdown">
            <button type="button" class="fb-profile-btn" id="profileDropdownBtn" aria-label="User menu" aria-expanded="false">
              <img
                src="{{ Auth::user()->profile_photo_url ?? asset('images/default-avatar.jpg') }}"
                alt="Profile picture"
                class="fb-avatar-img"
                onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=1a5c2a&background=e8f9eb';"
              >
              <span class="arrow-badge"><i class="fa-solid fa-chevron-down"></i></span>
            </button>

            <div class="dropdown-menu-custom" id="profileDropdownMenu" style="display: none;">
              <a href="{{ url('/user/profile') }}" class="dropdown-menu-item">
                <i class="fa-solid fa-user-pen"></i> Edit Profile
              </a>

              <form id="logout-form" method="POST" action="{{ route('logout') }}" style="margin: 0;">
                @csrf
                <button type="submit" class="dropdown-menu-item logout-btn" style="width: 100%; border: none; background: none; text-align: left; cursor: pointer;">
                  <i class="fa-solid fa-right-from-bracket"></i> Logout
                </button>
              </form>
            </div>
          </div>
        </div>
      @else
        <a href="{{ url('login') }}" class="btn-login">Login</a>
        @if (Route::has('register'))
          <a href="{{ url('register') }}" class="btn-register">Register</a>
        @endif
      @endauth
    @endif

    <button type="button" class="nav-toggle" id="sidebarOpenBtn"
            aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
</nav>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('profileDropdownBtn');
    const menu = document.getElementById('profileDropdownMenu');

    if (btn && menu) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isVisible = menu.style.display === 'block';
        menu.style.display = isVisible ? 'none' : 'block';
        btn.setAttribute('aria-expanded', !isVisible);
      });

      document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && !btn.contains(e.target)) {
          menu.style.display = 'none';
          btn.setAttribute('aria-expanded', 'false');
        }
      });
    }
  });
</script>