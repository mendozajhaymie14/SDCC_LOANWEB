<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>Cooperative Membership — San Dionisio Credit Cooperative</title>
</head>
<body>

  <header>
    @include('user.header')
  </header>

  @include('user.sidebar')

  <main class="member-main">
    @include('user.flash')

    <section class="passbook">
      <div>
        <div class="passbook-greeting">Cooperative membership</div>
        <p class="passbook-meta">
          Your online account is registered, but it is not linked to a cooperative
          membership yet. A loan can only be applied for once the Branch Manager
          approves your membership.
        </p>

        @if ($application)
          @php
            $statusLabels = [
              'pending'  => 'Under review',
              'approved' => 'Approved',
              'rejected' => 'Not approved',
            ];
            $status = $statusLabels[$application->status] ?? ucfirst($application->status);
          @endphp

          <div class="passbook-ledger" style="margin-top: 1rem;">
            <div class="ledger-row">
              <span class="ledger-label">Application status</span>
              <span class="ledger-value small">{{ $status }}</span>
            </div>
            <div class="ledger-row">
              <span class="ledger-label">Submitted</span>
              <span class="ledger-value small">{{ $application->created_at->format('M d, Y') }}</span>
            </div>
            @if ($application->reviewed_at)
              <div class="ledger-row">
                <span class="ledger-label">Decided</span>
                <span class="ledger-value small">{{ $application->reviewed_at->format('M d, Y') }}</span>
              </div>
            @endif
          </div>
        @endif

        <div class="passbook-actions">
          @if (!$application || $application->status === 'rejected')
            <a href="{{ route('member.applications.create') }}" class="btn-solid">Become a member</a>
          @elseif ($application->status === 'pending')
            <a href="{{ route('member.applications.show', $application->id) }}" class="btn-primary">View my application</a>
          @endif
          <a href="{{ route('user.dashboard') }}" class="btn-primary">Back to my account</a>
        </div>
      </div>
    </section>
  </main>

  @include('user.scripts')

</body>
</html>
