@extends('admin.index')

@section('admin_content')
<div style="padding: 1.5rem; width: 100%; max-width: 800px;">

    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #101828; margin: 0;">Application Details</h1>
        </div>

        <!-- KPI Badges -->
        <div style="display: flex; gap: 0.75rem;">
            <div style="background-color: #e2e8f0; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; color: #4a5568; display: block; text-transform: uppercase;">App ID</span>
                <span style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ $application->app_id }}</span>
            </div>
            <div style="background-color: #e2e8f0; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; color: #4a5568; display: block; text-transform: uppercase;">Amount</span>
                <span style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ '₱' . number_format($application->amount, 2) }}</span>
            </div>
            <div style="background-color: #e2e8f0; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; color: #4a5568; display: block; text-transform: uppercase;">AI Score</span>
                <span style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ $application->ai_score }}/100</span>
            </div>
        </div>
    </div>

    <!-- Application Details Card -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 1.25rem;">
        <div style="font-size: 0.875rem; color: #4a5568; margin-bottom: 1rem;">
            <strong>Applicant:</strong> {{ $application->applicant }}
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Loan Type:</strong> {{ $application->loan_type }}
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Requested Amount:</strong> {{ '₱' . number_format($application->amount, 2) }}
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Status:</strong>
            <span style="background-color: {{ $application->status === 'Approved' ? '#dcfce7' : '#fef3c7' }}; color: {{ $application->status === 'Approved' ? '#15803d' : '#92400e' }}; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">{{ ucfirst($application->status) }}</span>
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Date Submitted:</strong> {{ \Carbon\Carbon::parse($application->created_at)->format('M d, Y') }}
        </div>

        {{-- AI Risk Breakdown --}}
        <div style="margin-bottom: 1rem;">
            <strong>AI Risk Breakdown:</strong>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>DTI Ratio:</strong> {{ isset($loanApp->debt_to_income_ratio) ? $loanApp->debt_to_income_ratio . '%' : 'N/A' }}
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Document Score:</strong> {{ $application->ai_score !== null ? $application->ai_score . '/100' : 'N/A' }}
        </div>
        <div>
            <strong>Credit Rating:</strong> {{ isset($loanApp->ai_credit_score) ? $loanApp->ai_credit_score : 'N/A' }}
        </div>

        {{-- Risk Evaluation Results --}}
        @if($application->recommended_amount || $application->risk_level)
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
            <strong>Loan Evaluation:</strong>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Recommended Amount:</strong> {{ '₱' . number_format($application->recommended_amount, 2) }}
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Risk Level:</strong>
            <span style="background-color: {{ $application->risk_level === 'Low' ? '#dcfce7' : ($application->risk_level === 'Medium' ? '#fef3c7' : '#fee2e2') }}; color: {{ $application->risk_level === 'Low' ? '#15803d' : ($application->risk_level === 'Medium' ? '#92400e' : '#dc2626') }}; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">{{ ucfirst($application->risk_level) }}</span>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Risk Score:</strong> {{ $application->risk_score }}/100
        </div>
        @if($application->risk_factors)
        <div style="margin-bottom: 0.5rem;">
            <strong>Risk Factors:</strong>
            <ul style="margin: 0; padding-left: 1.25rem; color: #4a5568; font-size: 0.875rem;">
                @foreach(json_decode($application->risk_factors, true) as $factor)
                    <li>{{ $factor['reason'] ?? $factor }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        @endif
    </div>

    {{-- Action Buttons --}}
    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
        @if(in_array(strtolower($application->status), ['pending', 'flagged']))
            {{-- Approval button (only available while the application is still pending) --}}
            @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::APPROVE_LOANS))
            <form method="POST" action="{{ route('applications.approve', ['appId' => $application->app_id]) }}" style="display: inline;">
                @csrf
                @method('POST')
                <button type="submit" style="background-color: #10b981; color: #ffffff; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; text-decoration: none; margin-right: 0.5rem;">Approve</button>
            </form>
            @endif
            {{-- Rejection button (only available while the application is still pending) --}}
            @if(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::REJECT_LOANS))
            <form method="POST" action="{{ route('applications.reject', ['appId' => $application->app_id]) }}" style="display: inline;">
                @csrf
                @method('POST')
                <button type="submit" style="background-color: #ef4444; color: #ffffff; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; text-decoration: none;">Reject</button>
            </form>
            @endif
        @elseif(\App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::APPROVE_LOANS)
            || \App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::REJECT_LOANS))
            <p style="color: var(--muted); font-size: 0.875rem; margin: 0;">
                This application has already been <strong>{{ strtolower($application->status) }}</strong>.
                Decision buttons are only available for pending applications.
            </p>
        @endif
    </div>
</div>
@endsection