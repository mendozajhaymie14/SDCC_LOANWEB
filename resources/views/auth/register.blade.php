<x-guest-layout>
    <!-- Page Container -->
    <div class="register-page" style="min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: #E5E7EB; padding: 2rem; font-family: sans-serif;">

        <!-- Expanded Width Wrapper -->
        <div style="width: 100%; max-width: 1100px; margin: 0 auto;">

            <!-- Go Back Button -->
            <div style="width: 100%; display: flex; justify-content: flex-start; margin-bottom: 14px;">
                <a href="/" style="display: inline-flex; align-items: center; gap: 8px; color: #000000; font-weight: 600; font-size: 16px; text-decoration: none;">
                    <svg style="width: 20px; height: 20px; stroke: #000000;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Go Back</span>
                </a>
            </div>

            <!-- Main Black Frame Container -->
            <div class="register-frame" style="width: 100%; border: 2px solid #000000; border-radius: 48px; padding: 0; overflow: hidden; background-color: #ffffff; display: grid; grid-template-columns: 1fr 1fr; box-sizing: border-box; min-height: 520px; align-items: stretch; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">

                <!-- Left Banner -->
                <div class="register-banner" style="position: relative; height: 100%; width: 100%; min-height: 520px; border-top-left-radius: 46px; border-bottom-left-radius: 46px; border-top-right-radius: 40px; border-bottom-right-radius: 40px; overflow: hidden; display: flex; align-items: center; justify-content: center; background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-position: center; padding: 32px;">

                    <!-- Banner Content -->
                    <div style="position: relative; z-index: 10; display: flex; align-items: center; justify-content: center; gap: 22px; width: 100%;">

                        <!-- Logo -->
                        <div style="width: 130px; height: 130px; border-radius: 50%; background-color: #ffffff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; padding: 5px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                            <img src="{{ asset('images/Logo.jpg') }}"
                                 alt="San Dionisio Credit Cooperative Logo"
                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>

                        <!-- White Text -->
                        <h1 style="font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: 0.05em; line-height: 1.25; text-transform: uppercase; margin: 0; text-align: left; max-width: 260px;">
                            San Dionisio<br>Credit<br>Cooperative
                        </h1>
                    </div>
                </div>

                <!-- Right Form Side -->
                <div class="register-form-side" style="padding: 40px; display: flex; align-items: center; justify-content: center; background-color: #ffffff; border-top-right-radius: 46px; border-bottom-right-radius: 46px;">
                    <div style="width: 100%; box-sizing: border-box;" x-data="{
                        isExistingMember: {{ old('is_existing_member') == '1' ? '1' : '0' }},
                        memberStatus: '',
                        first_name: {{ json_encode(old('first_name')) }},
                        middle_name: {{ json_encode(old('middle_name')) }},
                        last_name: {{ json_encode(old('last_name')) }},
                        lookupError: '',
                        lookupSuccess: '',
                        lookupMember() {
                            this.lookupError = '';
                            this.lookupSuccess = '';
                            const id = (this.$refs.member_id.value || '').trim();
                            if (!id) {
                                this.first_name = '';
                                this.last_name = '';
                                this.middle_name = '';
                                return;
                            }
                            fetch('{{ route('member.lookup', ['memberId' => '__MEMBER_ID__']) }}'.replace('__MEMBER_ID__', encodeURIComponent(id)), { headers: { Accept: 'application/json' } })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.ok) {
                                        this.first_name = data.member.first_name;
                                        this.middle_name = data.member.middle_name;
                                        this.last_name = data.member.last_name;
                                        this.lookupSuccess = 'Member details loaded from our records.';
                                        this.lookupError = '';
                                    } else {
                                        this.first_name = '';
                                        this.last_name = '';
                                        this.middle_name = '';
                                        this.lookupError = data.message || 'Member ID not found. Please check and try again.';
                                    }
                                })
                                .catch(() => {
                                    this.first_name = '';
                                    this.last_name = '';
                                    this.middle_name = '';
                                    this.lookupError = 'Could not look up that Member ID right now.';
                                });
                        }
                    }">

                        <x-validation-errors class="mb-4" />

                        <form method="POST" action="{{ route('register') }}" class="register-actions" style="display: flex; flex-direction: column; gap: 14px; margin: 0;">
                            @csrf

                            <!-- Cooperative Member Selection Toggle -->
                            <div style="display: flex; flex-direction: column; gap: 6px; background-color: #f9fafb; padding: 12px; border-radius: 8px; border: 1px solid #e5e7eb;">
                                <label style="font-size: 13px; font-weight: 700; color: #374151;">Are you an existing Cooperative Member?</label>
                                <div style="display: flex; align-items: center; gap: 16px;">
                                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #4b5563; cursor: pointer;">
                                        <input type="radio" name="is_existing_member" value="1" x-model.number="isExistingMember" style="color: #16a34a;">
                                        <span>Yes, I am a member</span>
                                    </label>
                                    <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #4b5563; cursor: pointer;">
                                        <input type="radio" name="is_existing_member" value="0" x-model.number="isExistingMember" style="color: #16a34a;">
                                        <span>No, I am new</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Existing Member Verification Fields (Shows ONLY when 'Yes' is selected) -->
                            <div x-show="isExistingMember === 1" x-cloak style="display: flex; flex-direction: column; gap: 10px; background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px; border-radius: 8px;">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <x-label for="member_id" value="{{ __('Member ID') }}" style="font-size: 13px; font-weight: 700; color: #166534;" />
                                    <x-input id="member_id" x-ref="member_id" class="block w-full border-gray-300 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="member_id" :value="old('member_id')" placeholder="e.g. SDCC-2023-0001" x-bind:required="isExistingMember === 1" @input.debounce.500ms="lookupMember" />
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <x-label for="first_name" value="{{ __('First Name') }}" style="font-size: 13px; font-weight: 700; color: #166534;" />
                                        <x-input id="first_name" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="first_name" x-bind:value="first_name" placeholder="Auto-filled from member record" x-bind:readonly="lookupSuccess !== ''" x-bind:required="isExistingMember === 1" autocomplete="given-name" />
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <x-label for="middle_name" value="{{ __('Middle Name') }}" style="font-size: 13px; font-weight: 700; color: #166534;" />
                                        <x-input id="middle_name" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="middle_name" x-bind:value="middle_name" placeholder="Optional" x-bind:readonly="lookupSuccess !== ''" autocomplete="additional-name" />
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <x-label for="last_name" value="{{ __('Last Name') }}" style="font-size: 13px; font-weight: 700; color: #166534;" />
                                        <x-input id="last_name" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="last_name" x-bind:value="last_name" placeholder="Auto-filled from member record" x-bind:readonly="lookupSuccess !== ''" x-bind:required="isExistingMember === 1" autocomplete="family-name" />
                                    </div>
                                </div>
                                <div x-show="lookupError" x-cloak style="font-size: 12px; color: #dc2626; font-weight: 600;">
                                    <span x-text="lookupError"></span>
                                </div>
                                <div x-show="lookupSuccess" x-cloak style="font-size: 12px; color: #166534; font-weight: 600;">
                                    <span x-text="lookupSuccess"></span>
                                </div>
                            </div>

                            <!-- Split Name Fields (Vanishes when 'Yes' is selected) -->
                            <div x-show="isExistingMember === 0" x-cloak style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <x-label for="first_name" value="{{ __('First Name') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                    <x-input id="first_name" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="first_name" x-bind:value="first_name" x-bind:required="isExistingMember === 0" autocomplete="given-name" />
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <x-label for="middle_name" value="{{ __('Middle Name') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                    <x-input id="middle_name" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="middle_name" x-bind:value="middle_name" autocomplete="additional-name" />
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <x-label for="last_name" value="{{ __('Last Name') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                    <x-input id="last_name" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="text" name="last_name" x-bind:value="last_name" x-bind:required="isExistingMember === 0" autocomplete="family-name" />
                                </div>
                            </div>

                            <!-- Email -->
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <x-label for="email" value="{{ __('Email') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                <x-input id="email" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 bg-white" type="email" name="email" :value="old('email')" required autocomplete="username" pattern=".+@gmail\.com" title="Only @gmail.com email addresses are allowed" />
                            </div>

                            <!-- Password with Eye Toggle -->
                            <div style="display: flex; flex-direction: column; gap: 4px;" x-data="{ show: false }">
                                <x-label for="password" value="{{ __('Password') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                <div style="position: relative; width: 100%;">
                                    <x-input id="password" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 pr-10 bg-white" type="password" x-bind:type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" />
                                    <button type="button" @click="show = !show" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280; padding: 0; display: flex; align-items: center;">
                                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <svg x-show="show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;" x-cloak>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password with Eye Toggle -->
                            <div style="display: flex; flex-direction: column; gap: 4px;" x-data="{ show: false }">
                                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                <div style="position: relative; width: 100%;">
                                    <x-input id="password_confirmation" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2 px-3 pr-10 bg-white" type="password" x-bind:type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" />
                                    <button type="button" @click="show = !show" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280; padding: 0; display: flex; align-items: center;">
                                        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <svg x-show="show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 20px; height: 20px;" x-cloak>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Terms & Privacy Policy -->
                            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                                <div style="margin-top: 4px;">
                                    <x-label for="terms">
                                        <div style="display: flex; align-items: center;">
                                            <x-checkbox name="terms" id="terms" required class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 w-4 h-4" />
                                            <div style="margin-left: 8px; font-size: 12px; color: #6b7280;">
                                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" style="text-decoration: underline; color: #4b5563;">'.__('Terms of Service').'</a>',
                                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" style="text-decoration: underline; color: #4b5563;">'.__('Privacy Policy').'</a>',
                                                ]) !!}
                                            </div>
                                        </div>
                                    </x-label>
                                </div>
                            @endif

                            <!-- Actions -->
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px;">
                                <a href="{{ route('login') }}" style="font-size: 12px; color: #6b7280; text-decoration: underline;">
                                    {{ __('Already registered?') }}
                                </a>

                                <button type="submit" style="background-color: #1C2536; color: #ffffff; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 10px 22px; border-radius: 8px; border: none; cursor: pointer;">
                                    {{ __('REGISTER') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        @media (max-width: 991px) {
            .register-page { padding: 1rem; }
            .register-frame { border-radius: 28px; min-height: auto; display: block; }
            .register-banner { min-height: 240px; border-radius: 24px 24px 0 0; }
            .register-form-side { border-radius: 0 0 24px 24px; }
        }

        @media (max-width: 768px) {
            .register-page { padding: 0.75rem; }
            .register-frame { border-radius: 20px; }
            .register-banner { min-height: 200px; padding: 24px 20px; }
            .register-form-side { padding: 28px 20px; }
            .register-actions > div:last-child > button { width: 100%; padding: 12px 16px; }
        }

        @media (max-width: 480px) {
            .register-page { padding: 0.5rem; }
            .register-banner { min-height: 180px; padding: 16px; }
            .register-form-side { padding: 24px 16px; }
        }
    </style>
</x-guest-layout>