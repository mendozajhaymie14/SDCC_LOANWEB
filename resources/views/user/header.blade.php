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