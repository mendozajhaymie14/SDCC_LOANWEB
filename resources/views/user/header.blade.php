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
      <span class="welcome-user">Welcome, <strong>{{ Auth::user()->name }}</strong></span>

      <div class="profile-dropdown">
        <button type="button" class="fb-profile-btn" id="profileDropdownBtn" aria-label="User menu">
          <img
            src="{{ Auth::user()->profile_photo_url ?? asset('images/default-avatar.jpg') }}"
            alt="Profile picture"
            class="fb-avatar-img"
            onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=1a5c2a&background=e8f9eb';"
          >
          <span class="arrow-badge"><i class="fa-solid fa-chevron-down"></i></span>
        </button>

        <div class="dropdown-menu-custom" id="profileDropdownMenu">
          <a href="{{ url('/user/profile') }}" class="dropdown-menu-item">
            <i class="fa-solid fa-user-pen"></i> Edit Profile
          </a>

          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-menu-item logout-btn">
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
          </form>
        </div>
      </div>
    </div>

    <button type="button" class="nav-toggle" id="sidebarOpenBtn"
            aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>
</nav>