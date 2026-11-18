<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Membership Application | San Dionisio Credit Cooperative</title>
    <link rel="stylesheet" href="{{ asset('css/field-validation.css') }}">
    <script src="{{ asset('js/field-validation.js') }}" defer></script>
</head>
<body>
  @php
    $isAuth = auth()->check();
    $authEmail = $isAuth ? auth()->user()->email : '';
    $user = $isAuth ? auth()->user() : null;
    $firstName = $isAuth ? ($user->first_name ?? $user->name ?? '') : '';
    $surname = $isAuth ? ($user->last_name ?? '') : '';
    $contactNo = $isAuth ? ($user->contact_number ?? $user->phone ?? '') : '';
    $middleName = $isAuth ? ($user->middle_name ?? '') : '';
    $email = $isAuth ? auth()->user()->email : '';
    $phone = $isAuth ? ($user->phone ?? $user->contact_number ?? '') : '';
    // $errors is normally shared by Laravel's ShareErrorsFromSession middleware
    // in real browser requests. Initialize to null for safety outside requests.
    $errors ??= null;
@endphp

<div class="member-app-page" style="width: 100%; min-height: 100vh; overflow-x: hidden; padding: 0; font-family: 'DM Sans', sans-serif;">
    <!-- Page container: centered wrapper with max-w-7xl -->
    <div class="max-w-7xl mx-auto px-4 py-8">

        <div class="member-app-frame flex flex-col lg:grid lg:grid-cols-12 lg:gap-8 items-start"
             style="width: 100%; min-height: 100vh; border: 2px solid var(--green-deep); border-radius: 22px; padding: 0; overflow: hidden; background-color: var(--white); box-sizing: border-box; align-items: stretch; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">

            <!-- Left Hero Banner (desktop sidebar: sticky) -->
            <div class="member-app-banner lg:col-span-4 lg:sticky lg:top-8"
                 style="position: relative; width: 100%; overflow: hidden; background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-position: center;">

                <div style="position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; gap: 16px; width: 100%; padding: 32px; text-align: center;
                            background: linear-gradient(135deg, var(--green-deep) 0%, var(--green-mid) 100%);
                            border-radius: 22px;">

                    <div style="width: 120px; height: 120px; border-radius: 50%; background-color: var(--white); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 6px; box-shadow: 0 8px 24px rgba(0,0,0,0.25);">
                        <img src="{{ asset('images/Logo.jpg') }}"
                             alt="San Dionisio Credit Cooperative Logo"
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    </div>

                    <h1 style="font-family: 'Bebas Neue', sans-serif; font-size: clamp(32px, 5vw, 46px); letter-spacing: 0.5px; line-height: 1.2; color: #ffffff; margin: 0; text-transform: uppercase; text-shadow: 0 2px 10px rgba(0,0,0,0.35);">
                        San Dionisio<br>Credit<br>Cooperative
                    </h1>

                    <p style="font-size: 14px; color: rgba(255,255,255,0.92); max-width: 280px; line-height: 1.6; margin: 0; text-shadow: 0 1px 4px rgba(0,0,0,0.25);">
                        Membership application form. Fill in your details and we will review your application.
                    </p>
                </div>
            </div>

            <!-- Right Form Column (scrollable) -->
            <div class="member-app-form-side lg:col-span-8"
                 style="background-color: var(--white); padding: 24px; border-radius: 22px; overflow-y: auto; overflow-x: hidden;">

                @if (session('success'))
                    <div style="margin-bottom: 20px; font-weight: 500; font-size: 15px; color: #16a34a; padding: 12px; background: #e8f9eb; border-radius: 10px; border-left: 4px solid #16a34a;">
                        {{ session('success') }}
                    </div>
                @endif

                <x-validation-errors class="mb-4" />

                <form method="POST" action="{{ route('member.applications.store') }}" enctype="multipart/form-data"
                      style="display: flex; flex-direction: column; gap: 24px; margin: 0; padding-bottom: 16px;">
                    @csrf

                    <!-- Page / form header -->
                    <div class="form-header" style="max-width: 60ch; margin-bottom: 8px;">
                        <h1 style="font-family: 'Bebas Neue', sans-serif; font-size: clamp(28px, 5vw, 38px); letter-spacing: 0.5px; line-height: 1; color: var(--text-dark); margin: 0 0 6px 0;">
                            Membership <span style="color: var(--gold);">Application</span>
                        </h1>
                        <p style="color: #5a7a5e; line-height: 1.7; font-size: 15px; margin: 0;">
                            Fill in your details below. Fields marked with <strong style="color: #b91c1c;">*</strong> are required.
                        </p>
                    </div>

                    <!-- ── Basic Information ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Basic Information
                            </legend>

                            <!-- Row 1: First / Given Name * | Middle Name -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="first_name" class="field label">First / Given Name *</label>
                                <input type="text" id="first_name" name="first_name"
                                       value="{{ old('first_name', $firstName) }}"
                                       {{ $isAuth ? 'readonly' : '' }}
                                       class="{{ $isAuth ? 'member-app-input bg-gray-100 cursor-not-allowed' : 'member-app-input' }}"
                                       required autocomplete="given-name" />
                                @isset($errors)@error('first_name')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="middle_name" class="field label">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name"
                                       value="{{ old('middle_name', $middleName) }}"
                                       {{ $isAuth ? 'readonly' : '' }}
                                       class="{{ $isAuth ? 'member-app-input bg-gray-100 cursor-not-allowed' : 'member-app-input' }}"
                                       autocomplete="additional-name" />
                                @isset($errors)@error('middle_name')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 2: Last Name * | Email Address * -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="last_name" class="field label">Last Name *</label>
                                <input type="text" id="last_name" name="last_name"
                                       value="{{ old('last_name', $surname) }}"
                                       {{ $isAuth ? 'readonly' : '' }}
                                       class="{{ $isAuth ? 'member-app-input bg-gray-100 cursor-not-allowed' : 'member-app-input' }}"
                                       required autocomplete="family-name" />
                                @isset($errors)@error('last_name')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="email" class="field label">Email Address *</label>
                                <input type="email" id="email" name="email"
                                       value="{{ old('email', $authEmail) }}"
                                       {{ $isAuth ? 'readonly class="bg-gray-100 cursor-not-allowed" required' : 'required autocomplete="email"' }} />
                                @isset($errors)@error('email')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 3: Mobile Number * -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="contact_number" class="field label">Mobile Number *</label>
                                <input type="tel" id="contact_number" name="contact_number"
                                       value="{{ old('contact_number', $contactNo) }}"
                                       class="member-app-input"
                                       data-validate="phone"
                                       required autocomplete="tel"
                                       maxlength="11"
                                       pattern="[0-9]{11}"
                                       placeholder="09XXXXXXXXX"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" />
                                @isset($errors)@error('contact_number')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Occupation (required, text, max 100) -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="occupation" class="field label">Occupation *</label>
                                <input type="text" id="occupation" name="occupation"
                                       class="member-app-input"
                                       required
                                       maxlength="100"
                                       autocomplete="occupation" />
                                @isset($errors)@error('occupation')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                    </div>

                    <!-- ── Current Address ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Current Address
                            </legend>

                            <!-- Row 1: Province -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="province" class="field label">Province</label>
                                <select id="province" name="province" class="member-app-input">
                                    <option value="National Capital Region (NCR)" selected>National Capital Region (NCR)</option>
                                </select>
                                @isset($errors)@error('province')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 2: City / Municipality | Barangay -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="municipality" class="field label">City / Municipality</label>
                                <select id="municipality" name="municipality" class="member-app-input">
                                    <option value="">Select City / Municipality</option>
                                    <option value="Las Piñas City">Las Piñas City</option>
                                    <option value="Parañaque City">Parañaque City</option>
                                </select>
                                @isset($errors)@error('municipality')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="barangay" class="field label">Barangay</label>
                                <select id="barangay" name="barangay" class="member-app-input">
                                    <option value="">Select Barangay</option>
                                </select>
                                @isset($errors)@error('barangay')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Address Line and Zip Code Row -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-4">
    <!-- Address Line (70% width on desktop) -->
    <div class="field">
        <label for="address_line" class="field label">
            Address Line <span class="text-red-500">*</span>
        </label>
        <input type="text"
               id="address_line"
               name="address_line"
               value="{{ old('address_line') }}"
               class="member-app-input"
               maxlength="255" />
        @isset($errors)@error('address_line')<span class="error-text">{{ $message }}</span>@enderror@endisset
    </div>

    <!-- Zip Code (numeric validation) -->
    <div class="field">
        <label for="zip_code" class="field label">
            Zip Code <span class="text-red-500">*</span>
        </label>
        <input type="text"
               id="zip_code"
               name="zip_code"
               value="{{ old('zip_code') }}"
               maxlength="4"
               pattern="[0-9]{1,4}"
               inputmode="numeric"
               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);" />
        @isset($errors)@error('zip_code')<span class="error-text">{{ $message }}</span>@enderror@endisset
    </div>
