@extends('admin.index')

@section('admin_content')
<div style="padding: 1.5rem; width: 100%;">

    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #101828; margin: 0;">Credit Assessment</h1>
            <p style="font-size: 0.875rem; color: #667085; margin-top: 0.25rem;">AI Scoring Summary: {{ $totalEvaluated }} Evaluated</p>
        </div>

        <!-- KPI Summary Badges -->
        <div style="display: flex; gap: 0.75rem;">
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #166534; display: block; text-transform: uppercase;">Total Evaluated</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #166534;">{{ $totalEvaluated }}</span>
            </div>
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #166534; display: block; text-transform: uppercase;">High Grade %</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #166534;">{{ $highGradePct }}%</span>
            </div>
            <div style="background-color: #fef2f2; border: 1px solid #fecaca; padding: 0.5rem 1rem; border-radius: 8px; text-align: center;">
                <span style="font-size: 0.7rem; font-weight: 700; color: #991b1b; display: block; text-transform: uppercase;">High Risk %</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #991b1b;">{{ $highRiskPct }}%</span>
            </div>
        </div>
    </div>

    <!-- Filter Row -->
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <button class="filter-btn active" onclick="filterCredit('all',this)">All ({{ $applications->count() }})</button>
        <button class="filter-btn" onclick="filterCredit('low',this)">Low Risk</button>
        <button class="filter-btn" onclick="filterCredit('medium',this)">Medium Risk</button>
        <button class="filter-btn" onclick="filterCredit('high',this)">High Risk</button>
        <button class="filter-btn" onclick="filterCredit('unscored',this)">Unscored</button>
    </div>

    <!-- Credit Assessment Table Card -->
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #eaecf0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 1.25rem;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid #eaecf0;">
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">App ID</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Borrower</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Requested</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">AI Score</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Risk Level</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Recommended</th>
                    <th style="padding: 0.75rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #667085; text-transform: uppercase;">Action</th>
                </tr>
            </thead>
            <tbody id="credit-tbody">
                @forelse($applications as $app)
                    @php
                        $riskScore = $app->risk_score ?? 0;
                        $riskLevel = $app->risk_level ?? 'N/A';
                        $status = strtolower($riskLevel);
                        $aiScore = $app->ai_score ?? 'N/A';
                        $recommended = $app->recommended_amount ?? $app->amount ?? 0;
                        $borrower = $app->loanApplication?->full_name ?? $app->applicant ?? 'Unknown';
                    @endphp
                    <tr data-status="{{ $status }}" style="border-bottom: 1px solid #f2f4f7;">
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054; font-weight: 600;">{{ $app->app_id }}</td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">{{ $borrower }}</td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">₱{{ number_format($app->amount ?? 0, 2) }}</td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054; font-family: monospace; font-weight: 700;">{{ $aiScore }}</td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem;">
                            <span class="status-pill {{ $status }}">
                                {{ $riskLevel }}
                            </span>
                        </td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem; color: #344054;">₱{{ number_format($recommended, 2) }}</td>
                        <td style="padding: 0.85rem 0.5rem; font-size: 0.85rem;">
                            <a href="{{ \App\Support\Permissions::has(auth()->user(), \App\Support\Permissions::ENCODE_APPLICATIONS) ? url('/applications').'?open='.urlencode($app->app_id) : route('admin.applications.show', ['id' => $app->id]) }}" style="background-color: #2563eb; color: #ffffff; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-block;">Review &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: #667085;">
                            No applications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection