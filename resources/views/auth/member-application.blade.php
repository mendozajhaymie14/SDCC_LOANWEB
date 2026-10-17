@php
    $isAuth = auth()->check();
    $authEmail = $isAuth ? auth()->user()->email : '';
    $user = $isAuth ? auth()->user() : null;
    $firstName = $isAuth ? ($user->first_name ?? $user->name ?? '') : '';
    $surname = $isAuth ? ($user->last_name ?? '') : '';
    $contactNo = $isAuth ? ($user->contact_number ?? $user->phone ?? '') : '';
    $middleName = $isAuth ? ($user->middle_name ?? '') : '';
    $sex = $isAuth ? ($user->sex ?? '') : '';
    $civilStatus = $isAuth ? ($user->civil_status ?? '') : '';
    $dob = $isAuth ? ($user->birthdate ?? '') : '';
    $tin = $isAuth ? ($user->tin ?? '') : '';
    $email = $isAuth ? auth()->user()->email : '';
    $phone = $isAuth ? ($user->phone ?? $user->contact_number ?? '') : '';
    $suffix = $isAuth ? ($user->suffix ?? '') : '';
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

                            <!-- Row 2: Last Name * | Suffix (Optional) -->
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
                                <label for="suffix" class="field label">Suffix (Optional)</label>
                                <select id="suffix" name="suffix" class="member-app-input">
                                    <option value="">None</option>
                                    <option value="Jr." {{ old('suffix') == 'Jr.' ? 'selected' : '' }}>Jr.</option>
                                    <option value="Sr." {{ old('suffix') == 'Sr.' ? 'selected' : '' }}>Sr.</option>
                                    <option value="I" {{ old('suffix') == 'I' ? 'selected' : '' }}>I</option>
                                    <option value="II" {{ old('suffix') == 'II' ? 'selected' : '' }}>II</option>
                                    <option value="III" {{ old('suffix') == 'III' ? 'selected' : '' }}>III</option>
                                    <option value="IV" {{ old('suffix') == 'IV' ? 'selected' : '' }}>IV</option>
                                    <option value="V" {{ old('suffix') == 'V' ? 'selected' : '' }}>V</option>
                                </select>
                                @isset($errors)@error('suffix')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 3: Email Address * | Mobile Number * -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="email" class="field label">Email Address *</label>
                                <input type="email" id="email" name="email"
                                       value="{{ old('email', $authEmail) }}"
                                       {{ $isAuth ? 'readonly class="bg-gray-100 cursor-not-allowed"' : 'autocomplete="email"' }} />
                                @isset($errors)@error('email')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="contact_number" class="field label">Mobile Number *</label>
                                <input type="tel" id="contact_number" name="contact_number"
                                       value="{{ old('contact_number', $contactNo) }}"
                                       class="member-app-input"
                                       required autocomplete="tel"
                                       maxlength="11"
                                       pattern="[0-9]{11}"
                                       placeholder="09XXXXXXXXX"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" />
                                @isset($errors)@error('contact_number')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 4: Sex * | Civil Status * -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="sex" class="field label">Sex *</label>
                                <select id="sex" name="sex" class="member-app-input" required>
                                  <option value="">-- SELECT --</option>
                                    <option value="male" {{ old('sex') == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('sex') == 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="lgbtqia+" {{ old('sex') == 'lgbtqia+' ? 'selected' : '' }}>LGBTQIA+</option>
                                </select>
                                @isset($errors)@error('sex')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <label for="civil_status" class="field label">Civil Status *</label>
                                <select id="civil_status" name="civil_status" class="member-app-input" required>
                                  <option value="">-- SELECT --</option>
                                    <option value="single" {{ old('civil_status') == 'single' ? 'selected' : '' }}>Single</option>
                                    <option value="married" {{ old('civil_status') == 'married' ? 'selected' : '' }}>Married</option>
                                    <option value="legally_separated" {{ old('civil_status') == 'legally_separated' ? 'selected' : '' }}>Legally Separated</option>
                                    <option value="annulled" {{ old('civil_status') == 'annulled' ? 'selected' : '' }}>Annulled</option>
                                    <option value="widowed" {{ old('civil_status') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                </select>
                                @isset($errors)@error('civil_status')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>

                            <!-- Row 6: Date of Birth * -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="birthdate" class="field label">Date of Birth *</label>
                                <input type="date" id="birthdate" name="birthdate"
                                       value="{{ old('birthdate', $dob) }}"
                                       class="member-app-input"
                                       required autocomplete="bday" />
                                @isset($errors)@error('birthdate')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field">
                                <div style="height: 46px;"></div>
                              </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- ── Current Address ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Current Address
                            </legend>

                            <!-- Row 1: Country | Province -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                              <div class="field">
                                <label for="country" class="field label">Country</label>
                                <select id="country" name="country" class="member-app-input">
                                    <option value="Philippines" selected>Philippines</option>
                                </select>
                                @isset($errors)@error('country')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

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
               placeholder="e.g. 70 Sampaguita St"
               class="member-app-input"
               maxlength="255" />
        <span class="text-xs text-gray-500 mt-1 block">Unit No./Floor, Building Name, Building/House No., Street, Subdivision/Village</span>
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
               placeholder="e.g. 1740"
               maxlength="4"
               pattern="[0-9]{1,4}"
               inputmode="numeric"
               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);" />
        @isset($errors)@error('zip_code')<span class="error-text">{{ $message }}</span>@enderror@endisset
    </div>
</div>

                        </fieldset>
                    </div>

                    <!-- ── Supporting Documents & TIN ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Supporting Documents
                            </legend>

                            <div style="font-size: 13px; color: var(--text-mid); line-height: 1.6; margin-bottom: 20px;">
                                Please attach a 1&times;1 ID picture and a proof of billing. All uploads accept JPG, PNG or PDF (max 5 MB).
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

                              <div class="field member-file-field">
                                <label for="tin" class="field label">Tax Identification Number (TIN)</label>
                                <input type="text" id="tin" name="tin"
                                       value="{{ old('tin', $tin) }}"
                                       class="member-app-input"
                                       placeholder=""
                                       maxlength="12"
                                       pattern="[0-9]{12}"
                                       inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);" />
                                @isset($errors)@error('tin')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field member-file-field full">
                                <label for="id_picture">1&times;1 ID Picture</label>
                                <label for="id_picture" class="member-file-drop">
                                  <input type="file" id="id_picture" name="id_picture"
                                         accept=".jpg,.jpeg,.png,.pdf"
                                         class="sr-file-input" />
                                  <div class="drop-content">
                                    <span class="mf-icon"><i class="fa-solid fa-id-card"></i></span>
                                    <span class="mf-text">Tap to upload your ID picture</span>
                                    <span class="mh-hint">JPG, PNG or PDF &middot; up to 5 MB</span>
                                  </div>
                                </label>
                                @isset($errors)@error('id_picture')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>

                              <div class="field member-file-field full">
                                <label for="proof_of_billing">Proof of Billing</label>
                                <label for="proof_of_billing" class="member-file-drop">
                                  <input type="file" id="proof_of_billing" name="proof_of_billing"
                                         accept=".jpg,.jpeg,.png,.pdf"
                                         class="sr-file-input" />
                                  <span class="mf-icon"><i class="fa-solid fa-receipt"></i></span>
                                  <span class="mf-text">Tap to upload your proof of billing</span>
                                  <span class="mh-hint">JPG, PNG or PDF &middot; up to 5 MB</span>
                                  </div>
                                </label>
                                @isset($errors)@error('proof_of_billing')<span class="error-text">{{ $message }}</span>@enderror@endisset
                              </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- ── Character References ── -->
                    <div class="form-card">
                        <fieldset style="border: none; padding: 0; margin: 0;">
                            <legend style="font-family: 'Bebas Neue', sans-serif; font-size: 18px; letter-spacing: 1.2px; color: var(--green-deep); text-transform: uppercase; margin: 0 0 18px 0; border: none; padding: 0;">
                                Character References
                            </legend>

                            <div style="font-size: 13px; color: var(--text-mid); line-height: 1.6; margin-bottom: 20px;">
                                Please provide up to three (3) character references. All details are optional but will help us verify your application.
                            </div>

                            <div class="ref-table" role="table" aria-label="Character references">
                              <div class="ref-head" role="row">
                                <span class="ref-no" role="columnheader">No.</span>
                                <span class="ref-col" role="columnheader">Full Name</span>
                                <span class="ref-col" role="columnheader">Address</span>
                                <span class="ref-col" role="columnheader">Contact No.</span>
                              </div>

                              @for ($i = 0; $i < 3; $i++)
                                <div class="ref-row" role="row">
                                  <span class="ref-no">{{ $i + 1 }}.</span>

                                  <span class="ref-col" role="cell">
                                    <label for="ref_full_name_{{ $i }}" class="sr-only">Full name of reference {{ $i + 1 }}</label>
                                    <input type="text" id="ref_full_name_{{ $i }}" name="character_references[{{ $i }}][full_name]"
                                           value="{{ old('character_references.' . $i . '.full_name') }}"
                                           class="member-app-input" placeholder="Full name" autocomplete="off" />
                                  </span>

                                  <span class="ref-col" role="cell">
                                    <label for="ref_address_{{ $i }}" class="sr-only">Address of reference {{ $i + 1 }}</label>
                                    <input type="text" id="ref_address_{{ $i }}" name="character_references[{{ $i }}][address]"
                                           value="{{ old('character_references.' . $i . '.address') }}"
                                           class="member-app-input" placeholder="Address" autocomplete="street-address" />
                                  </span>

                                  <span class="ref-col" role="cell">
                                    <label for="ref_contact_{{ $i }}" class="sr-only">Contact number of reference {{ $i + 1 }}</label>
                                    <input type="tel" id="ref_contact_{{ $i }}" name="character_references[{{ $i }}][contact_number]"
                                           value="{{ old('character_references.' . $i . '.contact_number') }}"
                                           class="member-app-input"
                                           placeholder="09XXXXXXXXX"
                                           maxlength="11"
                                           pattern="[0-9]{11}"
                                           inputmode="numeric"
                                           autocomplete="off"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" />
                                  </span>
                                </div>
                              @endfor
                            </div>

                            @isset($errors)@error('character_references')<span class="error-text">{{ $message }}</span>@enderror@endisset
                        </fieldset>
                    </div>

                    <!-- ── Bottom Action Bar ── -->
                    <div style="display: flex; align-items: center; justify-content: flex-end; margin-top: 12px; gap: 16px; padding-top: 16px; border-top: 1px solid #e2f0e4;">
                        <a href="{{ url('/') }}" class="btn-cancel">Cancel</a>
                        <button type="submit" class="btn-submit">
                            {{ __('Submit Application') }}
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
  // Enhanced file upload preview with constrained thumbnails and file details
  ['id_picture', 'proof_of_billing'].forEach(function (inputName) {
    var input = document.getElementById(inputName);
    if (!input) return;

    var drop = input.closest('label') || input.parentElement;
    if (!drop) return;

    // Create containers
    var dropContent = drop.querySelector('.drop-content');
    var previewContainer = document.createElement('div');
    previewContainer.className = 'file-preview-container';
    previewContainer.style.display = 'none';
    drop.appendChild(previewContainer);

    input.addEventListener('change', function (e) {
      var file = e.target.files && e.target.files[0];

      // Hide drop content, show preview container
      if (dropContent) dropContent.style.display = 'none';
      previewContainer.style.display = 'flex';
      previewContainer.innerHTML = '';

      if (!file) {
        // Reset to drop state if no file selected
        if (dropContent) dropContent.style.display = 'flex';
        previewContainer.style.display = 'none';
        return;
      }

      // Create file info container
      var fileInfo = document.createElement('div');
      fileInfo.className = 'file-info';
      fileInfo.style.display = 'flex';
      fileInfo.style.alignItems = 'center';
      fileInfo.style.gap = '12px';

      // Add file icon (image or document)
      var iconContainer = document.createElement('div');
      iconContainer.className = 'file-icon';
      iconContainer.style.width = '24px';
      iconContainer.style.height = '24px';
      iconContainer.style.borderRadius = '8px';
      iconContainer.style.border = '1px solid var(--green-mid)';
      iconContainer.style.display = 'flex';
      iconContainer.style.alignItems = 'center';
      iconContainer.style.justifyContent = 'center';
      iconContainer.style.backgroundColor = 'var(--green-wash)';
      iconContainer.style.flexShrink = '0';

      var isImage = file.type && file.type.indexOf('image') === 0;
      if (isImage) {
        // For images, show constrained thumbnail
        var img = document.createElement('img');
        img.className = 'rounded-lg border border-emerald-300 shadow-sm';
        img.style.cssText = 'width: 96px !important; height: 96px !important; max-width: 96px !important; max-height: 96px !important; object-fit: cover !important;';
        img.src = URL.createObjectURL(file);
        img.alt = 'Preview';
        img.onload = function () { URL.revokeObjectURL(img.src); };
        iconContainer.appendChild(img);
      } else if (file.type === 'application/pdf') {
        // For PDFs, show document icon
        iconContainer.innerHTML = '<i class="fa-solid fa-file-pdf text-red-500"></i>';
      } else {
        // For other files, show generic document icon
        iconContainer.innerHTML = '<i class="fa-solid fa-file text-gray-400"></i>';
      }

      fileInfo.appendChild(iconContainer);

      // File details
      var details = document.createElement('div');
      details.style.flex = '1';
      details.style.minWidth = '0';

      // Filename
      var nameEl = document.createElement('div');
      nameEl.className = 'file-name text-sm font-medium text-gray-900 truncate';
      nameEl.title = file.name;
      nameEl.textContent = file.name.length > 20 ? file.name.substring(0, 17) + '...' : file.name;
      details.appendChild(nameEl);

      // File size
      var sizeEl = document.createElement('div');
      sizeEl.className = 'file-size text-xs text-gray-500';
      var size = file.size;
      var units = ['B', 'KB', 'MB', 'GB'];
      var unitIndex = 0;
      while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
      }
      sizeEl.textContent = size.toFixed(1) + ' ' + units[unitIndex];
      details.appendChild(sizeEl);

      fileInfo.appendChild(details);

      // Remove/Change button
      var btnContainer = document.createElement('div');
      btnContainer.style.display = 'flex';
      btnContainer.style.alignItems = 'center';

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'text-xs text-red-600 hover:underline cursor-pointer p-1';
      removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Remove';
      removeBtn.onclick = function () {
        input.value = ''; // Clear file input
        previewContainer.style.display = 'none';
        if (dropContent) dropContent.style.display = 'flex';
      };

      btnContainer.appendChild(removeBtn);
      fileInfo.appendChild(btnContainer);

      previewContainer.appendChild(fileInfo);
    });
  });

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
  citySelect.addEventListener('change', populateBarangays);
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

    /* Enhanced file upload preview styles */
    .file-preview-container {
      max-width: 100%;
      overflow: hidden;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .file-info {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .file-icon {
      width: 24px;
      height: 24px;
      border-radius: 8px;
      border: 1px solid var(--green-mid);
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--green-wash);
      flex-shrink: 0;
    }
    .file-name {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--text-dark);
    }
    .file-size {
      font-size: 0.75rem;
      color: var(--text-muted);
    }
    .file-preview-img {
      width: 96px !important;
      height: 96px !important;
      max-width: 96px !important;
      max-height: 96px !important;
      object-fit: cover !important;
    }
</style>