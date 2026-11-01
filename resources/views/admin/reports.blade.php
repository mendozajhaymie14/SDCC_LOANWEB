@extends('admin.index')

@section('admin_content')
@php
    $reportType = $filters['report_type'] ?? 'loan_summary';

    $reportTitles = [
        'loan_summary'         => 'Loan Summary',
        'approved_records'     => 'Approved Records',
        'borrower_performance' => 'Borrower Performance',
        'overdue_payments'     => 'Overdue Payments',
    ];

    // Exports reuse exactly what is on screen. Borrower Performance has no status filter.
    $exportQuery = collect(request()->query())
        ->except($reportType === 'borrower_performance' ? ['status', 'page'] : ['page'])
        ->all();

    $hasFilters = collect(['start_date', 'end_date', 'status', 'risk_level'])
        ->filter(fn ($k) => !empty($filters[$k] ?? null))
        ->isNotEmpty();

    $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M d, Y') : null;

    // Colour a label (risk level or performance rating) without assuming exact wording.
    $pill = fn ($label) => match (strtolower(trim((string) $label))) {
        'low', 'good', 'excellent', 'on-time', 'on time', 'reliable' => 'ok',
        'medium', 'fair', 'average', 'moderate', 'watch'             => 'warn',
        'high', 'poor', 'bad', 'delinquent', 'at risk'               => 'bad',
        default                                                       => 'neutral',
    };
@endphp

