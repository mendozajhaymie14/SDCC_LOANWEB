<!DOCTYPE html>
<html lang="en">
<head>
  @include('user.css')
  <title>My Profile — San Dionisio Credit Cooperative</title>
</head>
<body>

  <header>
    @include('user.header')
  </header>

  @include('user.sidebar')

  <main class="member-main">
    @include('user.flash')

    <section class="passbook">
      <div>
        <div class="passbook-greeting">My Cooperative Profile</div>
        <p class="passbook-meta">
          Your member details on file with the cooperative. Fields marked read-only
          can only be changed by contacting the cooperative office.
        </p>
      </div>
    </section>

    <div class="panel" style="margin-top: 32px;">
      <div class="panel-head">
        <h2 class="panel-title">Profile Details</h2>
      </div>
      <div class="panel-body">
        <form method="POST" action="{{ route('user.profile.update') }}" id="profileForm">
          @csrf
          @method('PUT')

          {{-- Read-only fields --}}
          <div class="grid-2" style="margin-bottom: 24px;">
            <div class="field">
              <label>Member ID <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ $member->member_id }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Full Name <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ $member->full_name }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Status <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ ucfirst($member->status) }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Savings Balance <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ \App\Support\Format::money($member->savings_balance ?? 0) }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Monthly Income <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ \App\Support\Format::money($member->monthly_income ?? 0) }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Existing Monthly Obligations <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ \App\Support\Format::money($member->existing_monthly_obligations ?? 0) }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Approved Credit Limit <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ $member->creditLimit() !== null ? \App\Support\Format::money($member->creditLimit()) : 'Not assessed' }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
            <div class="field">
              <label>Source of Income <span class="note" style="color: #7a927e;">(read-only)</span></label>
              <input type="text" value="{{ $member->source_of_income ?? 'N/A' }}" readonly class="bg-gray-100 cursor-not-allowed" style="border: 1px solid #d8e8db;">
              <p class="note" style="color: #a32b2b;">To change this, contact the cooperative office.</p>
            </div>
          </div>

          <hr style="border-color: #e2f0e4; margin: 32px 0;">

          {{-- Editable fields --}}
          <div class="grid-2" style="margin-bottom: 24px;">
            <div class="field">
              <label for="email">Email Address <span class="hint">*</span></label>
              <input type="email" id="email" name="email"
                     value="{{ old('email', $member->email) }}"
                     class="@error('email') has-error @enderror" required autocomplete="email">
              @error('email') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="field">
              <label for="current_password">Current Password <span class="hint">(required only when changing email)</span></label>
              <input type="password" id="current_password" name="current_password"
                     class="@error('current_password') has-error @enderror" autocomplete="current-password" placeholder="••••••••">
              @error('current_password') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="field">
              <label for="contact_number">Contact Number <span class="hint">(Philippine mobile)</span></label>
              <input type="tel" id="contact_number" name="contact_number"
                     value="{{ old('contact_number', $member->contact_number) }}"
                     class="@error('contact_number') has-error @enderror" autocomplete="tel" placeholder="09XXXXXXXXX">
              @error('contact_number') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="field full">
              <label for="address">Address</label>
              <textarea id="address" name="address"
                        class="@error('address') has-error @enderror" rows="3" placeholder="Complete address">{{ old('address', $member->address) }}</textarea>
              @error('address') <span class="error-text">{{ $message }}</span> @enderror
            </div>

            <div class="field">
              <label for="occupation">Occupation</label>
              <input type="text" id="occupation" name="occupation"
                     value="{{ old('occupation', $member->occupation) }}"
                     class="@error('occupation') has-error @enderror" autocomplete="off">
              @error('occupation') <span class="error-text">{{ $message }}</span> @enderror
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-solid">Save changes</button>
            <a href="{{ route('user.dashboard') }}" class="btn-cancel">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  @include('user.scripts')

</body>
</html>