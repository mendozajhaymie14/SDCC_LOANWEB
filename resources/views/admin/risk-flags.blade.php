@extends('admin.index')

@section('admin_content')
<div style="padding: 1.5rem; width: 100%;">

    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #101828; margin: 0;">Risk Flags</h1>
            <p style="font-size: 0.875rem; color: #667085; margin-top: 0.25rem;">High Risk Applications: {{ $riskFlagsCount ?? count($flaggedApplications) }}</p>
        </div>

        <!-- KPI Summary Badges -->
        <div style="display: flex; gap: 0.75rem;">
            <div style="background-color: #fef2f2; border: 1px solid #fecaca; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #991b1b; display: block; text-transform: uppercase;">High Risk</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #991b1b;">{{ $highRiskCount ?? $riskFlagsCount ?? 0 }}</span>
            </div>
            <div style="background-color: #fffbeb; border: 1px solid #fde68a; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #92400e; display: block; text-transform: uppercase;">Moderate Risk</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #92400e;">{{ $moderateRiskCount ?? 0 }}</span>
            </div>
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #166534; display: block; text-transform: uppercase;">Low Risk</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #166534;">{{ $lowRiskCount ?? 0 }}</span>
            </div>
        </div>
    </div>

    <!-- Risk Flags Table Card -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 1.25rem;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid #eaecf0;">
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">App ID</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Borrower Name</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Risk Level</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Flag Reason</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Loan Type</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Amount</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Recommended</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Date</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($flaggedApplications as $flag)
                    <tr style="border-bottom: 1px solid #f2f4f7;">
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; font-weight: 600; color: #344054;">
                            {{ $flag->app_id }}
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            {{ $flag->applicant }}
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #dc2626; font-weight: 700;">
                            High
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            {{ $flagReasons[$flag->id] ?? 'High risk factors detected' }}
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            <span class="loan-type-tag">{{ $flag->loan_type }}</span>
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            ₱{{ number_format($flag->amount, 2) }}
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            ₱{{ number_format($flag->recommended_amount ?? 0, 2) }}
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            {{ \Carbon\Carbon::parse($flag->created_at)->format('M d, Y') }}
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">
                            <a href="{{ url('/applications') }}?open={{ urlencode($flag->app_id) }}" style="background-color: #2563eb; color: #ffffff; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-block;">Review &rarr;</a>
                        </td>
                    </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 1.5rem; color: #667085;">
                        No flagged applications found — all applications are currently within acceptable risk parameters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection