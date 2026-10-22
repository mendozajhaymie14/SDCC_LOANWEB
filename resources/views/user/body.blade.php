{{-- Dashboard content. Rendered inside user/index.blade.php. --}}

<section class="passbook">
  <div>
    <div class="passbook-greeting">Good day, {{ $user->first_name ?: $user->name }}</div>
    <p class="passbook-meta">
      Member since {{ $user->member_since }}.
      Everything about your loans with San Dionisio Credit Cooperative lives here.
    </p>

    @if ($summary['total'] === 0)
      <p class="passbook-empty-hint">Your first loan will appear here once approved.</p>
    @endif

    <div class="passbook-actions">
      @if (\App\Support\Permissions::has($user, \App\Support\Permissions::APPLY_LOAN))
        <a href="{{ route('user.loans.create') }}" class="btn-primary">Apply for a loan</a>
      @endif
      @if (!$user->coopMember)
        <a href="{{ route('user.membership') }}" class="btn-solid">Become a member</a>
      @endif
    </div>
  </div>

  <div class="passbook-ledger">
    <div class="ledger-row">
      <span class="ledger-label">Released to date</span>
      <span class="ledger-value">₱{{ number_format($summary['borrowed'], 2) }}</span>
    </div>
    <div class="ledger-row">
      <span class="ledger-label">Applications</span>
      <span class="ledger-value small">{{ $summary['total'] }}</span>
    </div>
    <div class="ledger-row">
      <span class="ledger-label">Approved</span>
      <span class="ledger-value small">{{ $summary['approved'] }}</span>
    </div>
    <div class="ledger-row">
      <span class="ledger-label">Pending</span>
      <span class="ledger-value small">{{ $summary['pending'] }}</span>
    </div>
  </div>
</section>

@if (count($applications) > 0)
  {{-- Clear Balance/Due Card --}}
  <div class="balance-due-card panel" style="margin: 32px 0;">
    <div class="panel-head">
      <h2 class="panel-title">Account Summary</h2>
    </div>
    <div class="panel-body">
      <div class="balance-due-grid">
        <div class="balance-due-item">
          <h3 class="balance-due-label">Remaining Balance</h3>
          <p class="balance-due-value">₱{{ number_format($applications->where('status', 'approved')->sum('remaining_balance'), 2) }}</p>
        </div>
        <div class="balance-due-item">
          <h3 class="balance-due-label">Next Payment Due</h3>
          <p class="balance-due-value">
            @foreach($applications->where('status', 'approved')->whereNotNull('next_due_date')->sortBy('next_due_date') as $loan)
              @if ($loop->first)
                {{ $loan->next_due_date->format('M d, Y') }}
                @break
              @endif
            @endforeach
            @if ($applications->where('status', 'approved')->whereNotNull('next_due_date')->isEmpty())
              No scheduled payments
            @endif
          </p>
        </div>
      </div>
    </div>
  </div>

  {{-- Active Reminder Alerts --}}
  <div class="reminder-alerts panel" style="margin: 32px 0;">
    <div class="panel-head">
      <h2 class="panel-title">Active Payment Reminders</h2>
    </div>
    <div class="panel-body">
      @if (($unreadNotifications ?? collect())->isNotEmpty())
        <div class="reminder-list">
          @foreach($unreadNotifications as $notif)
            <div class="reminder-item">
              <div class="reminder-header">
                <strong>{{ $notif->title }}</strong>
                <span class="status-pill @if($notif->type === 'due_today') due-today @elseif($notif->type === 'due_soon') due-soon @elseif($notif->type === 'overdue') overdue @endif">
                  @if($notif->type === 'due_today') Due Today
                  @elseif($notif->type === 'due_soon') Due Soon
                  @elseif($notif->type === 'overdue') Overdue
                  @elseif($notif->type === 'payment_received') Payment Received
                  @endif
                </span>
              </div>
              <p class="reminder-message">{{ Str::limit($notif->message, 120) }}</p>
              <div class="reminder-footer">
                <small class="reminder-time">{{ $notif->created_at->format('M d, Y g:i A') }}</small>
                <button class="btn-link btn-mark-read" data-id="{{ $notif->id }}">Mark as Read</button>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <p class="text-center text-muted py-4">No active reminders.</p>
      @endif
    </div>
  </div>
@endif

<div class="panel-head">
  <h2 class="panel-title">Your applications</h2>
  @if ($applications->isNotEmpty())
    <span class="panel-note">Decisions are usually released within 48 hours.</span>
  @endif
</div>

@if ($applications->isEmpty())

  <div class="empty-state">
    <p>You haven't applied for a loan yet.<br>
       Pick a loan product, fill in the form, and we'll review it within 48 hours.</p>
    @if (\App\Support\Permissions::has($user, \App\Support\Permissions::APPLY_LOAN))
      <a href="{{ route('user.loans.create') }}" class="btn-solid">Apply for a loan</a>
    @endif
  </div>

@else

  <div class="app-list">
    @foreach ($applications as $application)
      <article class="app-row">
        <div>
          <div class="app-ref">{{ $application->reference }}</div>
          <div class="app-type">{{ $application->loan_type_label }}</div>
        </div>

        <div>
          <div class="app-cell-label">Amount</div>
          <div class="app-cell-value">₱{{ number_format($application->amount, 2) }}</div>
        </div>

        <div>
          <div class="app-cell-label">Term / monthly</div>
          <div class="app-cell-value">{{ $application->term_months }} mos &middot; ₱{{ number_format($application->monthly_payment, 2) }}</div>
        </div>

        <div>
          <div class="app-cell-label">Filed {{ $application->created_at->format('d M Y') }}</div>
          <span class="badge badge-{{ $application->status }}">{{ $application->status_label }}</span>
        </div>

        <div class="app-actions">
          @if ($application->status === 'pending')
            <form method="POST" action="{{ route('user.loans.cancel', $application) }}"
                  onsubmit="return confirm('Withdraw application {{ $application->reference }}?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="link-withdraw">Withdraw</button>
            </form>
          @elseif (in_array($application->status, ['approved', 'disbursed']) && $application->remaining_balance > 0)
  <div class="app-cell-label">
    Balance: ₱{{ number_format($application->remaining_balance, 2) }}
    @if ($application->next_due_date)
      &middot; Next due {{ $application->next_due_date->format('d M Y') }}
    @endif
  </div>
@endif
        </div>
      </article>
    @endforeach
  </div>

@endif