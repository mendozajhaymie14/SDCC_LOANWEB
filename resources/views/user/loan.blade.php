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

  @include('user.footer')

  @include('user.scripts')

  <script>
    // ─── Live repayment estimate ───
    (function () {
      const RATE   = 0.12; // keep in sync with LoanApplication::RATE
      const amount = document.getElementById('amount');
      const term   = document.getElementById('term_months');
      if (!amount || !term) return;

      const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      function update() {
        const principal = parseFloat(amount.value) || 0;
        const months    = parseInt(term.value, 10) || 12;
        const interest  = principal * RATE * (months / 12);
        const total     = principal + interest;

        document.getElementById('estPrincipal').textContent = peso(principal);
        document.getElementById('estInterest').textContent  = peso(interest);
        document.getElementById('estTotal').textContent     = peso(total);
        document.getElementById('estMonthly').textContent   = peso(months ? total / months : 0);
      }

      amount.addEventListener('input', update);
      term.addEventListener('change', update);
      update();
    })();
  </script>

</body>
</html>