<style>
  .rpt-page { display:flex; flex-direction:column; gap:1.25rem; }
  .rpt-card { background:var(--surface,#fff); border:1px solid var(--border,#e2e8f0); border-radius:16px; padding:1.25rem 1.4rem; }
  .rpt-title { margin:0; font-size:1.35rem; font-weight:700; color:var(--text,#0f172a); }
  .rpt-sub { margin:.25rem 0 0; font-size:.85rem; color:var(--muted,#64748b); }
  .rpt-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; margin-bottom:1.1rem; }
  .rpt-actions { display:flex; gap:.6rem; flex-wrap:wrap; }

  .rpt-btn { display:inline-flex; align-items:center; gap:.45rem; padding:.55rem 1rem; border-radius:10px; font-size:.85rem; font-weight:600;
             text-decoration:none; cursor:pointer; border:1px solid var(--border2,#cbd5e1); background:var(--surface,#fff); color:var(--muted2,#334155); transition:.15s; }
  .rpt-btn:hover { background:var(--card2,#f1f5f9); border-color:var(--accent,#0284c7); color:var(--accent,#0284c7); }
  .rpt-btn-primary { background:var(--accent3,#059669); border-color:var(--accent3,#059669); color:#fff; }
  .rpt-btn-primary:hover { background:#047857; border-color:#047857; color:#fff; }

  .rpt-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:.9rem; align-items:end;
                 padding:1rem 1.1rem; background:var(--card2,#f1f5f9); border:1px solid var(--border,#e2e8f0); border-radius:14px; }
  .rpt-field { display:flex; flex-direction:column; gap:.35rem; min-width:0; }
  .rpt-field label { font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted,#64748b); }
  .rpt-field select, .rpt-field input[type="date"] { box-sizing:border-box; width:100%; min-width:0; height:40px; line-height:40px; padding:0 .7rem; font:inherit; font-size:.88rem;
             color:var(--text,#0f172a); background:var(--surface,#fff); border:1px solid var(--border2,#cbd5e1); border-radius:10px; outline:none; transition:.15s; }
  .rpt-field select:focus, .rpt-field input[type="date"]:focus { border-color:var(--accent,#0284c7); box-shadow:0 0 0 3px rgba(2,132,199,.15); }
  .rpt-field select:disabled { opacity:.55; cursor:not-allowed; }
  .rpt-reset { align-self:end; }
  .rpt-reset a { display:inline-flex; align-items:center; gap:.4rem; height:40px; padding:0 .4rem; font-size:.85rem; font-weight:600; color:var(--red,#dc2626); text-decoration:none; }
  .rpt-reset a:hover { text-decoration:underline; }

  .rpt-chips { display:flex; flex-wrap:wrap; gap:.4rem; margin-top:.9rem; }
  .rpt-chip { display:inline-flex; align-items:center; padding:.25rem .65rem; border-radius:999px; font-size:.75rem; font-weight:600;
              background:rgba(2,132,199,.1); color:var(--accent,#0284c7); }

  .rpt-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1rem; }
  .rpt-kpi { display:flex; align-items:center; gap:.9rem; padding:1.1rem 1.2rem; border:1px solid var(--border,#e2e8f0); border-radius:14px; background:var(--surface,#fff); }
  .rpt-kpi-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
  .rpt-kpi-label { font-size:.78rem; font-weight:600; color:var(--muted,#64748b); }
  .rpt-kpi-value { font-size:1.55rem; font-weight:700; line-height:1.2; color:var(--text,#0f172a); }
  .rpt-kpi-note { font-size:.7rem; color:var(--muted,#64748b); }

  .rpt-section-head { display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:.9rem; }
  .rpt-section-title { margin:0; font-size:1.05rem; font-weight:700; color:var(--text,#0f172a); }
  .rpt-count { font-size:.78rem; font-weight:600; color:var(--muted,#64748b); }

  .rpt-grid3 { display:grid; grid-template-columns:repeat(auto-fit, minmax(290px, 1fr)); gap:1rem; }
  .rpt-subcard { border:1px solid var(--border,#e2e8f0); border-radius:14px; padding:1rem 1.1rem; background:var(--surface,#fff); }
  .rpt-subtitle { margin:0 0 .6rem; font-size:.78rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--muted,#64748b); }

  .rpt-table-wrap { width:100%; overflow-x:auto; }
  .rpt-table { width:100%; border-collapse:collapse; font-size:.85rem; }
  .rpt-table th { text-align:left; padding:.6rem .75rem; font-size:.7rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
                  color:var(--muted,#64748b); background:var(--card2,#f1f5f9); border-bottom:1px solid var(--border,#e2e8f0); white-space:nowrap; }
  .rpt-table td { padding:.65rem .75rem; border-bottom:1px solid var(--border,#e2e8f0); color:var(--text,#0f172a); vertical-align:middle; }
  .rpt-table tbody tr:hover { background:rgba(2,132,199,.04); }
  .rpt-table .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
  .rpt-table .ctr { text-align:center; font-variant-numeric:tabular-nums; }
  .rpt-table tfoot td { font-weight:700; background:var(--card2,#f1f5f9); border-bottom:0; }
  .rpt-mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.8rem; }

  .rpt-pill { display:inline-block; padding:.18rem .6rem; border-radius:999px; font-size:.72rem; font-weight:700; }
  .rpt-pill.ok      { background:rgba(5,150,105,.12);  color:#047857; }
  .rpt-pill.warn    { background:rgba(217,119,6,.14);  color:#b45309; }
  .rpt-pill.bad     { background:rgba(220,38,38,.12);  color:#b91c1c; }
  .rpt-pill.neutral { background:rgba(100,116,139,.14); color:#475569; }
  .rpt-late { color:#b91c1c; font-weight:700; }

  .rpt-empty { padding:2.2rem 1rem; text-align:center; color:var(--muted,#64748b); }
  .rpt-empty i { font-size:1.6rem; margin-bottom:.5rem; display:block; opacity:.6; }
  .rpt-empty a { color:var(--accent,#0284c7); font-weight:600; text-decoration:none; }

  @media (max-width: 640px) {
    .rpt-card { padding:1rem; }
    .rpt-kpi-value { font-size:1.3rem; }
  }
</style>

<div class="content-body">
<div class="rpt-page">

    {{-- ───────────────────────── Header + filters ───────────────────────── --}}
    <div class="rpt-card">
        <div class="rpt-head">
            <div>
                <h2 class="rpt-title">Reports &amp; Analytics</h2>
                <p class="rpt-sub">Pick a report and filter it. Results update automatically, and exports use the same filters.</p>
            </div>
            <div class="rpt-actions">
                <a class="rpt-btn" href="{{ route('reports.exportPdf', $exportQuery) }}"><i class="fas fa-file-pdf"></i> Export PDF</a>
                <a class="rpt-btn rpt-btn-primary" href="{{ route('reports.exportExcel', $exportQuery) }}"><i class="fas fa-file-excel"></i> Export Excel</a>
            </div>
        </div>

        <form id="reportFilters" method="GET" action="{{ route('reports.index') }}" class="rpt-filters">
            <div class="rpt-field">
                <label for="report_type">Report type</label>
                <select name="report_type" id="report_type" onchange="this.form.submit()">
                    @foreach ($reportTitles as $value => $label)
                        <option value="{{ $value }}" @selected($reportType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="rpt-field">
                <label for="start_date">From</label>
                <input type="date" name="start_date" id="start_date" value="{{ $filters['start_date'] ?? '' }}"
                       @if (!empty($filters['end_date'])) max="{{ $filters['end_date'] }}" @endif onchange="this.form.submit()">
            </div>

            <div class="rpt-field">
                <label for="end_date">To</label>
                <input type="date" name="end_date" id="end_date" value="{{ $filters['end_date'] ?? '' }}"
                       @if (!empty($filters['start_date'])) min="{{ $filters['start_date'] }}" @endif onchange="this.form.submit()">
            </div>

            <div class="rpt-field">
                <label for="status">Loan status</label>
                <select name="status" id="status" onchange="this.form.submit()" @disabled($reportType === 'borrower_performance')
                        @if ($reportType === 'borrower_performance') title="Not used by this report" @endif>
                    <option value="">All statuses</option>
                    @foreach (['pending', 'approved', 'rejected', 'paid', 'flagged'] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="rpt-field">
                <label for="risk_level">Risk level</label>
                <select name="risk_level" id="risk_level" onchange="this.form.submit()">
                    <option value="">All levels</option>
                    @foreach (['Low', 'Medium', 'High'] as $level)
                        <option value="{{ $level }}" @selected(($filters['risk_level'] ?? '') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>

            @if ($hasFilters)
                <div class="rpt-field rpt-reset">
                    <a href="{{ route('reports.index', ['report_type' => $reportType]) }}"><i class="fas fa-rotate-left"></i> Reset filters</a>
                </div>
            @endif
        </form>

        <div class="rpt-chips">
            <span class="rpt-chip">{{ $reportTitles[$reportType] ?? 'Report' }}</span>
            @if (!empty($filters['start_date']) || !empty($filters['end_date']))
                <span class="rpt-chip">
                    {{ $fmtDate($filters['start_date'] ?? null) ?? 'Beginning' }} &ndash; {{ $fmtDate($filters['end_date'] ?? null) ?? 'Today' }}
                </span>
            @endif
            @if (!empty($filters['status']) && $reportType !== 'borrower_performance')
                <span class="rpt-chip">Status: {{ ucfirst($filters['status']) }}</span>
            @endif
            @if (!empty($filters['risk_level']))
                <span class="rpt-chip">Risk: {{ $filters['risk_level'] }}</span>
            @endif
        </div>
    </div>

    {{-- ───────────────────────── Key metrics (always overall, not filtered) ───────────────────────── --}}
    <div class="rpt-card">
        <div class="rpt-section-head">
            <h3 class="rpt-section-title">Key Metrics</h3>
            <span class="rpt-count">Overall figures, not affected by the filters above</span>
        </div>
        <div class="rpt-kpis">
            <div class="rpt-kpi">
                <div class="rpt-kpi-icon" style="background:rgba(5,150,105,.12); color:#059669;"><i class="fas fa-hand-holding-dollar"></i></div>
                <div>
                    <div class="rpt-kpi-label">Approved Loans</div>
                    <div class="rpt-kpi-value">{{ \App\Support\Format::money($totalApproved ?? 0) }}</div>
                    <div class="rpt-kpi-note">Approved loan amounts</div>
                </div>
            </div>
            <div class="rpt-kpi">
                <div class="rpt-kpi-icon" style="background:rgba(2,132,199,.12); color:#0284c7;"><i class="fas fa-money-bill-wave"></i></div>
                <div>
                    <div class="rpt-kpi-label">Total Repayments</div>
                    <div class="rpt-kpi-value">{{ \App\Support\Format::money($totalRepayments ?? 0) }}</div>
                    <div class="rpt-kpi-note">All recorded payments</div>
                </div>
            </div>
            <div class="rpt-kpi">
                <div class="rpt-kpi-icon" style="background:rgba(67,56,202,.12); color:#4338ca;"><i class="fas fa-users"></i></div>
                <div>
                    <div class="rpt-kpi-label">Active Members</div>
                    <div class="rpt-kpi-value">{{ number_format($activeMembersCount ?? 0) }}</div>
                    <div class="rpt-kpi-note">Members with active status</div>
                </div>
            </div>
            <div class="rpt-kpi">
                <div class="rpt-kpi-icon" style="background:rgba(217,119,6,.14); color:#d97706;"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="rpt-kpi-label">Pending Applications</div>
                    <div class="rpt-kpi-value">{{ number_format($pendingApplicationsCount ?? 0) }}</div>
                    <div class="rpt-kpi-note">Waiting for a decision</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ───────────────────────── Selected report ───────────────────────── --}}
    @if ($reportType === 'loan_summary')
        @php $summaryTotal = (int) ($loanSummary['total_count'] ?? 0); @endphp
        <div class="rpt-card">
            <div class="rpt-section-head">
                <h3 class="rpt-section-title">Loan Summary</h3>
                <span class="rpt-count">{{ number_format($summaryTotal) }} {{ \Illuminate\Support\Str::plural('application', $summaryTotal) }}</span>
            </div>

            @if ($summaryTotal === 0)
                <div class="rpt-empty">
                    <i class="fas fa-folder-open"></i>
                    No loan applications match these filters.
                    @if ($hasFilters) <a href="{{ route('reports.index', ['report_type' => $reportType]) }}">Reset filters</a> @endif
                </div>
            @else
                <div class="rpt-grid3">
                    <div class="rpt-subcard">
                        <h4 class="rpt-subtitle">By status</h4>
                        <div class="rpt-table-wrap">
                            <table class="rpt-table">
                                <thead><tr><th>Status</th><th class="num">Count</th><th class="num">Total amount</th></tr></thead>
                                <tbody>
                                    @foreach ($loanSummary['status_totals'] as $status => $total)
                                        <tr>
                                            <td>{{ ucfirst($status) }}</td>
                                            <td class="num">{{ $loanSummary['by_status_counts'][$status] ?? 0 }}</td>
                                            <td class="num">{{ \App\Support\Format::money($total) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Total</td>
                                        <td class="num">{{ $summaryTotal }}</td>
                                        <td class="num">{{ \App\Support\Format::money($loanSummary['total_amount']) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="rpt-subcard">
                        <h4 class="rpt-subtitle">By loan type</h4>
                        <div class="rpt-table-wrap">
                            <table class="rpt-table">
                                <thead><tr><th>Loan type</th><th class="num">Count</th><th class="num">Total amount</th></tr></thead>
                                <tbody>
                                    @forelse ($loanSummary['by_type'] as $type => $total)
                                        <tr>
                                            <td>{{ ucfirst(str_replace('_', ' ', $type)) }}</td>
                                            <td class="num">{{ $loanSummary['by_type_counts'][$type] ?? 0 }}</td>
                                            <td class="num">{{ \App\Support\Format::money($total) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="rpt-empty">No data</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rpt-subcard">
                        <h4 class="rpt-subtitle">Risk distribution</h4>
                        <div class="rpt-table-wrap">
                            <table class="rpt-table">
                                <thead><tr><th>Risk level</th><th class="num">Count</th><th class="num">% of total</th></tr></thead>
                                <tbody>
                                    @foreach (['Low', 'Medium', 'High'] as $level)
                                        @php $count = $loanSummary['risk_distribution'][$level] ?? 0; @endphp
                                        <tr>
                                            <td><span class="rpt-pill {{ $pill($level) }}">{{ $level }}</span></td>
                                            <td class="num">{{ $count }}</td>
                                            <td class="num">{{ $summaryTotal > 0 ? round($count / $summaryTotal * 100, 1) : 0 }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

    @elseif ($reportType === 'approved_records')
        <div class="rpt-card">
            <div class="rpt-section-head">
                <h3 class="rpt-section-title">Approved Loan Records</h3>
                <span class="rpt-count">{{ number_format($approvedRecs->count()) }} {{ \Illuminate\Support\Str::plural('record', $approvedRecs->count()) }}</span>
            </div>

            @if ($approvedRecs->isEmpty())
                <div class="rpt-empty">
                    <i class="fas fa-folder-open"></i>
                    No approved loans match these filters.
                    @if ($hasFilters) <a href="{{ route('reports.index', ['report_type' => $reportType]) }}">Reset filters</a> @endif
                </div>
            @else
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>App ID</th><th>Member</th><th>Loan type</th><th class="num">Approved amount</th>
                                <th class="ctr">Term (mo)</th><th>Risk</th><th class="num">Recommended</th><th>Approved date</th><th>Approved by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($approvedRecs as $row)
                                <tr>
                                    <td class="rpt-mono">{{ $row->app_id ?: '—' }}</td>
                                    <td>{{ $row->member_name }}</td>
                                    <td>{{ $row->loan_type_label }}</td>
                                    <td class="num">{{ $row->approved_amount }}</td>
                                    <td class="ctr">{{ $row->term_months }}</td>
                                    <td><span class="rpt-pill {{ $pill($row->risk_level) }}">{{ $row->risk_level ?: '—' }}</span></td>
                                    <td class="num">{{ $row->recommended_amt }}</td>
                                    <td>{{ $row->approved_date }}</td>
                                    <td>{{ $row->approved_by }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @elseif ($reportType === 'borrower_performance')
        <div class="rpt-card">
            <div class="rpt-section-head">
                <h3 class="rpt-section-title">Borrower Performance</h3>
                <span class="rpt-count">{{ number_format($borrowerPerf->count()) }} {{ \Illuminate\Support\Str::plural('borrower', $borrowerPerf->count()) }}</span>
            </div>

            @if ($borrowerPerf->isEmpty())
                <div class="rpt-empty">
                    <i class="fas fa-folder-open"></i>
                    No borrower data matches these filters.
                    @if ($hasFilters) <a href="{{ route('reports.index', ['report_type' => $reportType]) }}">Reset filters</a> @endif
                </div>
            @else
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Member</th><th class="ctr">Total loans</th><th class="num">Total paid</th><th class="num">Outstanding</th>
                                <th class="ctr">On-time</th><th class="ctr">Late</th><th class="ctr">Overdue</th><th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($borrowerPerf as $row)
                                <tr>
                                    <td>{{ $row->member_name }}</td>
                                    <td class="ctr">{{ $row->total_loans }}</td>
                                    <td class="num">{{ $row->total_paid }}</td>
                                    <td class="num">{{ $row->outstanding }}</td>
                                    <td class="ctr">{{ $row->on_time_count }}</td>
                                    <td class="ctr">{{ $row->late_count }}</td>
                                    <td class="ctr {{ ($row->overdue_count ?? 0) > 0 ? 'rpt-late' : '' }}">{{ $row->overdue_count }}</td>
                                    <td><span class="rpt-pill {{ $pill($row->performance_label) }}">{{ $row->performance_label }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @elseif ($reportType === 'overdue_payments')
        <div class="rpt-card">
            <div class="rpt-section-head">
                <h3 class="rpt-section-title">Overdue Payments</h3>
                <span class="rpt-count">{{ number_format($overduePayments->count()) }} overdue {{ \Illuminate\Support\Str::plural('loan', $overduePayments->count()) }}</span>
            </div>

            @if ($overduePayments->isEmpty())
                <div class="rpt-empty">
                    <i class="fas fa-circle-check"></i>
                    No overdue payments. Nothing is past its due date.
                    @if ($hasFilters) <a href="{{ route('reports.index', ['report_type' => $reportType]) }}">Reset filters</a> @endif
                </div>
            @else
                <div class="rpt-table-wrap">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>App ID</th><th>Member</th><th>Loan type</th><th class="num">Overdue amount</th><th>Due date</th><th class="ctr">Days overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overduePayments as $row)
                                <tr>
                                    <td class="rpt-mono">{{ $row->app_id ?: '—' }}</td>
                                    <td>{{ $row->member_name }}</td>
                                    <td>{{ $row->loan_type_label }}</td>
                                    <td class="num">{{ $row->amount }}</td>
                                    <td>{{ $row->due_date }}</td>
                                    <td class="ctr"><span class="rpt-pill {{ $row->overdue_days > 30 ? 'bad' : 'warn' }}">{{ $row->overdue_days }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

</div>
</div>
@endsection