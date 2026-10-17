@php($isPaid = $application->status === 'paid')
<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>Amortization Schedule — San Dionisio Credit Cooperative</title>
</head>
<body>

  @include('user.header')

  @include('user.sidebar')

  <main class="member-main">
    @include('user.flash')

    {{-- Header --}}
    <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 28px; flex-wrap: wrap;">
      <div>
        <h1 style="font-family: 'Bebas Neue', sans-serif; font-size: 30px; letter-spacing: 1px; color: var(--text-dark); margin: 0;">
          Amortization Schedule
        </h1>
        <p style="margin: 4px 0 0; font-size: 13px; color: #7a927e;">
          Reference: {{ $application->reference }} • {{ $application->loan_type_label }}
        </p>
      </div>

      {{-- Download PDF --}}
      <a href="{{ route('export.amortization.pdf', $application) }}"
         class="btn-solid"
         style="display: inline-flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-file-pdf"></i> Download PDF
      </a>
    </div>

    {{-- Top Summary Card --}}
    <div style="background: var(--white); border: 1px solid #e2f0e4; border-radius: 20px; padding: 28px 32px; margin-bottom: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
      <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #7a927e; margin-bottom: 18px;">Loan Summary</div>

      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
        <div>
          <div style="font-size: 12px; color: #7a927e; margin-bottom: 4px;">Total Loan Amount</div>
          <div style="font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-dark);">
            ₱{{ number_format($application->amount, 2) }}
          </div>
        </div>

        <div>
          <div style="font-size: 12px; color: #7a927e; margin-bottom: 4px;">Interest Rate</div>
          <div style="font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--text-dark);">
            12% p.a.
          </div>
        </div>

        <div>
          <div style="font-size: 12px; color: #7a927e; margin-bottom: 4px;">Total Payable</div>
          <div style="font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--green-deep);">
            ₱{{ number_format($summary['total_paid'] ?? 0, 2) }}
          </div>
        </div>

        <div>
          <div style="font-size: 12px; color: #7a927e; margin-bottom: 4px;">Monthly Amortization</div>
          <div style="font-family: 'DM Sans', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--green-deep);">
            ₱{{ number_format($summary['emi'] ?? 0, 2) }}
          </div>
        </div>
      </div>

      @if ($application->term_months)
        <div style="margin-top: 18px; font-size: 13px; color: #7a927e;">
          <span style="font-weight: 600; color: var(--text-dark);">{{ $application->term_months }}</span>
          -monthly term &nbsp;|&nbsp; Total Interest:
          <span style="font-weight: 600; color: var(--text-dark);">₱{{ number_format($summary['total_interest'] ?? 0, 2) }}</span>
          &nbsp;|&nbsp; Remaining Balance:
          <span style="font-weight: 600; color: var(--text-dark);">₱{{ number_format((float)($application->remaining_balance ?? 0), 2) }}</span>
        </div>
      @endif
    </div>

    @if ($schedule->isNotEmpty())
      {{-- Schedule Table --}}
      <div style="background: var(--white); border: 1px solid #e2f0e4; border-radius: 20px; padding: 28px 32px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #7a927e; margin-bottom: 18px;">Payment Breakdown</div>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
          <thead>
            <tr style="border-bottom: 2px solid #e2f0e4;">
              <th style="padding: 12px 8px; text-align: left; color: #5a7a5e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Month #</th>
              <th style="padding: 12px 8px; text-align: left; color: #5a7a5e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Due Date</th>
              <th style="padding: 12px 8px; text-align: right; color: #5a7a5e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Principal</th>
              <th style="padding: 12px 8px; text-align: right; color: #5a7a5e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Interest</th>
              <th style="padding: 12px 8px; text-align: right; color: #5a7a5e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Total Payment</th>
              <th style="padding: 12px 8px; text-align: right; color: #5a7a5e; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Remaining Balance</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($schedule as $row)
              @php($dueDate = \Carbon\Carbon::parse($row['due_date']))
              <tr style="border-bottom: 1px solid #f0f7f2;">
                <td style="padding: 12px 8px; color: var(--text-dark); font-weight: 600;">{{ $row['month'] }}</td>
                <td style="padding: 12px 8px; color: #5a7a5e;">{{ $dueDate->format('M d, Y') }}</td>
                <td style="padding: 12px 8px; text-align: right; font-family: 'DM Sans', sans-serif; color: var(--text-dark);">₱{{ number_format($row['principal'], 2) }}</td>
                <td style="padding: 12px 8px; text-align: right; font-family: 'DM Sans', sans-serif; color: #5a7a5e;">₱{{ number_format($row['interest'], 2) }}</td>
                <td style="padding: 12px 8px; text-align: right; font-family: 'DM Sans', sans-serif; font-weight: 600; color: var(--green-deep);">₱{{ number_format($row['payment'], 2) }}</td>
                <td style="padding: 12px 8px; text-align: right; font-family: 'DM Sans', sans-serif; color: var(--text-dark);">₱{{ number_format($row['remaining_balance'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div style="background: var(--white); border: 1px solid #e2f0e4; border-radius: 20px; padding: 40px; text-align: center; margin-bottom: 32px;">
        <p style="color: #7a927e; margin: 0;">No amortization schedule has been generated yet.
          @if ($application->term_months)
            Term information is available but the schedule could not be computed.
          @else
            This loan application does not have term data.
          @endif
        </p>
      </div>
    @endif
  </main>

  @include('user.scripts')
</body>
</html>