</div>

<!-- Birthdate -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <div class="field">
        <label for="date_of_birth" class="field label">Birthdate</label>
        <input type="date"
               id="date_of_birth"
               name="date_of_birth"
               value="{{ old('date_of_birth') }}"
               class="member-app-input"
               max="{{ now()->subYears(18)->format('Y-m-d') }}" />
        @isset($errors)@error('date_of_birth')<span class="error-text">{{ $message }}</span>@enderror@endisset
        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
            Must be at least 18 years old
        </p>
    </div>
</div>

                        </fieldset>
                    </div>

                    <!-- ── Permanent Address ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Permanent Address
                            </legend>

                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #4b5563; margin-top: 4px;">
                                    <input type="checkbox" id="same_as_current_address" name="same_as_current_address" checked style="margin-top: 2px; margin-right: 0;" />
                                    <label for="same_as_current_address" style="cursor: pointer; margin: 0;">Same as current address</label>
                                </div>
                            </div>

                            <!-- Row 1: Permanent Street (full width) -->
                            <div style="margin-bottom: 24px;">
                              <div class="field">
                                <label for="perm_street" class="field label">Street / Address Line</label>
                                <input type="text" id="perm_street" name="perm_street"
                                       value="{{ old('perm_street') }}"
                                       class="member-app-input" style="width: 100%;" />
                                @isset($errors)@error('perm_street')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 2: Permanent Barangay | Permanent Municipality -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="perm_barangay" class="field label">Barangay</label>
                                <select id="perm_barangay" name="perm_barangay" class="member-app-input">
                                    <option value="">Select Barangay</option>
                                </select>
                                @isset($errors)@error('perm_barangay')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="perm_municipality" class="field label">City / Municipality</label>
                                <select id="perm_municipality" name="perm_municipality" class="member-app-input">
                                    <option value="">Select City / Municipality</option>
                                    <option value="Las Piñas City">Las Piñas City</option>
                                    <option value="Parañaque City">Parañaque City</option>
                                </select>
                                @isset($errors)@error('perm_municipality')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 3: Permanent Province | Permanent Zip Code -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="perm_province" class="field label">Province</label>
                                <select id="perm_province" name="perm_province" class="member-app-input">
                                    <option value="National Capital Region (NCR)" selected>National Capital Region (NCR)</option>
                                </select>
                                @isset($errors)@error('perm_province')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="perm_zip_code" class="field label">Zip Code</label>
                                <input type="text"
                                       id="perm_zip_code"
                                       name="perm_zip_code"
                                       value="{{ old('perm_zip_code') }}"
                                       maxlength="4"
                                       pattern="[0-9]{1,4}"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);" />
                                @isset($errors)@error('perm_zip_code')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                        </fieldset>
                    </div>

                    <!-- ── Financial Profile ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Financial Profile
                            </legend>

                            <!-- Monthly Income | Existing Monthly Obligations -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="monthly_income" class="field label">Monthly Income (₱)</label>
                                <input type="number" id="monthly_income" name="monthly_income"
                                       step="0.01" min="0"
                                       value="{{ old('monthly_income') }}"
                                       class="member-app-input"
                                       placeholder="e.g. 15000"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 10);" />
                                @isset($errors)@error('monthly_income')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="existing_monthly_obligations" class="field label">Existing Monthly Obligations (₱)</label>
                                <input type="number" id="existing_monthly_obligations" name="existing_monthly_obligations"
                                       step="0.01" min="0"
                                       value="{{ old('existing_monthly_obligations') }}"
                                       class="member-app-input"
                                       placeholder="e.g. 2000"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 10);" />
                                @isset($errors)@error('existing_monthly_obligations')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="savings_balance" class="field label">Savings Balance (₱)</label>
                                <input type="number" id="savings_balance" name="savings_balance"
                                       step="0.01" min="0"
                                       value="{{ old('savings_balance') }}"
                                       class="member-app-input"
                                       placeholder="Optional. Subject to verification by the cooperative."
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 10);" />
                                @isset($errors)@error('savings_balance')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                        </fieldset>
                    </div>

                    <!-- ── Bottom Action Bar ── -->
                    <div style="display: flex; flex-direction: column; align-items: flex-end; margin-top: 12px; gap: 16px; padding-top: 16px; border-top: 1px solid #e2f0e4;">
                        <div style="display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #4b5563; margin-top: 4px;">
                            <input type="checkbox" id="dataPrivacy" name="data_privacy_consent" required style="margin-top: 2px;" />
                            <label for="dataPrivacy">I consent to the processing of my personal data for membership evaluation and cooperative purposes, in accordance with the Data Privacy Act.</label>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 16px; width: 100%;">
                            <a href="{{ url('/') }}" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-submit">
                                {{ __('Submit Application') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
  // ─── DUPLICATE MEMBER LIVE CHECK ───
  // Blur on name/email/contact calls POST /admin/check-duplicate.
  // Red error for email/contact match; amber warning for name-only match.
  // Blocks submit on error.
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action="/member-application"]');
    if (!form) return;

    const csrfToken = form.querySelector('input[name="_token"]')?.value || '';

    const firstName = document.getElementById('first_name');
    const middleName = document.getElementById('middle_name');
    const lastName = document.getElementById('last_name');
    const email = document.getElementById('email');
    const contact = document.getElementById('contact_number');

    function getFullName() {
      return [firstName, middleName, lastName]
        .map(f => f ? f.value.trim() : '')
        .filter(Boolean)
        .join(' ');
    }

    function clearMsg(fieldEl) {
      const parent = fieldEl.parentElement;
      if (parent) parent.querySelectorAll('.dup-check-msg').forEach(m => m.remove());
      fieldEl.style.borderColor = '';
    }

    function showMessage(fieldEl, msg, type) {
      const parent = fieldEl.parentElement;
      if (!parent) return;
      parent.querySelectorAll('.dup-check-msg').forEach(m => m.remove());
      const div = document.createElement('div');
      div.className = 'dup-check-msg ' + (type === 'error' ? 'error' : 'warning');
      div.style.cssText = 'margin-top:4px; font-size:0.75rem; padding:4px 8px; border-radius:4px;';
      if (type === 'error') {
        div.style.color = '#b91c1c';
        div.style.background = '#fef2f2';
        div.style.border = '1px solid #fca5a5';
        fieldEl.style.borderColor = '#ef4444';
      } else {
        div.style.color = '#92400e';
        div.style.background = '#fffbeb';
        div.style.border = '1px solid #f59e0b';
      }
      div.textContent = msg;
      parent.appendChild(div);
    }

    function checkField(fieldEl) {
      const fullName = getFullName();
      const eml = email ? email.value.trim() : '';
      const cnt = contact ? contact.value.trim() : '';
      if (!fullName && !eml && !cnt) { clearMsg(fieldEl); return; }

      const body = new FormData();
      body.append('full_name', fullName);
      body.append('email', eml);
      body.append('phone', cnt);
      body.append('_token', csrfToken);

      fetch('/admin/check-duplicate', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: body,
      })
        .then(r => r.json())
        .then(result => {
          if (result.is_duplicate) {
            showMessage(fieldEl, result.message, 'error');
          } else if (result.is_warning) {
            showMessage(fieldEl, result.message, 'warning');
          } else {
            clearMsg(fieldEl);
          }
        })
        .catch(() => {});
    }

    const fields = [firstName, middleName, lastName, email, contact].filter(Boolean);
    fields.forEach(el => el.addEventListener('blur', () => checkField(el)));

    // Block submit on any duplicate error, or a non-gmail email
    form.addEventListener('submit', function (e) {
      const eml = email ? email.value.trim().toLowerCase() : '';
      if (eml && !eml.endsWith('@gmail.com')) {
        e.preventDefault();
        showMessage(email, 'Only @gmail.com email addresses are allowed.', 'error');
        email.focus();
        return;
      }
      const errorEls = document.querySelectorAll('.dup-check-msg.error');
      if (errorEls.length > 0) {
        e.preventDefault();
        alert('Please fix the duplicate member errors before submitting.');
      }
    });
  });
