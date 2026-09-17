{{-- Loan application form. Rendered inside user/loan.blade.php. --}}

<div class="form-header">
  <h1>Loan application</h1>
  <p>Fill in your details below. Fields marked with * are required. You'll get a reference number as soon as you submit, and a decision within 48 hours.</p>
</div>

<form method="POST" action="{{ route('user.loans.store') }}" class="form-shell" id="loanForm" novalidate>
  @csrf

  <div class="form-card">

    <fieldset class="fieldset">
      <legend>About you</legend>

      <div class="grid-2">
        <div class="field">
          <label for="full_name">Full name *</label>
          <input type="text" id="full_name" name="full_name"
                 value="{{ old('full_name', $user->name) }}"
                 class="@error('full_name') has-error @enderror" required>
          @error('full_name') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="email">Email address *</label>
          <input type="email" id="email" name="email"
                 value="{{ old('email', $user->email) }}"
                 class="@error('email') has-error @enderror" required>
          @error('email') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="contact_number">Mobile number *</label>
          <input type="tel" id="contact_number" name="contact_number"
                 value="{{ old('contact_number') }}" placeholder="09XX XXX XXXX"
                 class="@error('contact_number') has-error @enderror" required>
          @error('contact_number') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field full">
          <label for="address">Home address *</label>
          <input type="text" id="address" name="address" value="{{ old('address') }}"
                 placeholder="House no., street, barangay, city"
                 class="@error('address') has-error @enderror" required>
          @error('address') <span class="error-text">{{ $message }}</span> @enderror
        </div>
      </div>
    </fieldset>

    <fieldset class="fieldset">
      <legend>Income</legend>

      <div class="grid-2">
        <div class="field">
          <label for="employment_status">Employment status *</label>
          <select id="employment_status" name="employment_status"
                  class="@error('employment_status') has-error @enderror" required>
            <option value="">Select one</option>
            @foreach (['Employed', 'Self-employed', 'Business Owner', 'Retired', 'Student'] as $status)
              <option value="{{ $status }}" @selected(old('employment_status') === $status)>{{ $status }}</option>
            @endforeach
          </select>
          @error('employment_status') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field" id="employerNameField">
          <label for="employer_name">Employer or business name</label>
          <input type="text" id="employer_name" name="employer_name" value="{{ old('employer_name') }}">
        </div>

        <div class="field">
          <label for="monthly_income">Monthly income * <span class="hint">(in pesos)</span></label>
          <input type="number" id="monthly_income" name="monthly_income" min="1" step="0.01"
                 value="{{ old('monthly_income') }}"
                 class="@error('monthly_income') has-error @enderror" required>
          @error('monthly_income') <span class="error-text">{{ $message }}</span> @enderror
        </div>
      </div>
    </fieldset>

    <fieldset class="fieldset">
      <legend>Loan details</legend>

      <div class="grid-2">
        <div class="field full">
          <label for="loan_type">Loan product *</label>
          <select id="loan_type" name="loan_type" class="@error('loan_type') has-error @enderror" required>
            <option value="">Select a loan product</option>
            @foreach ($loanTypes as $name => $description)
              <option value="{{ $name }}" @selected(old('loan_type') === $name)>{{ $name }} — {{ $description }}</option>
            @endforeach
          </select>
          @error('loan_type') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="amount">Amount * <span class="hint">(₱1,000 – ₱2,000,000)</span></label>
          <input type="number" id="amount" name="amount" min="1000" max="2000000" step="100"
                 value="{{ old('amount') }}"
                 class="@error('amount') has-error @enderror" required>
          @error('amount') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="term_months">Payment term *</label>
          <select id="term_months" name="term_months" class="@error('term_months') has-error @enderror" required>
            <option value="">Select term</option>
            @foreach ([6, 12, 18, 24, 36, 48, 60] as $term)
              <option value="{{ $term }}" @selected((int) old('term_months', 12) === $term)>{{ $term }} months</option>
            @endforeach
          </select>
          @error('term_months') <span class="error-text">{{ $message }}</span> @enderror
        </div>
      </div>
    </fieldset>

    <div class="form-actions">
      <button type="submit" class="btn-solid">Submit application</button>
      <a href="{{ route('user.dashboard') }}" class="btn-cancel">Cancel</a>
    </div>
  </div>

  <aside class="estimate" aria-live="polite">
    <h2>Estimated repayment</h2>
    <div class="estimate-amount" id="estMonthly">₱0.00</div>
    <div class="estimate-caption">per month</div>

    <div class="estimate-line"><span>Principal</span><span id="estPrincipal">₱0.00</span></div>
    <div class="estimate-line"><span>Interest (12% p.a.)</span><span id="estInterest">₱0.00</span></div>
    <div class="estimate-line"><span>Total payable</span><span id="estTotal">₱0.00</span></div>

    <p class="estimate-foot">Indicative only. Your final rate depends on your loan product, membership standing, and share capital.</p>
  </aside>
</form>

<script>
  (function () {
    // ─── Hide/show employer name based on employment status ───
    var employmentStatus = document.getElementById('employment_status');
    var employerField = document.getElementById('employerNameField');

    if (employmentStatus && employerField) {
      function toggleEmployer() {
        var val = employmentStatus.value;
        if (val === 'Self-employed' || val === 'Unemployed' || val === 'Retired') {
          employerField.style.display = 'none';
          var input = employerField.querySelector('input');
          if (input) { input.removeAttribute('name'); input.removeAttribute('required'); }
        } else {
          employerField.style.display = '';
          var input = employerField.querySelector('input');
          if (input) { input.setAttribute('name', 'employer_name'); }
        }
      }
      employmentStatus.addEventListener('change', toggleEmployer);
      toggleEmployer();
    }

    // ─── Dynamic payment term based on loan product ───
    var loanType = document.getElementById('loan_type');
    var termSelect = document.getElementById('term_months');

    var TERM_OPTIONS = {
      'short': [6, 12],         // Salary, Emergency, Business
      'long':  [6]              // Educational, Appliance
    };

    // Loan types that map to max 12 months
    var SHORT_TERM_LOANS = ['Salary Loan', 'Emergency Loan', 'Business Loan'];

    function updateTermOptions() {
      if (!loanType || !termSelect) return;
      var val = loanType.value;
      var options = SHORT_TERM_LOANS.indexOf(val) !== -1 ? TERM_OPTIONS['short'] : TERM_OPTIONS['long'];

      // Rebuild options
      termSelect.innerHTML = '';
      var defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = 'Select term';
      termSelect.appendChild(defaultOption);

      options.forEach(function (term) {
        var opt = document.createElement('option');
        opt.value = term;
        opt.textContent = term + ' months';
        termSelect.appendChild(opt);
      });

      // Auto-select first valid option
      if (options.length > 0) {
        termSelect.value = String(options[0]);
      }
    }

    if (loanType && termSelect) {
      loanType.addEventListener('change', updateTermOptions);
      updateTermOptions();
    }
  })();
</script>
