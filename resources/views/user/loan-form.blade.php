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
                 inputmode="numeric" pattern="[0-9]{11}" maxlength="11"
                 title="Enter exactly 11 digits"
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
          <label for="member_status">Member status *</label>
          <select id="member_status" name="member_status"
                  class="@error('member_status') has-error @enderror" required>
            <option value="">Select one</option>
            @foreach (['Employed', 'Self-employed', 'Business Owner', 'Student'] as $status)
              <option value="{{ $status }}" @selected(old('member_status') === $status)>{{ $status }}</option>
            @endforeach
          </select>
          @error('member_status') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="monthly_income">Monthly income * <span class="hint">(in pesos)</span></label>
          <input type="number" id="monthly_income" name="monthly_income" min="1" step="0.01"
                 value="{{ old('monthly_income') }}"
                 class="@error('monthly_income') has-error @enderror" required>
          @error('monthly_income') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="source_of_income">Source of income *</label>
          <input type="text" id="source_of_income" name="source_of_income"
                 value="{{ old('source_of_income') }}"
                 inputmode="text" pattern="[A-Za-z][A-Za-z .'-]*"
                 title="Letters only (no numbers)"
                 class="@error('source_of_income') has-error @enderror" required>
          @error('source_of_income') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field" id="employerNameField">
          <label for="employer_name">Employer or business name</label>
          <input type="text" id="employer_name" name="employer_name" value="{{ old('employer_name') }}">
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
              <option value="{{ $name }}" @selected(old('loan_type') === $name)>{{ $name }}</option>
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
            @foreach ([6, 12, 18, 24, 36] as $term)
              <option value="{{ $term }}" @selected((int) old('term_months', 12) === $term)>{{ $term }} months</option>
            @endforeach
          </select>
          @error('term_months') <span class="error-text">{{ $message }}</span> @enderror
        </div>
      </div>
    </fieldset>

    <div class="field checkbox-field">
      <input type="checkbox" id="agree" name="agree" value="1" @checked(old('agree'))>
      <label for="agree">I confirm that my details are true and correct.</label>
      @error('agree') <span class="error-text">{{ $message }}</span> @enderror
    </div>

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
    // ─── Remove spinner arrows + disable mouse wheel on number inputs ───
    document.querySelectorAll('input[type="number"]').forEach(function (input) {
      // Hide spinner arrows (Chrome, Safari, Edge, Firefox)
      input.style.mozAppearance = 'textfield';
      input.addEventListener('mousewheel', function (e) { e.preventDefault(); }, { passive: false });
    });
    var style = document.createElement('style');
    style.textContent = 'input[type="number"]::-webkit-outer-spin-button, ' +
                        'input[type="number"]::-webkit-inner-spin-button { ' +
                        '-webkit-appearance: none; margin: 0; } ' +
                        'input[type="number"] { -moz-appearance: textfield; }';
    document.head.appendChild(style);

    // ─── Mobile number: digits only, exactly 11 digits ───
    var mobileInput = document.getElementById('contact_number');
    if (mobileInput) {
      mobileInput.addEventListener('input', function () {
        var cleaned = mobileInput.value.replace(/\D/g, '');
        if (cleaned.length > 11) { cleaned = cleaned.slice(0, 11); }
        mobileInput.value = cleaned;
      });
      mobileInput.addEventListener('blur', function () {
        if (mobileInput.value && mobileInput.value.length !== 11) {
          mobileInput.setCustomValidity('Please enter exactly 11 digits.');
        } else {
          mobileInput.setCustomValidity('');
        }
      });
    }

    // ─── Source of income: letters only (no digits) ───
    var sourceInput = document.getElementById('source_of_income');
    if (sourceInput) {
      sourceInput.addEventListener('input', function () {
        sourceInput.value = sourceInput.value.replace(/[0-9]/g, '');
      });
      sourceInput.addEventListener('blur', function () {
        if (sourceInput.value && /[^A-Za-z .'-]/.test(sourceInput.value)) {
          sourceInput.setCustomValidity('Source of income must contain letters only.');
        } else {
          sourceInput.setCustomValidity('');
        }
      });
    }

    // ─── Hide/show employer name based on member status ───
    var memberStatus = document.getElementById('member_status');
    var employerField = document.getElementById('employerNameField');

    if (memberStatus && employerField) {
      function toggleEmployer() {
        var val = memberStatus.value;
        if (val === 'Student') {
          employerField.style.display = 'none';
          var input = employerField.querySelector('input');
          if (input) { input.removeAttribute('name'); input.removeAttribute('required'); }
        } else {
          employerField.style.display = '';
          var input = employerField.querySelector('input');
          if (input) { input.setAttribute('name', 'employer_name'); }
        }
      }
      memberStatus.addEventListener('change', toggleEmployer);
      toggleEmployer();
    }

    // ─── Payment terms: 6 to 36 months for every loan product ───
    var loanType = document.getElementById('loan_type');
    var termSelect = document.getElementById('term_months');
    var TERM_OPTIONS = [6, 12, 18, 24, 36];

    function updateTermOptions() {
      if (!termSelect) return;

      // Rebuild options
      termSelect.innerHTML = '';
      var defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = 'Select term';
      termSelect.appendChild(defaultOption);

      TERM_OPTIONS.forEach(function (term) {
        var opt = document.createElement('option');
        opt.value = term;
        opt.textContent = term + ' months';
        termSelect.appendChild(opt);
      });

      // Auto-select first valid option
      termSelect.value = String(TERM_OPTIONS[0]);
    }

    if (loanType && termSelect) {
      loanType.addEventListener('change', updateTermOptions);
      updateTermOptions();
    }
  })();
</script>
