{{-- Loan application form. Rendered inside user/loan.blade.php. --}}
<script>
  window.SDCC_LOAN_MATRIX = @json(config('loan_matrix.matrix'), JSON_THROW_ON_ERROR);
</script>
<script src="{{ asset('js/loan-form.js') }}" defer></script>

<div class="form-header">
  <h1>Loan application</h1>
  <p>Fill in your details below. Fields marked with * are required. You'll get a reference number as soon as you submit, and a decision within 48 hours.</p>
</div>

<form method="POST" action="{{ route('user.loans.store') }}" class="form-shell" id="loanForm" enctype="multipart/form-data" autocomplete="on">
  @csrf

  <div class="form-card">

    @if ($errors->any())
      <div class="form-error-summary" role="alert">
        <p><strong>{{ $errors->count() }} issue{{ $errors->count() > 1 ? 's' : '' }} found:</strong></p>
        <ul>
          @foreach ($errors->all() as $error)
            <li><a href="#{{ $error->field() }}" onclick="document.getElementById('{{ $error->field() }}')?.focus()">{{ $error->getMessage() }}</a></li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="form-stepper" id="formStepper">
      <div class="step active" data-step="1"><span class="step-num">1</span><span>Personal</span></div>
      <div class="step-sep"></div>
      <div class="step" data-step="2"><span class="step-num">2</span><span>Income</span></div>
      <div class="step-sep"></div>
      <div class="step" data-step="3"><span class="step-num">3</span><span>Loan</span></div>
      <div class="step-sep"></div>
      <div class="step" data-step="4"><span class="step-num">4</span><span>Review</span></div>
    </div>

    <div class="form-main">

      <fieldset class="fieldset" data-step="1">
        <legend>About you</legend>

        <div class="grid-2">
          <div class="field">
            <label for="full_name">Full name *</label>
            <input type="text" id="full_name" name="full_name"
                   value="{{ old('full_name', $user->name) }}"
                   class="@error('full_name') has-error @enderror" required autocomplete="name">
            @error('full_name') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field">
            <label for="email">Email address *</label>
            <input type="email" id="email" name="email"
                   value="{{ old('email', $user->email) }}"
                   class="@error('email') has-error @enderror" required autocomplete="email">
            @error('email') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field">
            <label for="contact_number">Mobile number *</label>
            <input type="tel" id="contact_number" name="contact_number"
                   value="{{ old('contact_number') }}" placeholder="09XX XXX XXXX"
                   inputmode="numeric" pattern="[0-9]{11}" maxlength="11"
                   title="Enter exactly 11 digits"
                   class="@error('contact_number') has-error @enderror" required autocomplete="tel">
            @error('contact_number') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field">
            <label for="birth_date">Birth date *</label>
            <input type="date" id="birth_date" name="birth_date"
                   value="{{ old('birth_date') }}"
                   class="@error('birth_date') has-error @enderror" required autocomplete="bday">
            @error('birth_date') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field full">
            <label for="address">Home address *</label>
            <input type="text" id="address" name="address" value="{{ old('address') }}"
                   placeholder="House no., street, barangay, city"
                   class="@error('address') has-error @enderror" required autocomplete="street-address">
            @error('address') <span class="error-text">{{ $message }}</span> @enderror
          </div>
        </div>
      </fieldset>

      <fieldset class="fieldset" data-step="2">
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
            <select id="monthly_income" name="monthly_income"
                    class="@error('monthly_income') has-error @enderror" required>
              <option value="">Select a range</option>
              @foreach (config('loan_matrix.form_options.monthly_income') as [$value, $label])
                <option value="{{ $value }}" @selected(old('monthly_income') == $value)>{{ $label }}</option>
              @endforeach
            </select>
            @error('monthly_income') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field">
            <label for="source_of_income">Source of income *</label>
            <select id="source_of_income" name="source_of_income"
                    class="@error('source_of_income') has-error @enderror" required>
              <option value="">Select one</option>
              @foreach (config('loan_matrix.form_options.source_of_income') as $source)
                <option value="{{ $source }}" @selected(old('source_of_income') === $source)>{{ $source }}</option>
              @endforeach
            </select>
            @error('source_of_income') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field" id="employerNameField">
            <label for="employer_name">Employer or business name</label>
            <input type="text" id="employer_name" name="employer_name" value="{{ old('employer_name') }}">
          </div>
        </div>
      </fieldset>

      <fieldset class="fieldset" data-step="3">
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
            <input type="number" id="amount" name="amount" min="1000" max="2000000" step="100" inputmode="numeric"
                   value="{{ old('amount') }}"
                   class="@error('amount') has-error @enderror" required autocomplete="off">
            @error('amount') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          <div class="field">
            <label for="term_months">Payment term *</label>
            <select id="term_months" name="term_months" class="@error('term_months') has-error @enderror" required autocomplete="off">
              <option value="">Select term</option>
              @foreach ([6, 12, 18, 24, 36] as $term)
                <option value="{{ $term }}" @selected((int) old('term_months', 12) === $term)>{{ $term }} months</option>
              @endforeach
            </select>
            @error('term_months') <span class="error-text">{{ $message }}</span> @enderror
          </div>

          </div>
      </fieldset>

      <fieldset class="fieldset" data-step="4">
        <legend>Required documents</legend>
        <div id="requirementsPanel" class="requirements-panel">
          <p class="requirements-hint">Select a loan product to see the documents you will need.</p>
        </div>
        <div class="field">
          <label for="required_documents">Upload supporting documents</label>
          <div class="file-dropzone" id="fileDropzone">
            <label for="required_documents" class="dz-label">
              <span class="dz-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
              <span class="dz-text">Tap to take a photo or pick files</span>
              <span class="dz-hint">JPG, PNG or PDF · up to 10 files</span>
            </label>
            <input type="file" id="required_documents" name="required_documents[]"
                   accept=".jpg,.jpeg,.png,.pdf" multiple
                   capture="user" class="sr-only">
          </div>
          <div class="file-preview" id="filePreview"></div>
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
  </div>

  <aside class="estimate" aria-live="polite" role="status">
    <h3>Estimated repayment</h3>
    <div class="estimate-status" id="estimateStatus">
      <span class="status-indicator" aria-hidden="true"></span>
      <span class="status-text" id="statusText">Enter amount and term</span>
    </div>
    <div class="estimate-amount" id="estMonthly">₱0.00</div>
    <div class="estimate-caption">per month</div>

    <div class="estimate-breakdown">
      <div class="estimate-line"><span>Principal</span><span id="estPrincipal">₱0.00</span></div>
      <div class="estimate-line"><span id="estRateLabel">Estimated interest</span><span id="estInterest">₱0.00</span></div>
      <div class="estimate-line total"><span>Total payable</span><span id="estTotal">₱0.00</span></div>
    </div>

    <p class="estimate-foot">Indicative only. Your final rate depends on your loan product, membership standing, and share capital.</p>
  </aside>
</form>
