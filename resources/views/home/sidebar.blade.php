{{-- Slide-out menu. Opens from the hamburger in header.blade.php. --}}

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar" aria-label="Menu" aria-hidden="true">

  <div class="sidebar-head">
    <div class="sidebar-brand">SAN DIONISIO<br>CREDIT COOPERATIVE</div>
    <button type="button" class="sidebar-close" id="sidebarCloseBtn" aria-label="Close menu">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  @auth
    <div class="sidebar-user">
      <img
        src="{{ Auth::user()->profile_photo_url ?? asset('images/default-avatar.jpg') }}"
        alt="Profile picture"
        onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=1a5c2a&background=e8f9eb';"
      >
      <div>
        <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
        <div class="sidebar-user-meta">{{ Auth::user()->email }}</div>
      </div>
    </div>
  @endauth

  <ul class="sidebar-nav">
    <li><a href="#home"><i class="fa-solid fa-house"></i> Home</a></li>
    <li><a href="#features"><i class="fa-solid fa-circle-info"></i> About</a></li>
    <li><a href="#how"><i class="fa-solid fa-list-check"></i> How it works</a></li>
    <li><a href="#contact"><i class="fa-solid fa-envelope"></i> Contact</a></li>
    <li><a href="{{ route('faqs') }}"><i class="fa-solid fa-circle-question"></i> FAQs</a></li>
    <li><a href="{{ url('login') }}"><i class="fa-solid fa-right-to-bracket"></i> Login</a></li>
    <li><a href="{{ url('register') }}" class="sidebar-register"><i class="fa-solid fa-user-plus"></i> Register</a></li>

    @auth
      <li class="sidebar-divider"></li>
      <li><a href="{{ url('/user/dashboard') }}"><i class="fa-solid fa-gauge"></i> My account</a></li>
      <li><a href="{{ url('/user/loans/apply') }}"><i class="fa-solid fa-hand-holding-dollar"></i> Apply for a loan</a></li>
      <li><a href="{{ url('/user/profile') }}"><i class="fa-solid fa-user-pen"></i> Edit profile</a></li>
      <li class="sidebar-divider"></li>
      <li>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
        </form>
      </li>
    @endauth
  </ul>
</aside>