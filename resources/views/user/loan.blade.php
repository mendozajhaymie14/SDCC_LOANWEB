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

</body>
</html>