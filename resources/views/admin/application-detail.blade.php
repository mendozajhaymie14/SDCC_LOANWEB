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
                <span class="num" style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ \App\Support\Format::money($application->amount) }}</span>
            </div>
            <div style="background-color: #e2e8f0; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; color: #4a5568; display: block; text-transform: uppercase;">Recommended Amount & Risk (Rule-Based)</span>
                <span class="num" style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ $application->ai_score }}/100</span>
            </div>
        </div>
    </div>

    <!-- Application Details Card -->
    <div id="appDetailCard" style="background: #ffffff; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 1.25rem;">
        <div style="font-size: 0.875rem; color: #4a5568; margin-bottom: 1rem;">
            <strong>Applicant:</strong> {{ $application->applicant }}
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Loan Type:</strong> {{ $application->loan_type }}
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Requested Amount:</strong> <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($application->amount) }}</span>
        </div>

        @php
            $coopMember = $application->user?->coopMember;
            if (!$coopMember && $application->email) {
                $coopMember = \App\Models\CoopMember::where('email', $application->email)->first();
            }
            $monthlyCapacity = $coopMember ? $coopMember->monthlyCapacity() : null;
            $monthlyUsed = $coopMember ? $coopMember->monthlyUsed() : 0;
            $monthlyAvailable = $coopMember ? $coopMember->monthlyAvailable() : null;
            $approvedLimit = $coopMember ? $coopMember->approved_credit_limit : null;
            $outstanding = $coopMember ? \App\Models\CoopMember::outstandingBalance($coopMember) : 0;
            $availableCreditTotal = $approvedLimit !== null ? (float)$approvedLimit - $outstanding : null;
            $isCapacitySuggested = $monthlyCapacity !== null && $coopMember && $coopMember->monthly_credit_limit_source === 'auto';
        @endphp

        <div style="margin-bottom: 1rem;">
            <strong>Monthly Used:</strong> <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($monthlyUsed) }}</span>
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Monthly Available:</strong>
            @if($monthlyAvailable !== null)
                <span class="num" style="color:var(--success);font-weight:600;">{{ \App\Support\Format::money($monthlyAvailable) }}</span>
            @else
                <span style="color:var(--muted);font-style:italic;">Not assessed</span>
            @endif
            @if($monthlyCapacity !== null)
                <div style="font-size:.75rem;color:var(--muted);margin-top:.2rem;">
                    This month: <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($monthlyUsed) }}</span> approved of <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($monthlyCapacity) }}</span> limit
                    @if($isCapacitySuggested) <span style="font-size:.75rem;color:var(--muted);">(suggested)</span> @endif
                </div>
            @endif
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Max for 12 months:</strong>
            @if($monthlyCapacity !== null)
                <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($monthlyCapacity * 12) }}</span>
            @else
                <span style="color:var(--muted);font-style:italic;">Not assessed</span>
            @endif
        </div>

        @if($approvedLimit !== null)
        <div style="margin-bottom: 1rem; display:flex;justify-content:space-between;padding:.4rem 0;font-size:.78rem;color:var(--muted);">
            <span>Total credit limit <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($approvedLimit) }}</span> · Available credit <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($availableCreditTotal ?? 0) }}</span></span>
        </div>
        @endif

        <div style="margin-bottom: 1rem;">
            <strong>Status:</strong>
            <span style="background-color: {{ $application->status === 'Approved' ? '#dcfce7' : '#fef3c7' }}; color: {{ $application->status === 'Approved' ? '#15803d' : '#92400e' }}; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">{{ ucfirst($application->status) }}</span>
        </div>

        <div style="margin-bottom: 1rem;">
            <strong>Date Submitted:</strong> {{ \Carbon\Carbon::parse($application->created_at)->format('M d, Y') }}
        </div>

        {{-- Rule-Based Risk Breakdown --}}
        <div style="margin-bottom: 1rem;">
            <strong>Rule-Based Risk Breakdown:</strong>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>DTI Ratio:</strong> {{ isset($loanApp->debt_to_income_ratio) ? $loanApp->debt_to_income_ratio . '%' : 'N/A' }}
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Document Check:</strong> <span class="num" style="font-weight:600;">{{ $application->ai_score !== null ? $application->ai_score . '/100' : 'N/A' }}</span> (Rule-Based Score)
        </div>
        <div>
            <strong>Credit Rating:</strong> <span class="num" style="font-weight:600;">{{ isset($loanApp->ai_credit_score) ? $loanApp->ai_credit_score : 'N/A' }}</span>
        </div>

        {{-- Risk Evaluation Results --}}
        @if($application->recommended_amount || $application->risk_level)
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
            <strong>Loan Evaluation:</strong>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Recommended Amount:</strong> <span class="num" style="font-weight:600;">{{ \App\Support\Format::money($application->recommended_amount) }}</span>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Risk Level:</strong>
            <span style="background-color: {{ $application->risk_level === 'Low' ? '#dcfce7' : ($application->risk_level === 'Medium' ? '#fef3c7' : '#fee2e2') }}; color: {{ $application->risk_level === 'Low' ? '#15803d' : ($application->risk_level === 'Medium' ? '#92400e' : '#dc2626') }}; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">{{ ucfirst($application->risk_level) }}</span>
        </div>
        <div style="margin-bottom: 0.5rem;">
            <strong>Risk Score:</strong> <span class="num" style="font-weight:600;">{{ $application->risk_score }}/100</span>
        </div>
        @if($application->reasons)
        <div style="margin-bottom: 0.5rem;">
            <strong>Risk Factors:</strong>
            <ul style="margin: 0; padding-left: 1.25rem; color: #4a5568; font-size: 0.875rem;">
                @foreach($application->reasons as $reason)
                    <li>{{ $reason }}</li>
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