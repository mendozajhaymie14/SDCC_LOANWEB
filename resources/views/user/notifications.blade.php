<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>My Reminders — San Dionisio Credit Cooperative</title>
</head>
<body>

  <header>
    @include('user.header')
  </header>

  @include('user.sidebar')

  <main class="member-main">
    <h2 class="panel-title">My Reminders</h2>

    @if($notifications->isEmpty())
      <div class="panel" style="padding: 2rem; text-align: center; color: var(--muted);">
        No reminders.
      </div>
    @else
      <ul class="reminder-list" style="list-style: none; padding: 0;">
        @foreach($notifications as $notif)
          <li class="reminder-item panel" style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);">
            <div class="reminder-title">
              <strong>{{ $notif->title }}</strong>
              @if($notif->type === 'due_today')
                <span class="status-pill due-today">Due Today</span>
              @elseif($notif->type === 'due_soon')
                <span class="status-pill due-soon">Due Soon</span>
              @elseif($notif->type === 'overdue')
                <span class="status-pill overdue">Overdue</span>
              @elseif($notif->type === 'payment_received')
                <span class="status-pill payment-received">Payment Received</span>
              @endif
            </div>
            <p class="reminder-message">{{ Str::limit($notif->message, 140) }}</p>
            <small class="reminder-time">{{ $notif->created_at->format('M d, Y g:i A') }}</small>
            <form method="POST" action="{{ route('user.notifications.read', ['notification' => $notif]) }}" style="display: inline;">
              @csrf
              <button type="submit" class="btn-link btn-mark-read">Mark as Read</button>
            </form>
          </li>
        @endforeach
      </ul>
    @endif
  </main>

  @include('user.scripts')
</body>
</html>
