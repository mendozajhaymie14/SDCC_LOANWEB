@extends('admin.index')

@section('admin_content')
<div style="padding: 1.5rem; width: 100%; max-width: 1200px;">

    @if(session('error'))
      <div style="padding:10px 12px; border:1px solid #fca5a5; background:#fef2f2; color:#991b1b; border-radius:6px; margin-bottom:12px;">{{ session('error') }}</div>
    @endif
    @if(session('success'))
      <div style="padding:10px 12px; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <div class="panel app-panel">
      <div class="page-header">
        <h2 class="panel-title">{{ $member->full_name }}</h2>
        <div>
          @can('encode_applications')
          <a href="#" class="btn-review" style="text-decoration:none; margin-right:8px;" onclick="openEncodeModal({{ $member->id }}); return false;">+ Encode Application</a>
          @endcan
          <a href="{{ route('active-members.index') }}" class="btn-review" style="text-decoration:none;">← Back to Active Members</a>
        </div>
      </div>

      <table class="app-table" style="margin-bottom: 1rem;">
        <tbody>
          <tr><td><strong>Member ID</strong></td><td>{{ $member->member_id }}</td></tr>
          <tr><td><strong>Email</strong></td><td>{{ $member->email ?? '—' }}</td></tr>
          <tr><td><strong>Contact Number</strong></td><td>{{ $member->contact_number ?? '—' }}</td></tr>
          <tr><td><strong>Address</strong></td><td>{{ ($member->address ?? '') ?: '—' }}</td></tr>
          <tr><td><strong>Occupation</strong></td><td>{{ $member->occupation ?? '—' }}</td></tr>
          <tr><td><strong>Date of Birth</strong></td><td>{{ \App\Support\Format::date($member->date_of_birth) }}</td></tr>
          <tr><td><strong>Status</strong></td><td>{{ $member->status }}</td></tr>
          <tr><td><strong>Member Account</strong></td><td>{{ $member->user ? 'Linked' : 'Not Linked' }}</td></tr>
        </tbody>
      </table>

      <h3 style="font-size:0.95rem; margin: 12px 0 6px;">Member Financial Profile</h3>
      <table class="app-table" style="margin-bottom: 1rem;">
        <tbody>
          <tr><td><strong>Monthly Income</strong></td><td>{{ \App\Support\Format::money($member->monthly_income) }}</td></tr>
          <tr><td><strong>Savings Balance</strong></td><td>{{ \App\Support\Format::money($member->savings_balance) }}</td></tr>
          <tr><td><strong>Share Capital</strong></td><td>{{ \App\Support\Format::money($member->share_capital) }}</td></tr>
          <tr><td><strong>Approved Credit Limit</strong></td><td>{{ \App\Support\Format::money($member->approved_credit_limit) }}</td></tr>
          <tr><td><strong>Existing Monthly Obligations</strong></td><td>{{ \App\Support\Format::money($member->existing_monthly_obligations) }}</td></tr>
        </tbody>
      </table>

      @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::EDIT_MEMBER_PROFILES))
      <form action="{{ route('admin.members.update', $member->id) }}" method="POST">
        @csrf
        @method('PUT')
        <h3 style="font-size:0.95rem; margin: 12px 0 6px;">Edit Profile & Financial Profile</h3>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px;">
          <label style="font-size:0.75rem;">Contact Number
            <input class="form-input" type="text" name="contact_number" value="{{ old('contact_number', $member->contact_number) }}" />
          </label>
          <label style="font-size:0.75rem;">Address
            <input class="form-input" type="text" name="address" value="{{ old('address', $member->address) }}" />
          </label>
          <label style="font-size:0.75rem;">Occupation
            <input class="form-input" type="text" name="occupation" value="{{ old('occupation', $member->occupation) }}" />
          </label>
          <label style="font-size:0.75rem;">Monthly Income (₱)
            <input class="form-input" type="number" step="0.01" name="monthly_income" value="{{ old('monthly_income', $member->monthly_income) }}" />
          </label>
          <label style="font-size:0.75rem;">Existing Monthly Obligations (₱)
            <input class="form-input" type="number" step="0.01" name="existing_monthly_obligations" value="{{ old('existing_monthly_obligations', $member->existing_monthly_obligations) }}" />
          </label>
          <label style="font-size:0.75rem;">Savings Balance (₱)
            <input class="form-input" type="number" step="0.01" name="savings_balance" value="{{ old('savings_balance', $member->savings_balance) }}" />
          </label>
          <label style="font-size:0.75rem;">Share Capital (₱)
            <input class="form-input" type="number" step="0.01" name="share_capital" value="{{ old('share_capital', $member->share_capital) }}" />
          </label>
          <label style="font-size:0.75rem;">Approved Credit Limit (₱)
            <input class="form-input" type="number" step="0.01" name="approved_credit_limit" value="{{ old('approved_credit_limit', $member->approved_credit_limit) }}" />
          </label>
        </div>
        <button type="submit" class="btn-review" style="margin-top:10px;">Save Changes</button>
      </form>
      @endif
    </div>

    <div class="panel app-panel">
      <div class="page-header">
        <h2 class="panel-title">Loan Applications ({{ $loans->count() }})</h2>
      </div>
      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>APP ID</th>
              <th>AMOUNT</th>
              <th>TERM</th>
              <th>STATUS</th>
              <th>RECOMMENDED</th>
              <th>RISK</th>
              <th>REMAINING BALANCE</th>
              <th>NEXT DUE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            @forelse($loans as $loan)
              @php $mirror = $mirrors->get($loan->id); @endphp
              <tr>
                <td class="td-mono">{{ $mirror->app_id ?? '—' }}</td>
                <td class="td-mono">{{ \App\Support\Format::money($loan->amount) }}</td>
                <td>{{ $loan->term_months }} mo</td>
                <td><span class="status-pill">{{ $loan->getStatusLabelAttribute() }}</span></td>
                <td class="td-mono">{{ $loan->recommended_amount > 0 ? \App\Support\Format::money($loan->recommended_amount) : '—' }}</td>
                <td>{{ $loan->risk_level }}</td>
                <td class="td-mono">{{ \App\Support\Format::money($loan->remaining_balance) }}</td>
                <td>{{ \App\Support\Format::date($loan->next_due_date) }}</td>
                <td>
                  @if($mirror)
                  <a class="btn-review" href="#" data-app-id="{{ $mirror->app_id }}" data-numeric-id="{{ $mirror->id }}" onclick="openAppDetail(event, '{{ $mirror->id }}', '{{ $mirror->app_id ?? '' }}')">View</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="empty-table-msg">No loan applications.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if($overdue->isNotEmpty())
    <div class="panel app-panel">
      <div class="page-header"><h2 class="panel-title">Overdue Loans ({{ $overdue->count() }})</h2></div>
      <div class="table-wrap">
        <table class="app-table">
          <thead><tr><th>APP ID</th><th>AMOUNT</th><th>REMAINING BALANCE</th><th>NEXT DUE</th></tr></thead>
          <tbody>
            @foreach($overdue as $loan)
              <tr>
                <td class="td-mono">{{ $mirrors->get($loan->id)?->app_id ?? '—' }}</td>
                <td class="td-mono">{{ \App\Support\Format::money($loan->amount) }}</td>
                <td class="td-mono">{{ \App\Support\Format::money($loan->remaining_balance) }}</td>
                <td>{{ \App\Support\Format::date($loan->next_due_date) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    <div class="panel app-panel">
      <div class="page-header"><h2 class="panel-title">Payment History ({{ $payments->count() }} payments — {{ \App\Support\Format::money($totalPaid) }} collected)</h2></div>
      <div class="table-wrap">
        <table class="app-table">
          <thead><tr><th>APP ID</th><th>AMOUNT PAID</th><th>DATE PAID</th><th>METHOD</th></tr></thead>
          <tbody>
            @forelse($payments as $p)
              <tr>
                <td class="td-mono">{{ $mirrors->get($p->loan_application_id)?->app_id ?? '—' }}</td>
                <td class="td-mono">{{ \App\Support\Format::money($p->amount_paid) }}</td>
                <td>{{ \App\Support\Format::date($p->payment_date) }}</td>
                <td>{{ $p->payment_method }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="empty-table-msg">No payments recorded yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel app-panel">
      <div class="page-header"><h2 class="panel-title">Account Summary</h2></div>
      <table class="app-table">
        <tbody>
          <tr><td><strong>Live loans</strong></td><td>{{ $activeLoans->count() }}</td></tr>
          <tr><td><strong>Outstanding balance</strong></td><td>{{ \App\Support\Format::money($outstanding) }}</td></tr>
          <tr><td><strong>Total paid</strong></td><td>{{ \App\Support\Format::money($totalPaid) }}</td></tr>
          <tr><td><strong>Overdue count</strong></td><td>{{ $overdue->count() }}</td></tr>
          @if($nextDue)
          <tr><td><strong>Next due date</strong></td><td>{{ \App\Support\Format::date($nextDue->next_due_date) }}</td></tr>
          @endif
        </tbody>
      </table>
    </div>

</div>
@endsection