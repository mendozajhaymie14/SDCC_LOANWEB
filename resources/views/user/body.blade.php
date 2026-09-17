{{-- Dashboard content. Rendered inside user/index.blade.php. --}}

<section class="passbook" style="background: linear-gradient(135deg, #0a3d1c, #1a5c2a); border-radius: 24px; padding: 32px; display: flex; justify-content: space-between; align-items: center; color: #ffffff; gap: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
  <div style="flex: 1;">
    <div class="passbook-greeting" style="font-size: 28px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #ffffff; margin-bottom: 8px;">
      Good day, {{ Str::before($user->name, ' ') }}
    </div>
    <p class="passbook-meta" style="font-size: 14px; color: #a7f3d0; margin-bottom: 24px; max-width: 480px; line-height: 1.5;">
      Member since {{ $user->coopMember?->member_id ? substr($user->coopMember->member_id, 5, 4) : ($user->coopMember?->created_at?->format('Y') ?? $user->created_at?->format('Y') ?? 'today') }}.
      Everything about your loans with San Dionisio Credit Cooperative lives here.
    </p>

    <div class="passbook-actions" style="display: flex; gap: 12px;">
      <a href="{{ route('user.loans.create') }}" class="btn-primary" style="background-color: #ffffff; color: #0a3d1c; font-weight: 700; padding: 10px 20px; border-radius: 9999px; text-decoration: none; font-size: 14px;">Apply for a loan</a>
      <a href="{{ url('/user/profile') }}" class="btn-outline" style="border: 1px solid #ffffff; color: #ffffff; font-weight: 600; padding: 10px 20px; border-radius: 9999px; text-decoration: none; font-size: 14px;">Edit profile</a>
    </div>
  </div>

  <div class="passbook-ledger" style="display: flex; flex-direction: column; gap: 16px; min-width: 220px; border-left: 1px solid rgba(255, 255, 255, 0.2); padding-left: 24px;">
    <div class="ledger-row" style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px;">
      <span class="ledger-label" style="font-size: 13px; color: #d1fae5;">Approved and released</span>
      <span class="ledger-value" style="font-size: 22px; font-weight: 800; color: #fde047;">₱{{ number_format($summary['borrowed'], 2) }}</span>
    </div>
    <div class="ledger-row" style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px;">
      <span class="ledger-label" style="font-size: 13px; color: #d1fae5;">Applications submitted</span>
      <span class="ledger-value small" style="font-size: 16px; font-weight: 700; color: #ffffff;">{{ $summary['total'] }}</span>
    </div>
    <div class="ledger-row" style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px;">
      <span class="ledger-label" style="font-size: 13px; color: #d1fae5;">Waiting for a decision</span>
      <span class="ledger-value small" style="font-size: 16px; font-weight: 700; color: #ffffff;">{{ $summary['pending'] }}</span>
    </div>
  </div>
</section>

<div class="panel-head" style="margin-top: 32px; margin-bottom: 16px;">
  <h2 class="panel-title" style="font-size: 20px; font-weight: 800; text-transform: uppercase;">Your applications</h2>
  @if ($applications->isNotEmpty())
    <span class="panel-note" style="font-size: 13px; color: #6b7280;">Decisions are usually released within 48 hours.</span>
  @endif
</div>

@if ($applications->isEmpty())

  <div class="empty-state" style="background-color: #ffffff; border: 1px dashed #d1d5db; border-radius: 16px; padding: 40px; text-align: center;">
    <p style="color: #4b5563; font-size: 14px; margin-bottom: 16px; line-height: 1.6;">You haven't applied for a loan yet.<br>
       Pick a loan product, fill in the form, and we'll review it within 48 hours.</p>
    <a href="{{ route('user.loans.create') }}" class="btn-solid" style="background-color: #16a34a; color: #ffffff; font-weight: 700; padding: 10px 24px; border-radius: 8px; text-decoration: none; display: inline-block;">Apply for a loan</a>
  </div>

@else

  <div class="app-list" style="display: flex; flex-direction: column; gap: 12px;">
    @foreach ($applications as $application)
      <article class="app-row" style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; display: grid; grid-template-columns: 2fr 1fr 1.5fr 1.5fr 1fr; align-items: center; gap: 16px;">
        <div>
          <div class="app-ref" style="font-weight: 700; color: #111827;">{{ $application->reference }}</div>
          <div class="app-type" style="font-size: 13px; color: #6b7280;">{{ $application->loan_type }}</div>
        </div>

        <div>
          <div class="app-cell-label" style="font-size: 11px; color: #9ca3af; text-transform: uppercase;">Amount</div>
          <div class="app-cell-value" style="font-weight: 700; color: #111827;">₱{{ number_format($application->amount, 2) }}</div>
        </div>

        <div>
          <div class="app-cell-label" style="font-size: 11px; color: #9ca3af; text-transform: uppercase;">Term / monthly</div>
          <div class="app-cell-value" style="font-size: 13px; color: #374151;">
            {{ $application->term_months }} mos &middot; ₱{{ number_format($application->monthly_payment, 2) }}
          </div>
        </div>

        <div>
          <div class="app-cell-label" style="font-size: 11px; color: #9ca3af;">Filed {{ $application->created_at->format('d M Y') }}</div>
          <span class="badge badge-{{ $application->status }}" style="font-size: 12px; font-weight: 700; text-transform: capitalize;">{{ $application->status_label }}</span>
        </div>

        <div style="text-align: right;">
          @if ($application->status === 'pending')
            <form method="POST" action="{{ route('user.loans.cancel', $application) }}"
                  onsubmit="return confirm('Withdraw application {{ $application->reference }}?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="link-withdraw" style="background: none; border: none; color: #dc2626; font-size: 13px; cursor: pointer; text-decoration: underline;">Withdraw</button>
            </form>
          @endif
        </div>
      </article>
    @endforeach
  </div>

@endif