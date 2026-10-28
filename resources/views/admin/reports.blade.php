@extends('admin.index')

@section('admin_content')
<div class="content-body">
    <div class="panel app-panel">
        <div class="page-header">
            <div>
                <h2 class="panel-title">Reports & Analytics</h2>
            </div>
            <div class="page-actions">
                <form id="reportFilters" method="GET" action="{{ route('reports.index') }}" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <div class="filter-row">
                        <label class="filter-label" for="report_type">Type:</label>
                        <select name="report_type" id="report_type" class="filter-select" onchange="this.form.submit()">
                            <option value="loan_summary" {{ $filters['report_type'] == 'loan_summary' ? 'selected' : '' }}>Loan Summary</option>
                            <option value="approved_records" {{ $filters['report_type'] == 'approved_records' ? 'selected' : '' }}>Approved Records</option>
                            <option value="borrower_performance" {{ $filters['report_type'] == 'borrower_performance' ? 'selected' : '' }}>Borrower Performance</option>
                        </select>
                    </div>

                    <div class="filter-row">
                        <label class="filter-label" for="start_date">From:</label>
                        <input type="date" name="start_date" id="start_date" class="filter-input" value="{{ old('start_date', $filters['start_date'] ?? '') }}" onchange="this.form.submit()">
                    </div>

                    <div class="filter-row">
                        <label class="filter-label" for="end_date">To:</label>
                        <input type="date" name="end_date" id="end_date" class="filter-input" value="{{ old('end_date', $filters['end_date'] ?? '') }}" onchange="this.form.submit()">
                    </div>

                    <div class="filter-row">
                        <label class="filter-label" for="status">Status:</label>
                        <select name="status" id="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach(['pending', 'approved', 'disbursed', 'rejected', 'paid', 'flagged'] as $status)
                                <option value="{{ $status }}" {{ $filters['status'] == $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($filters['report_type'] !== 'borrower_performance')
                        <button type="button" class="btn btn-secondary" onclick="window.location='{{ route('reports.exportPdf') }}?report_type=' + $('#report_type').val() + '&start_date=' + $('#start_date').val() + '&end_date=' + $('#end_date').val() + '&status=' + $('#status').val()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location='{{ route('reports.exportExcel') }}?report_type=' + $('#report_type').val() + '&start_date=' + $('#start_date').val() + '&end_date=' + $('#end_date').val() + '&status=' + $('#status').val()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary" onclick="window.location='{{ route('reports.exportPdf') }}?report_type=' + $('#report_type').val() + '&start_date=' + $('#start_date').val() + '&end_date=' + $('#end_date').val()'">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location='{{ route('reports.exportExcel') }}?report_type=' + $('#report_type').val() + '&start_date=' + $('#start_date').val() + '&end_date=' + $('#end_date').val()'">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <div class="content-body">
    <div class="panel">
        <div class="card-header">
            <div class="card-title">Key Metrics</div>
        </div>
        <div class="card-table-responsive">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; width: 100%;">
                <!-- Total Disbursed -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center;">
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">Total Disbursed</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #10b981;">₱{{ number_format($totalDisbursed ?? 0, 2) }}</div>
                </div>
                <!-- Total Repayments -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center;">
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">Total Repayments</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #3b82f6;">₱{{ number_format($totalRepayments ?? 0, 2) }}</div>
                </div>
                <!-- Active Members -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center;">
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">Active Members</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #8b5cf6;">{{ $activeMembersCount ?? 0 }}</div>
                </div>
                <!-- Pending Applications -->
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; text-align: center;">
                    <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">Pending Applications</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #f59e0b;">{{ $pendingApplicationsCount ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($loanSummary)
        <div class="panel">
            <div class="card-header">
                <div class="card-title">Loan Summary</div>
            </div>
            <div class="card-table-responsive">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; width: 100%;">
                    <!-- By Status -->
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">By Status</div>
                        <table style="width: 100%; font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loanSummary['status_totals'] as $status => $total)
                                    <tr>
                                        <td>{{ ucfirst($status) }}</td>
                                        <td>{{ $loanSummary['by_status_counts'][$status] ?? 0 }}</td>
                                        <td class="text-right">{{ \App\Support\Format::money($total) }}</td>
                                    </tr>
                                @endforeach
                                <tr style="font-weight: bold; background-color: #f0f4f8;">
                                    <td>Total</td>
                                    <td>{{ $loanSummary['total_count'] }}</td>
                                    <td class="text-right">{{ \App\Support\Format::money($loanSummary['total_amount']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- By Type -->
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">By Loan Type</div>
                        <table style="width: 100%; font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>Loan Type</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loanSummary['by_type'] as $type => $total)
                                    <tr>
                                        <td>{{ ucfirst($type) }}</td>
                                        <td>{{ $loanSummary['by_type_counts'][$type] ?? 0 }}</td>
                                        <td class="text-right">{{ \App\Support\Format::money($total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Risk Distribution -->
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                        <div style="font-size: 0.85rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">Risk Distribution</div>
                        <table style="width: 100%; font-size: 11px;">
                            <thead>
                                <tr>
                                    <th>Risk Level</th>
                                    <th>Count</th>
                                    <th>% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(['Low', 'Medium', 'High'] as $level)
                                    @php $count = $loanSummary['risk_distribution'][$level] ?? 0; @endphp
                                    <tr>
                                        <td><span class="risk-{{ strtolower($level) }}">{{ $level }}</span></td>
                                        <td>{{ $count }}</td>
                                        <td>{{ $loanSummary['total_count'] > 0 ? round($count / $loanSummary['total_count'] * 100, 1) : 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($approvedRecs->isNotEmpty())
        <div class="panel">
            <div class="card-header">
                <div class="card-title">Approved Loan Records</div>
            </div>
            <div class="card-table-responsive">
                <table style="width: 100%; font-size: 11px; margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>App ID</th>
                            <th>Member</th>
                            <th>Loan Type</th>
                            <th>Approved Amount</th>
                            <th>Term (mo)</th>
                            <th>Risk</th>
                            <th>Recommended</th>
                            <th>Approved Date</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($approvedRecs as $row)
                            <tr>
                                <td>{{ $row->app_id }}</td>
                                <td>{{ $row->member_name }}</td>
                                <td>{{ $row->loan_type_label }}</td>
                                <td class="text-right">{{ $row->approved_amount }}</td>
                                <td class="text-center">{{ $row->term_months }}</td>
                                <td><span class="risk-{{ strtolower($row->risk_level) }}">{{ $row->risk_level }}</span></td>
                                <td class="text-right">{{ $row->recommended_amt }}</td>
                                <td>{{ $row->approved_date }}</td>
                                <td>{{ $row->approved_by }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($borrowerPerf->isNotEmpty())
        <div class="panel">
            <div class="card-header">
                <div class="card-title">Borrower Performance</div>
            </div>
            <div class="card-table-responsive">
                <table style="width: 100%; font-size: 11px; margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Total Loans</th>
                            <th>Total Paid</th>
                            <th>Outstanding</th>
                            <th>On-time</th>
                            <th>Late</th>
                            <th>Overdue</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($borrowerPerf as $row)
                            <tr>
                                <td>{{ $row->member_name }}</td>
                                <td class="text-center">{{ $row->total_loans }}</td>
                                <td class="text-right">{{ $row->total_paid }}</td>
                                <td class="text-right">{{ $row->outstanding }}</td>
                                <td class="text-center">{{ $row->on_time_count }}</td>
                                <td class="text-center">{{ $row->late_count }}</td>
                                <td class="text-center">{{ $row->overdue_count }}</td>
                                <td><span class="risk-{{ strtolower($row->performance_label) }}">{{ $row->performance_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection