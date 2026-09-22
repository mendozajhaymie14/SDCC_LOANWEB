{{-- Loan application form. Rendered inside user/loan.blade.php. --}}
<script>
  window.SDCC_LOAN_MATRIX = @json(\App\Http\Controllers\UserController::MATRIX, JSON_THROW_ON_ERROR);
</script>

<div class="form-header">
  <h1>Loan application</h1>
  <p>Fill in your details below. Fields marked with * are required. You'll get a reference number as soon as you submit, and a decision within 48 hours.</p>
</div>

<form method="POST" action="{{ route('user.loans.store') }}" class="form-shell" id="loanForm" novalidate enctype="multipart/form-data">
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

        <div class="field">
          <label for="birth_date">Birth date *</label>
          <input type="date" id="birth_date" name="birth_date"
                 value="{{ old('birth_date') }}"
                 class="@error('birth_date') has-error @enderror" required>
          @error('birth_date') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="civil_status">Civil status *</label>
          <select id="civil_status" name="civil_status"
                  class="@error('civil_status') has-error @enderror" required>
            <option value="">Select one</option>
            @foreach (['Single', 'Married', 'Widowed', 'Divorced', 'Separated'] as $status)
              <option value="{{ $status }}" @selected(old('civil_status') === $status)>{{ $status }}</option>
            @endforeach
          </select>
          @error('civil_status') <span class="error-text">{{ $message }}</span> @enderror
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
              <option value="{{ $name }}" @selected(old('loan_type') === $name)>{{ $description }}</option>
            @endforeach
          </select>
          @error('loan_type') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field">
          <label for="amount">Amount * <span class="hint" id="amountHint">(₱1,000 – ₱2,000,000)</span></label>
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

        <div class="field full">
          <label for="collateral">Collateral</label>
          <select id="collateral" name="collateral" class="@error('collateral') has-error @enderror">
            <option value="">Select One</option>
            <option value="None">None</option>
            <option value="Cart">Cart</option>
            <option value="Chattel">Chattel</option>
            <option value="Real Estate Mortgage">Real Estate Mortgage (REM)</option>
            <option value="TCT and Machinery &amp; Equipment">TCT and Machinery &amp; Equipment</option>
            <option value="100% Share Capital and Savings/Time Deposit">100% Share Capital and Savings/Time Deposit</option>
            <option value="100% Share Capital">100% Share Capital</option>
          </select>
          @error('collateral') <span class="error-text">{{ $message }}</span> @enderror
        </div>

        <div class="field full">
          <label for="purpose">Loan purpose *</label>
          <textarea id="purpose" name="purpose" rows="3" placeholder="Briefly describe what the loan is for"
                    class="@error('purpose') has-error @enderror" required>{{ old('purpose') }}</textarea>
          @error('purpose') <span class="error-text">{{ $message }}</span> @enderror
        </div>
      </div>
    </fieldset>

    <fieldset class="fieldset">
      <legend>Required documents</legend>
      <div id="requirementsPanel" class="requirements-panel">
        <p class="requirements-hint">Select a loan product to see the documents you will need.</p>
      </div>
      <div class="field" style="margin-top: 16px;">
        <label for="required_documents">Upload supporting documents</label>
        <input type="file" id="required_documents" name="required_documents[]"
               accept=".jpg,.jpeg,.png,.pdf" multiple>
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
    <div class="estimate-line"><span id="estRateLabel">Estimated interest</span><span id="estInterest">₱0.00</span></div>
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

    // ─── SDCC Loan Matrix: drive limits, terms, collateral, and requirements ───
    var MATRIX = window.SDCC_LOAN_MATRIX || {};
    var loanType = document.getElementById('loan_type');
    var amountInput = document.getElementById('amount');
    var amountHint = document.getElementById('amountHint');
    var termSelect = document.getElementById('term_months');
    var collateralSelect = document.getElementById('collateral');
    var requirementsPanel = document.getElementById('requirementsPanel');
    var purposeInput = document.getElementById('purpose');

    function money(value) {
      return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function productConfig() {
      return MATRIX[loanType ? loanType.value : ''] || {};
    }

    function rebuildTermOptions(terms) {
      if (!termSelect) return;
      termSelect.innerHTML = '';
      var defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = 'Select term';
      termSelect.appendChild(defaultOption);

      (terms || []).forEach(function (term) {
        var opt = document.createElement('option');
        opt.value = term;
        opt.textContent = term + ' months';
        termSelect.appendChild(opt);
      });
    }

    function renderRequirements(config) {
      if (!requirementsPanel) return;
      var requirements = config.requirements || [];
      if (!requirements.length) {
        requirementsPanel.innerHTML = '<p class="requirements-hint">No special documents are listed for this product.</p>';
        return;
      }
      requirementsPanel.innerHTML = '<ul class="requirements-list">' + requirements.map(function (item) {
        return '<li>' + item + '</li>';
      }).join('') + '</ul>';
    }

    function updateMatrixFields() {
      var config = productConfig();
      if (!config) {
        return;
      }

      // Amount limit
      var maxAmount = config.max_amount || 2000000;
      maxAmount = Math.min(maxAmount, 2000000);
      amountInput.max = maxAmount;
      amountInput.min = maxAmount < 1000 ? 1 : 1000;
      amountHint.textContent = '(' + money(amountInput.min) + ' – ' + money(maxAmount) + ')';

      // Terms
      rebuildTermOptions(config.terms || [12]);
      if (termSelect.value && (config.terms || []).indexOf(Number(termSelect.value)) === -1) {
        termSelect.value = '';
      }

      // Collateral
      if (collateralSelect) {
        var options = config.collateral_options || ['None'];
        collateralSelect.innerHTML = '';
        options.forEach(function (value) {
          var opt = document.createElement('option');
          opt.value = value;
          opt.textContent = value === 'None' ? 'None required' : value;
          collateralSelect.appendChild(opt);
        });
        collateralSelect.required = options.indexOf('None') === -1;
      }

      // Requirements
      renderRequirements(config);
    }

    if (loanType) {
      loanType.addEventListener('change', updateMatrixFields);
      updateMatrixFields();
    }
    // ─── Document upload: accept JPG, PNG, PDF only ───
    var documentUpload = document.getElementById('required_documents');
    if (documentUpload) {
      documentUpload.addEventListener('change', function () {
        var files = documentUpload.files;
        var invalid = [];
        for (var i = 0; i < files.length; i++) {
          var ext = files[i].name.split('.').pop().toLowerCase();
          if (['jpg', 'jpeg', 'png', 'pdf'].indexOf(ext) === -1) {
            invalid.push(files[i].name);
          }
        }
        if (invalid.length) {
          alert('These files are not allowed: ' + invalid.join(', ') + '\nOnly JPG, PNG, and PDF files are accepted.');
          documentUpload.value = '';
        }
      });
    }
  })();
</script>
