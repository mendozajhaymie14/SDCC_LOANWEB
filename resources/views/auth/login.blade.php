<x-guest-layout>
    <!-- Page Container -->
    <div style="min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: #E5E7EB; padding: 2rem; font-family: sans-serif;">
        
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

            <!-- Main Black Frame Container (Border reduced to 2px) -->
            <div style="width: 100%; border: 2px solid #000000; border-radius: 48px; padding: 0; overflow: hidden; background-color: #ffffff; display: grid; grid-template-columns: 1fr 1fr; box-sizing: border-box; min-height: 480px; align-items: stretch; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
                
                <!-- Left Banner with bg.png -->
                <div style="position: relative; height: 100%; width: 100%; min-height: 480px; border-top-left-radius: 46px; border-bottom-left-radius: 46px; border-top-right-radius: 40px; border-bottom-right-radius: 40px; overflow: hidden; display: flex; align-items: center; justify-content: center; background-image: url('{{ asset('images/bg.png') }}'); background-size: cover; background-position: center; padding: 32px;">

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
                <div style="padding: 40px; display: flex; align-items: center; justify-content: center; background-color: #ffffff; border-top-right-radius: 46px; border-bottom-right-radius: 46px;">
                    <div style="width: 100%; box-sizing: border-box;">
                        
                        <x-validation-errors class="mb-4" />

                        @session('status')
                            <div style="margin-bottom: 16px; font-weight: 500; font-size: 14px; color: #16a34a;">
                                {{ $value }}
                            </div>
                        @endsession

                        <form method="POST" action="{{ route('login') }}" style="display: flex; flex-direction: column; gap: 18px; margin: 0;">
                            @csrf

                            <!-- Email / Username -->
<div style="display: flex; flex-direction: column; gap: 6px;">
    <x-label for="email" value="{{ __('Email / Username') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
    <x-input id="email" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2.5 px-3.5 bg-white" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
</div>



                            <!-- Password -->
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <x-label for="password" value="{{ __('Password') }}" style="font-size: 14px; font-weight: 700; color: #4b5563;" />
                                <x-input id="password" class="block w-full border-gray-200 rounded-lg shadow-sm focus:border-green-500 focus:ring-green-500 text-sm py-2.5 px-3.5 bg-white" type="password" name="password" required autocomplete="current-password" />
                            </div>

                            <!-- Remember Me -->
                            <div style="display: flex; align-items: center; margin-top: 4px;">
                                <x-checkbox id="remember_me" name="remember" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 w-4 h-4" />
                                <span style="margin-left: 10px; font-size: 13px; color: #6b7280;">{{ __('Remember me') }}</span>
                            </div>

                            <!-- Actions -->
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px;">
                                <div>
                                    @if (Route::has('password.request'))
                                        <a href="{{ route('password.request') }}" style="font-size: 12px; color: #6b7280; text-decoration: underline;">
                                            {{ __('Forgot your password?') }}
                                        </a>
                                    @endif
                                </div>

                                <button type="submit" style="background-color: #1C2536; color: #ffffff; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 10px 22px; border-radius: 8px; border: none; cursor: pointer;">
                                    {{ __('LOG IN') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-guest-layout>