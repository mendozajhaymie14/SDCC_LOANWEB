@extends('admin.index')

@section('admin_content')
<div class="content-body">
    <div class="panel app-panel">
        <div class="page-header">
            <div>
                <h2 class="panel-title">Reports & Analytics</h2>
            </div>
            <div class="page-actions">
                <a href="{{ route('reports.exportPdf') }}" class="btn-new" style="text-decoration:none;">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="{{ route('reports.exportExcel') }}" class="btn-new" style="text-decoration:none;">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>
        </div>
    </div>

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

    <div class="panel">
        <div class="card-header">
            <div class="card-title">Note</div>
        </div>
        <div class="card-table-responsive">
            <p style="color: #6b7280; text-align: center; font-style: italic;">
                This is a basic reports page. Detailed reporting features can be added as needed.
            </p>
        </div>
    </div>
</div>
@endsection