</script>

<script>

  // Province is static: only "National Capital Region (NCR)" is available.
  // No JavaScript population is needed for the province select.

  // City -> Barangay mapping (Las Piñas City and Parañaque City only)
  const barangaysByCity = {
    "Las Piñas City": [
      "B. F. International Village", "CAA / B.F. International", "Almanza Uno",
      "Almanza Dos", "Talon Uno", "Talon Dos", "Talon Tres", "Talon Kuatro",
      "Talon Singko", "Pamplona Uno", "Pamplona Dos", "Pamplona Tres",
      "Pulang Lupa Uno", "Pulang Lupa Dos", "Zapote", "Manuyo Uno",
      "Manuyo Dos", "Elias Aldana", "Ilaya", "Daniel Fajardo"
    ],
    "Parañaque City": [
      "Baclaran", "Don Galo", "La Huerta", "San Dionisio", "San Isidro",
      "Sto. Niño", "Tambo", "Vitalez", "B.F. Homes", "Don Bosco",
      "Marcelo Green", "Merville", "Moonwalk", "San Antonio",
      "San Martin de Porres", "Sun Valley"
    ]
  };

  const citySelect = document.getElementById('municipality');
  const barangaySelect = document.getElementById('barangay');

  // Populate barangays based on the selected city (normalized string matching)
  function populateBarangays() {
    const city = citySelect.value.trim();
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

    const barangays = barangaysByCity[city] || [];
    barangays.forEach(barangay => {
      const opt = document.createElement('option');
      opt.value = barangay;
      opt.textContent = barangay;
      barangaySelect.appendChild(opt);
    });
  }

  // Populate barangays on DOMContentLoaded (in case a city is pre-selected)
  document.addEventListener('DOMContentLoaded', populateBarangays);

  // Populate barangays whenever the city selection changes
  citySelect.addEventListener('change', function () {
    populateBarangays();
    syncPermFieldsOnChange();
  });

  // ─── SAME AS CURRENT ADDRESS CHECKBOX ───
  // When checked, copy current address fields to permanent address fields
  // and live-sync whenever current fields change
  const sameAsCurrentCheckbox = document.getElementById('same_as_current_address');
  const permFields = {
    street: { current: 'address_line', permanent: 'perm_street' },
    barangay: { current: 'barangay', permanent: 'perm_barangay' },
    municipality: { current: 'municipality', permanent: 'perm_municipality' },
    province: { current: 'province', permanent: 'perm_province' },
    zip_code: { current: 'zip_code', permanent: 'perm_zip_code' }
  };

  // Province is static (NCR only), so no population needed for perm_province.
  // perm_municipality has static options (Las Piñas City / Parañaque City).
  // perm_barangay is populated dynamically based on perm_municipality selection.

  // Populate perm barangays based on the selected permanent municipality
  function populatePermBarangays() {
    const city = document.getElementById('perm_municipality')?.value.trim();
    const barangaySelect = document.getElementById('perm_barangay');
    if (!barangaySelect) return;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    const barangays = barangaysByCity[city] || [];
    barangays.forEach(barangay => {
      const opt = document.createElement('option');
      opt.value = barangay;
      opt.textContent = barangay;
      barangaySelect.appendChild(opt);
    });
  }

  function copyCurrentToPermanent() {
    if (!sameAsCurrentCheckbox.checked) return;

    // Copy simple string fields first
    [permFields.street, permFields.province, permFields.zip_code].forEach(key => {
      const currentEl = document.getElementById(key.current);
      const permEl = document.getElementById(key.permanent);
      if (currentEl && permEl) {
        permEl.value = currentEl.value;
      }
    });

    // Set permanent municipality first, then populate barangays, then set barangay
    const currentMunicipalityEl = document.getElementById(permFields.municipality.current);
    const permMunicipalityEl = document.getElementById(permFields.municipality.permanent);
    if (currentMunicipalityEl && permMunicipalityEl) {
      permMunicipalityEl.value = currentMunicipalityEl.value;
    }

    // Populate perm barangay options based on the selected municipality, THEN set value
    populatePermBarangays();
    const currentBarangayEl = document.getElementById(permFields.barangay.current);
    const permBarangayEl = document.getElementById(permFields.barangay.permanent);
    if (currentBarangayEl && permBarangayEl) {
      permBarangayEl.value = currentBarangayEl.value;
      permBarangayEl.dispatchEvent(new Event('change'));
    }
  }

  function syncPermFieldsOnChange() {
    if (!sameAsCurrentCheckbox.checked) return;

    // Copy simple string fields whenever current fields change
    [permFields.street, permFields.province, permFields.zip_code].forEach(key => {
      const currentEl = document.getElementById(key.current);
      const permEl = document.getElementById(key.permanent);
      if (currentEl && permEl) {
        permEl.value = currentEl.value;
      }
    });

    // Sync municipality
    const currentMunicipalityEl = document.getElementById(permFields.municipality.current);
    const permMunicipalityEl = document.getElementById(permFields.municipality.permanent);
    if (currentMunicipalityEl && permMunicipalityEl) {
      permMunicipalityEl.value = currentMunicipalityEl.value;
    }

    // Repopulate barangays and sync barangay value
    populatePermBarangays();
    const currentBarangayEl = document.getElementById(permFields.barangay.current);
    const permBarangayEl = document.getElementById(permFields.barangay.permanent);
    if (currentBarangayEl && permBarangayEl) {
      permBarangayEl.value = currentBarangayEl.value;
      permBarangayEl.dispatchEvent(new Event('change'));
    }
  }

  function togglePermanentFields(disabled) {
    Object.values(permFields).forEach(({ permanent }) => {
      const permEl = document.getElementById(permanent);
      if (permEl) {
        permEl.disabled = disabled;
        if (disabled) {
          permEl.style.backgroundColor = '#f3f4f6';
        } else {
          permEl.style.backgroundColor = '';
        }
      }
    });
  }

  // Initial state on load — restore old values and populate selects
  document.addEventListener('DOMContentLoaded', function () {
    if (sameAsCurrentCheckbox) {
      togglePermanentFields(sameAsCurrentCheckbox.checked);
      if (sameAsCurrentCheckbox.checked) {
        copyCurrentToPermanent();
      }
    }
    // Restore old('municipality') and old('barangay') after selects are populated
    restoreOldValues();
  });

  // Live sync: when current address fields change AND checkbox is checked,
  // re-sync permanent fields in real time
  const currentFieldIds = ['province', 'municipality', 'barangay', 'address_line', 'zip_code'];
  currentFieldIds.forEach(fieldId => {
    const el = document.getElementById(fieldId);
    if (el) {
      el.addEventListener('change', syncPermFieldsOnChange);
      el.addEventListener('input', syncPermFieldsOnChange);
    }
  });

  // Toggle permanent field disabled state when checkbox changes
  sameAsCurrentCheckbox.addEventListener('change', function () {
    togglePermanentFields(this.checked);
    if (this.checked) {
      copyCurrentToPermanent();
    }
  });

  // When perm municipality changes, repopulate perm barangays
  const permMunicipalityEl = document.getElementById('perm_municipality');
  if (permMunicipalityEl) {
    permMunicipalityEl.addEventListener('change', function () {
      populatePermBarangays();
      const val = this.value;
      if (val && ['Las Piñas City', 'Parañaque City'].includes(val)) {
        document.getElementById('perm_barangay').value = val;
      }
    });
  }

  // Restore old input values for current/permanent address selects after DOM population
  function restoreOldValues() {
    // Restore current address municipality and barangay
    const municipalitySelect = document.getElementById('municipality');
    const barangaySelect = document.getElementById('barangay');
    if (municipalitySelect && barangaySelect) {
      municipalitySelect.value = '@old(\'municipality\', \'\')';
      populateBarangays();
      barangaySelect.value = '@old(\'barangay\', \'\')';
    }
    // Restore permanent address municipality and barangay
    const permMunicipalitySelect = document.getElementById('perm_municipality');
    const permBarangaySelect = document.getElementById('perm_barangay');
    if (permMunicipalitySelect && permBarangaySelect) {
      permMunicipalitySelect.value = '@old(\'perm_municipality\', \'\')';
      populatePermBarangays();
      permBarangaySelect.value = '@old(\'perm_barangay\', \'\')';
    }
  }

  // ─── DATE OF BIRTH VALIDATION ───
  // Validate that DOB is a past date and applicant is at least 18 years old
  const dobInput = document.getElementById('date_of_birth');
  if (dobInput) {
    function validateDOB(input) {
      const value = input.value.trim();
      if (!value) {
        clearDOBError(input);
        return;
      }

      // Parse as LOCAL midnight to avoid UTC offset bugs (new Date('YYYY-MM-DD')
      // is treated as UTC by the spec, which shifts the comparison by one day
      // in timezones behind UTC).
      const dob = new Date(value + 'T00:00:00');
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      if (isNaN(dob.getTime())) {
        showDOBError(input, 'Birthdate must be a valid date');
        return;
      }

      if (dob > today) {
        showDOBError(input, 'Birthdate cannot be in the future');
        return;
      }

      let exactAge = today.getFullYear() - dob.getFullYear();
      const monthDiff = today.getMonth() - dob.getMonth();
      const hasBirthdayPassed = monthDiff > 0 || (monthDiff === 0 && today.getDate() >= dob.getDate());
      if (!hasBirthdayPassed) {
        exactAge -= 1;
      }

      if (exactAge < 18) {
        showDOBError(input, 'Applicant must be at least 18 years old');
        return;
      }

      clearDOBError(input);
    }

    dobInput.addEventListener('change', function () { validateDOB(this); });
    dobInput.addEventListener('input', function () { validateDOB(this); });
  }

  function showDOBError(input, message) {
    const parent = input.parentElement;
    if (!parent) return;
    parent.querySelectorAll('.dob-error-msg').forEach(m => m.remove());
    const div = document.createElement('div');
    div.className = 'dob-error-msg error';
    div.style.cssText = 'margin-top:4px; font-size:0.75rem; padding:4px 8px; border-radius:4px; color: #b91c1c; background: #fef2f2; border: 1px solid #fca5a5;';
    div.textContent = message;
    parent.appendChild(div);
    input.style.borderColor = '#ef4444';
  }

  function clearDOBError(input) {
    const parent = input.parentElement;
    if (!parent) return;
    parent.querySelectorAll('.dob-error-msg').forEach(m => m.remove());
    input.style.borderColor = '';
  }
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700&display=swap');

    :root {
      --off-white: #f4faf5;
      --text-dark: #1a2a1a;
      --text-mid: #4b5563;
      --text-muted: #6b7280;
      --green-deep: #1a5c2a;
      --green-mid: #2e8b3e;
      --green-pale: #b8efc2;
      --green-wash: #e8f9eb;
      --dull: #e2e8f0;
      --gold: #d4af37;
      --white: #ffffff;
    }

    .error-text { display: block; margin-top: 6px; font-size: 13px; color: #a32b2b; }

    /* Unified form control styling */
    .field label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #374151;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 6px;
    }
    .field input, .field select, .field textarea, .member-app-input {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      background: var(--white);
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--text-dark);
      transition: border-color 0.15s, box-shadow 0.15s;
      box-sizing: border-box;
    }
    .field input:focus, .field select:focus, .field textarea:focus, .member-app-input:focus {
      outline: none;
      border-color: var(--green-mid);
      background: var(--white);
      box-shadow: 0 0 0 4px rgba(46, 139, 62, 0.18);
    }

    /* Desktop card: white bg, emerald border, subtle shadow */
    .form-card {
      background: var(--white);
      border: 1px solid var(--green-pale);
      border-radius: 16px;
      padding: 30px 28px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    /* Form header */
    .form-header { margin-bottom: 16px; }
    .form-header h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(24px, 4vw, 32px);
      letter-spacing: 0.5px;
      line-height: 1.2;
      color: var(--text-dark);
      margin: 0 0 6px 0;
    }
    .form-header p { color: #5a7a5e; line-height: 1.7; font-size: 14px; margin: 0; }

    /* Cancel button */
    .btn-cancel {
      text-decoration: none;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      color: var(--text-mid);
      padding: 12px 24px;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .btn-cancel:hover { background: var(--green-wash); color: var(--green-deep); border-color: var(--green-mid); }

    /* Submit button - primary */
    .btn-submit {
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background-color: var(--green-deep);
      color: var(--white);
      padding: 12px 28px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s, box-shadow 0.2s;
      box-shadow: 0 2px 6px rgba(26, 92, 42, 0.25);
    }
    .btn-submit:hover { background-color: var(--green-mid); box-shadow: 0 4px 12px rgba(26, 92, 42, 0.35); }

    /* File upload drop-zone */
    .member-file-field label { display: block; }
    .sr-file-input {
      position: absolute;
      width: 1px; height: 1px;
      padding: 0; margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }
    .member-file-drop {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 26px 20px;
      border: 2px dashed #c3d8c6;
      border-radius: 14px;
      background: var(--green-wash);
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      text-align: center;
    }
    .member-file-drop:hover {
      border-color: var(--green-mid);
      background: var(--green-wash);
    }
    .member-file-drop .mf-icon {
      font-size: 26px;
      color: var(--green-mid);
    }
    .member-file-drop .mf-text {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-dark);
    }
    .member-file-drop .mh-hint, .member-file-drop .mf-hint {
      font-size: 12px;
      color: var(--text-muted);
    }

    /* Visually hidden labels */
    .sr-only {
      position: absolute;
      width: 1px; height: 1px;
      padding: 0; margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    /* Character references table */
    .ref-table {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .ref-row {
      display: grid;
      grid-template-columns: 36px 1fr 1.4fr 1.2fr;
      gap: 1px;
      background: #e2f0e4;
      border: 1px solid #e2f0e4;
      border-radius: 14px;
      overflow: hidden;
    }
    .ref-head {
      background: var(--green-wash);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.4px;
      text-transform: uppercase;
      color: var(--green-deep);
      display: grid;
      grid-template-columns: 36px 1fr 1.4fr 1.2fr;
      align-items: center;
      padding: 10px 12px;
    }
    .ref-no, .ref-col {
      background: var(--white);
      display: flex;
      align-items: center;
    }
    .ref-no {
      justify-content: center;
      font-weight: 700;
      font-size: 13px;
      color: var(--text-mid);
      padding: 10px 4px;
    }
    .ref-col {
      padding: 6px 10px;
    }

    /* Mobile / tablet: stacked layout */
    @media (max-width: 1023px) {
      .member-app-frame { flex-direction: column; }
      .member-app-banner { width: 100%; margin-bottom: 16px; }
      .member-app-form-side { width: 100%; border-radius: 16px; }
      .member-app-form-side .form-header { max-width: 100%; }
    }

    @media (max-width: 768px) {
      .member-app-banner { padding: 24px 18px; }
      .member-app-banner [style*="width: 120px"] { width: 88px !important; height: 88px !important; }
      .member-app-banner h1 { font-size: 20px !important; }
      .member-app-form-side { padding: 22px 18px; }
      .form-card { padding: 22px 18px; }
      /* stack every two-column row of fields */
  .member-app-form-side [style*="grid-template-columns: 1fr 1fr"] {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }
  /* let grid children shrink instead of stretching the page */
  .member-app-form-side [style*="display: grid"] > * {
    min-width: 0;
  }
  .member-app-form-side input,
  .member-app-form-side select,
  .member-app-form-side textarea {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  /* character references: one card per person */
  .ref-head { display: none; }
  .ref-row {
    display: flex !important;
    flex-direction: column;
    gap: 8px;
    padding: 8px;
  }
  .ref-no { justify-content: flex-start; padding: 4px 8px; }
  /* make sure nothing forces horizontal scroll */
  .member-app-page, .member-app-frame { max-width: 100vw; overflow-x: hidden; }
  html, body { margin: 0; max-width: 100%; overflow-x: hidden; }

  /* let every wrapper shrink to the phone width */
  .member-app-page,
  .member-app-frame,
  .member-app-form-side,
  .member-app-form-side form,
  .member-app-form-side fieldset,
  .member-app-form-side .form-card,
  .member-app-form-side .field {
    min-width: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box;
  }
  .member-app-form-side fieldset { width: 100%; }
  .member-app-form-side select { text-overflow: ellipsis; }

    .member-app-banner > div {
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
    padding: 24px 16px !important;
  }
  
    html, body {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    position: relative;
  }
  .member-app-page {
    position: relative;      /* makes absolute children belong to this box */
    overflow: hidden;
    contain: paint;          /* clips anything that tries to stick out */
    max-width: 100vw;
  }
    }

    @media (max-width: 480px) {
      .member-app-banner { padding: 18px 14px; }
      .member-app-banner [style*="width: 120px"] { width: 80px !important; height: 80px !important; }
      .member-app-form-side { padding: 18px 14px; }
      .form-card { padding: 20px 16px; }
      .grid-2, .ref-row { grid-template-columns: 1fr !important; display: flex !important; flex-direction: column !important; gap: 10px; }
      .ref-head { display: none; }
      .field { margin-bottom: 16px; }
    }

</html>