<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>My Account — San Dionisio Credit Cooperative</title>
</head>
<body>

  <header>
    @include('user.header')
  </header>

  @include('user.sidebar')

  <main class="member-main">
    @include('user.flash')
    @include('user.body')
  </main>

  @include('user.scripts')

  <!-- Notifications dropdown -->
  <div class="notif-dropdown" id="notifDropdown">
    <div class="notif-head">
      <h4 class="notif-head-title">Payment Reminders</h4>
    </div>
    @if(($unreadNotifications ?? collect())->isNotEmpty())
      <div class="notif-items">
        @foreach($unreadNotifications as $notif)
          <div class="notif-item" data-notification-id="{{ $notif->id }}" data-type="{{ $notif->type }}">
            <strong>{{ $notif->title }}</strong>
            <div class="notif-msg">{{ Str::limit($notif->message, 100) }}</div>
            <div class="notif-time">{{ $notif->created_at->format('M d, Y g:i A') }}</div>
          </div>
        @endforeach
      </div>
      <div style="padding: 0.8rem 1.2rem; border-top: 1px solid var(--border); text-align: center;">
        <a href="{{ route('user.notifications') }}" class="notif-mark-all">View all reminders</a>
      </div>
    @else
      <div style="padding: 1.2rem; text-align: center; color: var(--muted);">
        No active reminders.
      </div>
    @endif
  </div>

</body>
</html>