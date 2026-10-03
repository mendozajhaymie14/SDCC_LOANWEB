<x-guest-layout>
    <div class="member-app-page" style="min-height: 100vh; display: flex; flex-direction: column; align-items: center; background-color: var(--off-white); padding: 2rem; font-family: 'DM Sans', sans-serif;">

        <div style="width: 100%; max-width: 1100px; margin: 0 auto;">

            <!-- Go Back Button -->
            <div style="width: 100%; display: flex; justify-content: flex-start; margin-bottom: 24px;">
                <a href="/" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-dark); font-weight: 600; font-size: 16px; text-decoration: none;">
                    <svg style="width: 20px; height: 20px; stroke: var(--text-dark);" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Go Back</span>
                </a>
            </div>

            <div class="member-app-frame" style="width: 100%; border: 2px solid var(--green-deep); border-radius: 22px; padding: 0; overflow: hidden; background-color: var(--white); display: grid; grid-template-columns: 1fr 1.6fr; box-sizing: border-box; align-items: stretch; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">

                <!-- Left Banner -->
                <div class="member-app-banner" style="position: relative; width: 100%; min-height: 300px; border-top-left-radius: 22px; border-bottom-left-radius: 22px; border-top-right-radius: 12px; border-bottom-right-radius: 12px; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-position: center; padding: 32px; text-align: center;">

                    <div style="position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; gap: 12px; width: 100%;;">
                        <div style="width: 110px; height: 110px; border-radius: 50%; background-color: var(--white); display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                            <img src="{{ asset('images/Logo.jpg') }}"
                                 alt="San Dionisio Credit Cooperative Logo"
                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                        <h1 style="font-family: 'Bebas Neue', sans-serif; font-size: clamp(38px, 5vw, 54px); letter-spacing: 0.5px; line-height: 1; color: #ffffff; margin: 0; text-transform: uppercase; text-shadow: 0 1px 8px rgba(0,0,0,0.35);">
                            San Dionisio<br>Credit<br>Cooperative
                        </h1>
                        <p style="font-size: 15px; color: rgba(255,255,255,0.9); max-width: 260px; line-height: 1.6; margin: 0; text-shadow: 0 1px 4px rgba(0,0,0,0.25);">
                            Membership application form. Fill in your details and we will review your application.
                        </p>
                    </div>
                </div>

                <!-- Right Form Side -->
                <div class="member-app-form-side" style="padding: 40px; background-color: var(--white); border-top-right-radius: 22px; border-bottom-right-radius: 22px; box-sizing: border-box;">
                    <div style="width: 100%; box-sizing: border-box;">

                        @if (session('success'))
                            <div style="margin-bottom: 20px; font-weight: 500; font-size: 15px; color: #16a34a;">
                                {{ session('success') }}
                            </div>
                        @endif

                        <x-validation-errors class="mb-4" />

                        <form method="POST" action="{{ route('member.applications.store') }}" style="display: flex; flex-direction: column; gap: 24px; margin: 0;">
                            @csrf

                            <!-- ── Personal Information ── -->
                            <div class="form-header">
                                <h1>Membership <span style="color: var(--gold);">Application</span></h1>
                                <p>Fill in your details below. Fields marked with * are required.</p>
                            </div>

                            <div class="form-card">
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 22px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin-bottom: 10px; border: none; padding: 0;">Personal Information</legend>

                                    <div class="grid-2">
                                      <div class="field">
                                        <label for="surname">Surname *</label>
                                        <input type="text" id="surname" name="surname"
                                               value="{{ old('surname') }}"
                                               class="member-app-input"
                                               required autocomplete="family-name" />
                                        @error('surname')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>

                                      <div class="field">
                                        <label for="first_name">First Name *</label>
                                        <input type="text" id="first_name" name="first_name"
                                               value="{{ old('first_name') }}"
                                               class="member-app-input"
                                               required autocomplete="given-name" />
                                        @error('first_name')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>

                                      <div class="field">
                                        <label for="middle_name">Middle Name</label>
                                        <input type="text" id="middle_name" name="middle_name"
                                               value="{{ old('middle_name') }}"
                                               class="member-app-input"
                                               autocomplete="additional-name" />
                                        @error('middle_name')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>
                                    </div>
                                </fieldset>
                            </div>

                            <!-- ── Present Address ── -->
                            <div class="form-card">
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 22px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin-bottom: 10px; border: none; padding: 0;">Kasalukuyang Tirahan <span style="font-weight: 500; color: #7a927e; text-transform: none; letter-spacing: 0;">(Present Address)</span></legend>

                                    <div class="grid-2">
                                      <div class="field">
                                        <label for="house_no">House No. </label>
                                        <input type="text" id="house_no" name="house_no"
                                               value="{{ old('house_no') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="street">Street</label>
                                        <input type="text" id="street" name="street"
                                               value="{{ old('street') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="barangay">Barangay</label>
                                        <input type="text" id="barangay" name="barangay"
                                               value="{{ old('barangay') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="municipality">Municipality</label>
                                        <input type="text" id="municipality" name="municipality"
                                               value="{{ old('municipality') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="zip_code">Zip Code</label>
                                        <input type="text" id="zip_code" name="zip_code"
                                               value="{{ old('zip_code') }}"
                                               class="member-app-input" />
                                      </div>
                                    </div>
                                </fieldset>
                            </div>

                            <!-- ── Permanent Address ── -->
                            <div class="form-card">
                                <fieldset style="border: none; padding: 0; margin: 40px 0;">
                                    <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 22px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin-bottom: 10px; border: none; padding: 0;">Permanenteng Tirahan <span style="font-weight: 500; color: #7a927e; text-transform: none; letter-spacing: 0;">(Permanent Address)</span></legend>

                                    <div class="grid-2">
                                      <div class="field">
                                        <label for="perm_house_no">House No. </label>
                                        <input type="text" id="perm_house_no" name="perm_house_no"
                                               value="{{ old('perm_house_no') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="perm_street">Street</label>
                                        <input type="text" id="perm_street" name="perm_street"
                                               value="{{ old('perm_street') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="perm_barangay">Barangay</label>
                                        <input type="text" id="perm_barangay" name="perm_barangay"
                                               value="{{ old('perm_barangay') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="perm_municipality">Municipality</label>
                                        <input type="text" id="perm_municipality" name="perm_municipality"
                                               value="{{ old('perm_municipality') }}"
                                               class="member-app-input" />
                                      </div>

                                      <div class="field">
                                        <label for="perm_zip_code">Zip Code</label>
                                        <input type="text" id="perm_zip_code" name="perm_zip_code"
                                               value="{{ old('perm_zip_code') }}"
                                               class="member-app-input" />
                                      </div>
                                    </div>
                                </fieldset>
                            </div>

                            <!-- ── Residency & Contact ── -->
                            <div class="form-card">
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 22px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin-bottom: 10px; border: none; padding: 0;">Residency & Contact</legend>

                                    <div style="font-size: 14px; color: var(--text-mid); margin-bottom: 12px;">
                                      Type of Residency
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                      @foreach (['owned' => 'Owned', 'rented' => 'Rented', 'mortgage' => 'Mortgage', 'living_with_relatives' => 'Living with parents/relatives'] as $val => $label)
                                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: var(--text-mid); cursor: pointer;">
                                          <input type="radio" name="residency_type" value="{{ $val }}" style="color: var(--green-deep);" /> <span>{{ $label }}</span>
                                        </label>
                                      @endforeach
                                    </div>
                                    @error('residency_type')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
                                      <div>
                                        <label for="contact_number" class="field label">Contact No. </label>
                                        <input type="text" id="contact_number" name="contact_number"
                                               value="{{ old('contact_number') }}"
                                               class="member-app-input"
                                               required />
                                        @error('contact_number')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>

                                      <div>
                                        <label for="email" class="field label">Email Address </label>
                                        <input type="email" id="email" name="email"
                                               value="{{ old('email') }}"
                                               class="member-app-input"
                                               required autocomplete="email" />
                                        @error('email')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>
                                    </div>
                                </fieldset>
                            </div>

                            <!-- ── Birth & Identity ── -->
                            <div class="form-card">
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 22px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin-bottom: 10px; border: none; padding: 0;">Birth & Identity</legend>

                                    <div class="grid-2">
                                      <div class="field">
                                        <label for="birthdate">Birthdate *</label>
                                        <input type="date" id="birthdate" name="birthdate"
                                               value="{{ old('birthdate') }}"
                                               class="member-app-input"
                                               required autocomplete="bday" />
                                        @error('birthdate')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>

                                      <div class="field">
                                        <label for="nationality">Nationality *</label>
                                        <select id="nationality" name="nationality" class="member-app-input" required>
                                          <option value="">Select nationality…</option>
                                          <option value="Filipino" @selected(old('nationality') === 'Filipino')>Filipino</option>
                                          <option value="Filipino (dual)" @selected(old('nationality') === 'Filipino (dual)')>Filipino (dual citizen)</option>
                                          <option value="Naturalized Filipino" @selected(old('nationality') === 'Naturalized Filipino')>Naturalized Filipino</option>
                                          <option value="Chinese" @selected(old('nationality') === 'Chinese')>Chinese</option>
                                          <option value="American" @selected(old('nationality') === 'American')>American</option>
                                          <option value="Korean" @selected(old('nationality') === 'Korean')>Korean</option>
                                          <option value="Japanese" @selected(old('nationality') === 'Japanese')>Japanese</option>
                                          <option value="Indian" @selected(old('nationality') === 'Indian')>Indian</option>
                                          <option value="Other" @selected(old('nationality') === 'Other')>Other</option>
                                        </select>
                                        @error('nationality')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                      </div>
                                    </div>

                                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 24px;">
                                      <label for="place_of_birth" class="field label">Lugar ng Kapanganakan (Place of Birth) *</label>
                                      <input type="text" id="place_of_birth" name="place_of_birth"
                                             value="{{ old('place_of_birth') }}"
                                             class="member-app-input"
                                             required />
                                      @error('place_of_birth')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                    </div>
                                </fieldset>
                            </div>

                            <!-- ── Gender, Occupation, Civil Status ── -->
                            <div class="form-card">
                                <fieldset style="border: none; padding: 0; margin: 0;">
                                    <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 22px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin-bottom: 10px; border: none; padding: 0;">Demographics</legend>

                                    <div style="font-size: 14px; color: var(--text-mid); margin-bottom: 12px;">
                                      Kasarian (Gender)
                                    </div>
                                    <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                      @foreach (['male' => 'Male', 'female' => 'Female', 'lgbtqia+' => 'LGBTQIA+'] as $val => $label)
                                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: var(--text-mid); cursor: pointer;">
                                          <input type="radio" name="gender" value="{{ $val }}" style="color: var(--green-deep);" /> <span>{{ $label }}</span>
                                        </label>
                                      @endforeach
                                    </div>
                                    @error('gender')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror

                                    <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 24px;">
                                      <label for="occupation" class="field label">Hanap-Buhay (Occupation) *</label>
                                      <input type="text" id="occupation" name="occupation"
                                             value="{{ old('occupation') }}"
                                             class="member-app-input"
                                             required />
                                      @error('occupation')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                    </div>

                                    <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 24px;">
                                      <span style="font-size: 14px; font-weight: 600; color: var(--text-mid);">Civil Status</span>
                                      <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                        @foreach (['single' => 'Single', 'married' => 'Married', 'legally_separated' => 'Legally Separated', 'annulled' => 'Annulled', 'widowed' => 'Widowed'] as $val => $label)
                                          <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: var(--text-mid); cursor: pointer;">
                                            <input type="radio" name="civil_status" value="{{ $val }}" style="color: var(--green-deep);" /> <span>{{ $label }}</span>
                                          </label>
                                        @endforeach
                                      </div>
                                      @error('civil_status')<span class="error-text" style="font-size: 12px; color: #a32b2b; display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                                    </div>
                                </fieldset>
                            </div>

                            <!-- ── Submit ── -->
                            <div style="display: flex; align-items: center; justify-content: flex-end; margin-top: 24px; gap: 16px;">
                                <a href="{{ url('/') }}" class="btn-cancel">Cancel</a>
                                <button type="submit" style="font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background-color: var(--green-deep); color: var(--white); padding: 12px 28px; border-radius: 10px; border: none; cursor: pointer; transition: background 0.2s;">
                                    {{ __('Submit Application') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

        </div>

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
        }

        .error-text { display: block; margin-top: 6px; font-size: 13px; color: #a32b2b; }

        /* Form field base styles */
        .field input, .field select, .field textarea {
          width: 100%;
          padding: 13px 16px;
          border: 1px solid #d8e8db;
          border-radius: 12px;
          background: var(--off-white);
          font-family: 'DM Sans', sans-serif;
          font-size: 15px;
          color: var(--text-dark);
          transition: border-color 0.2s, background 0.2s;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
          outline: 2px solid var(--green-mid);
          outline-offset: 1px;
          border-color: var(--green-mid);
          background: var(--white);
        }
        .field input.has-error, .field select.has-error, .field textarea.has-error { border-color: #d98a8a; background: #fdf7f7; }

        /* Grid-2 layout */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .field.full { grid-column: 1 / -1; }

        /* Form header */
        .form-header { margin-bottom: 24px; max-width: 60ch; }
        .form-header h1 {
          font-family: 'Bebas Neue', sans-serif;
          font-size: clamp(38px, 5vw, 54px);
          letter-spacing: 0.5px;
          line-height: 1;
          color: var(--text-dark);
          margin-bottom: 14px;
        }
        .form-header p { color: #5a7a5e; line-height: 1.7; font-size: 16px; }

        /* Form card */
        .form-card {
          background: var(--white);
          border: 1px solid #e2f0e4;
          border-radius: 22px;
          padding: 40px;
        }

        /* Fieldset legend */
        .fieldset { border: none; }
        .fieldset + .fieldset { margin-top: 40px; padding-top: 36px; border-top: 1px solid #edf5ee; }
        .fieldset legend {
          font-family: 'Bebas Neue', sans-serif;
          font-size: 22px;
          letter-spacing: 1.2px;
          color: var(--green-deep);
          margin-bottom: 10px;
        }

        /* Form actions */
        .form-actions { margin-top: 36px; display: flex; gap: 16px; align-items: center; }
        .btn-cancel {
          text-decoration: none;
          font-family: 'DM Sans', sans-serif;
          font-size: 14px;
          font-weight: 600;
          color: var(--text-mid);
          padding: 12px 24px;
          border: 1px solid #d8e8db;
          border-radius: 10px;
          transition: all 0.2s;
          white-space: nowrap;
        }
        .btn-cancel:hover { background: var(--green-wash); color: var(--green-deep); border-color: var(--green-mid); }

        /* Submit button */
        button[type="submit"] {
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
          transition: background 0.2s;
        }
        button[type="submit"]:hover { background-color: var(--green-mid); }

        /* Number input spinner removal */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
          -webkit-appearance: none;
          margin: 0;
        }
        input[type="number"] {
          -moz-appearance: textfield;
        }

        /* Radio buttons */
        .checkbox-field { display: flex; gap: 12px; align-items: flex-start; font-size: 14px; color: var(--text-mid); line-height: 1.6; }
        .checkbox-field input { width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0; }

        /* Media queries for responsive */
        @media (max-width: 991px) {
          .member-app-frame { border-radius: 28px; display: block !important; }
          .member-app-banner { min-height: 200px !important; border-radius: 24px 24px 0 0; }
          .member-app-form-side { border-radius: 0 0 24px 24px; }
        }

        @media (max-width: 768px) {
          .member-app-page { padding: 0.75rem; }
          .member-app-frame { border-radius: 20px; }
          .member-app-banner { min-height: 160px !important; padding: 20px; }
          .member-app-banner [style*="width: 110px"] { width: 90px !important; height: 90px !important; }
          .member-app-banner h1 { font-size: 18px !important; }
          .member-app-form-side { padding: 28px 20px; }
          .grid-2 { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
          .member-app-page { padding: 0.5rem; }
          .member-app-banner { min-height: 130px !important; padding: 14px; }
          .member-app-form-side { padding: 24px 16px; }
          .member-app-form-side [style*="repeat(3, 1fr)"] { grid-template-columns: 1fr !important; }
          .member-app-form-side [style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; }
        }
    </style>
</x-guest-layout>