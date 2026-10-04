<x-guest-layout>
    <div class="member-app-page" style="min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: #E5E7EB; padding: 2rem; font-family: sans-serif;">

        <div style="width: 100%; max-width: 560px; background: #ffffff; border: 2px solid #000000; border-radius: 48px; padding: 48px 40px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">

            <div style="width: 80px; height: 80px; border-radius: 50%; background: #16a34a; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                <svg xmlns="http://www.w3.org/2000/svg" style="width: 44px; height: 44px; color: #ffffff;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 style="font-size: 28px; font-weight: 800; color: #1C2536; margin-bottom: 12px; letter-spacing: 0.5px;">
                Application Received
            </h1>
            <p style="font-size: 14px; color: #4b5563; line-height: 1.7; margin-bottom: 28px;">
                Thank you, <strong>{{ $application->fullName }}</strong>. Your membership application has been submitted successfully.
            </p>

            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px 24px; text-align: left; margin-bottom: 28px;">
                <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px;">
                    <span style="color: #6b7280;">Application ID</span>
                    <span style="font-weight: 700; color: #1C2536;">#{{ str_pad($application->id, 5, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px;">
                    <span style="color: #6b7280;">Full Name</span>
                    <span style="font-weight: 700; color: #1C2536;">{{ $application->fullName }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px;">
                    <span style="color: #6b7280;">Email</span>
                    <span style="font-weight: 700; color: #1C2536;">{{ $application->email }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px;">
                    <span style="color: #6b7280;">Status</span>
                    <span style="font-weight: 700; color: #8a6d13;">{{ $application->statusLabel }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px;">
                    <span style="color: #6b7280;">Submitted</span>
                    <span style="font-weight: 700; color: #1C2536;">{{ $application->created_at->format('d M Y') }}</span>
                </div>
            </div>

            <p style="font-size: 13px; color: #6b7280; line-height: 1.7; margin-bottom: 24px;">
                Our membership officer will review your application and get back to you within 3–5 business days. You will receive an email notification once a decision has been made.
            </p>

            <div style="display: flex; gap: 12px; justify-content: center;">
                <a href="{{ url('/login') }}" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 100px; border: 2px solid #16a34a; background: transparent; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; color: #16a34a; cursor: pointer; text-decoration: none; transition: background 0.22s, color 0.22s;">
                    <i class="fa-solid fa-right-from-bracket"></i> Login
                </a>
                <a href="{{ url('/') }}" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 100px; border: 2px solid #16a34a; background: #16a34a; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; color: #ffffff; cursor: pointer; text-decoration: none; transition: background 0.22s;">
                    Go Home
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>