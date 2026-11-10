<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>Apply for a Loan — San Dionisio Credit Cooperative</title>
</head>
<body>

  <header>
    @include('user.header')
  </header>

  @include('user.sidebar')

  <main class="member-main">
    @include('user.flash')
    @include('user.loan-form')
  </main>


  @include('user.scripts')

  <script>
    // ─── Live repayment estimate ───
    (function () {
      const amount = document.getElementById('amount');
      const term   = document.getElementById('term_months');
      const loanType = document.getElementById('loan_type');
      if (!amount || !term || !loanType) return;

      const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const MATRIX = window.SDCC_LOAN_MATRIX || {};

      function update() {
        const principal = parseFloat(amount.value) || 0;
        const months    = parseInt(term.value, 10) || 12;
        const config    = MATRIX[loanType.value] || {};
        const rate      = Number(config.rate) || 0.12;
        const interest  = principal * rate * (months / 12);
        const total     = principal + interest;

        document.getElementById('estPrincipal').textContent = peso(principal);
        document.getElementById('estRateLabel').textContent = 'Estimated interest (' + (rate * 100).toFixed(0) + '% p.a.)';
        document.getElementById('estInterest').textContent  = peso(interest);
        document.getElementById('estTotal').textContent     = peso(total);
        document.getElementById('estMonthly').textContent   = peso(months ? total / months : 0);
      }

      amount.addEventListener('input', update);
      term.addEventListener('change', update);
      loanType.addEventListener('change', update);
      update();
    })();
  </script>

  {{-- Credit Limit Card for the member --}}
  <div style="margin-top: 2rem; padding: 1rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;">
    <div style="font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: .05em; color: #1e293b; margin-bottom: .5rem;">Credit Limit</div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 2rem; font-size: 0.875rem;">
      <div>
        <strong>Approved Limit</strong>
        @if($coopMember?->hasAssessedCreditLimit())
          <span style="font-family:monospace;">₱{{ number_format($coopMember->approved_credit_limit, 2) }}</span>
        @else
          <span style="color: var(--muted); font-style: italic;">Not assessed</span>
        @endif
      </div>
      <div>
        <strong>Outstanding</strong>
        @if(isset($outstanding))
          <span style="font-family:monospace;">₱{{ number_format($outstanding, 2) }}</span>
        @else
          <span style="color: var(--muted);">N/A</span>
        @endif
      </div>
    </div>
    <div style="margin-top: .5rem; padding-top: .5rem; border-top: 1px solid #e2e8f0; font-size: 0.875rem;">
      <strong>Available</strong>
      @if($coopMember?->hasAssessedCreditLimit() && isset($outstanding))
        <span style="font-family:monospace;">₱{{ number_format($coopMember->approved_credit_limit - $outstanding, 2) }}</span>
      @else
        <span style="color: var(--muted);">N/A</span>
      @endif
    </div>
  </div>
</body>
</html>