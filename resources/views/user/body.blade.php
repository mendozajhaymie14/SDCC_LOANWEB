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
      <a href="{{ route('user.loans.create') }}" class="btn-primary">Apply for a loan</a>
      @if (!$user->coopMember)
        <a href="{{ route('member.applications.create') }}" class="btn-solid">Become a member</a>
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
    <a href="{{ route('user.loans.create') }}" class="btn-solid">Apply for a loan</a>
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
          @endif
        </div>
      </article>
    @endforeach
  </div>

@endif