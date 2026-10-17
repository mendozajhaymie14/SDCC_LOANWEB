@extends('admin.index')

@section('admin_content')
<div class="content-body">
  <div class="panel app-panel">
    <div class="page-header">
      <div>
        <h2 class="panel-title">Activity Log</h2>
      </div>
      <div class="page-actions">
        <!-- Single-row search & filter toolbar -->
        <form method="GET" action="{{ url('/admin/audit-logs') }}" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; width: 100%; margin-top: 0.5rem;">
            <!-- Left: Search Bar -->
            <div style="flex: 1; min-width: 260px; max-width: 380px; position: relative;">
                <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem;">🔍</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search actions, users, or details..." style="width: 100%; padding: 0.45rem 0.75rem 0.45rem 2.2rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; color: #374151; outline: none; background: #fff;">
            </div>

            <!-- Right: Date Range Pickers + Apply Button -->
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <span style="font-size: 0.8rem; font-weight: 500; color: #6b7280;">Date:</span>
                <input type="date" name="start_date" value="{{ request('start_date') }}" style="padding: 0.4rem 0.6rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.825rem; color: #374151; background: #fff;">
                <span style="font-size: 0.8rem; color: #9ca3af;">to</span>
                <input type="date" name="end_date" value="{{ request('end_date') }}" style="padding: 0.4rem 0.6rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.825rem; color: #374151; background: #fff;">
                
                <button type="submit" style="background-color: #2563eb; color: #ffffff; padding: 0.45rem 1rem; border: none; border-radius: 8px; font-size: 0.825rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                    Apply Filters
                </button>
            </div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="card-header">
        <div class="card-title">Activity Log Table</div>
        <div class="card-table-responsive">
          <table class="min-w-full divide divide-gray-200">
            <thead>
              <tr>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b font-mono">Date</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">User</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Action</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Details</th>
                <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">IP Address</th>
              </tr>
            </thead>
            <tbody id="auditLogsTable">
              @forelse($auditLogs as $log)
                  <tr class="hover:bg-gray-50 transition-colors {{ $loop->odd ? 'bg-white' : 'bg-gray-50' }}">
                    <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y H:i') }}</td>
                    <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $log->admin_name ?? 'System' }}</td>
                    <td class="py-3 px-4 font-medium text-gray-600 text-sm">
                        @php
                            $badgeStyle = 'background-color: #f3f4f6; color: #374151;';
                            if (str_contains(strtolower($log->action), 'approved')) {
                                $badgeStyle = 'background-color: #dcfce7; color: #15803d;';
                            } elseif (str_contains(strtolower($log->action), 'risk') || str_contains(strtolower($log->action), 'override')) {
                                $badgeStyle = 'background-color: #fef3c7; color: #b45309;';
                            } elseif (str_contains(strtolower($log->action), 'login') || str_contains(strtolower($log->action), 'repayment')) {
                                $badgeStyle = 'background-color: #dbeafe; color: #1d4ed8;';
                            }
                        @endphp
                        <span style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; inline-block; {{ $badgeStyle }}">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td class="py-3 px-4 font-small text-gray-500 text-xs">{{ Str::limit($log->details ?? '-', 100) }}</td>
                    <td class="py-3 px-4 font-small text-gray-500 text-xs">{{ $log->ip_address ?? 'N/A' }}</td>
                  </tr>
              @empty
                <tr>
                  <td colspan="5" class="py-6 text-center text-gray-400">No activity logs found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="text-center py-8">
        <div class="inline-block px-4 py-2 bg-blue-50 text-blue-800 text-sm font-medium rounded-border border border-blue-200 mb-2">
          <i class="fas fa-info-circle mr-2"></i> Logs are generated automatically for sensitive actions (status updates, payment creation, member modifications).
        </div>
        <small class="text-gray-500">Log retention: 90 days minimum. For export/backup queries, contact system administrator.</small>
      </div>
    </div>
  </div>
</div>
@endsection

<script>
function filterLogs(term) {
  const termLower = term.trim().toLowerCase();
  const rows = document.querySelectorAll('#auditLogsTable tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = termLower ? (text.includes(termLower) ? '' : 'none') : '';
  });
}

// Simple date range picker
document.getElementById('dateRangeInput').addEventListener('focus', function() {
  const today = new Date();
  const from = new Date(today);
  from.setMonth(today.getMonth() - 3);
  const to = new Date(today);
  const format = 'YYYY-MM-DD';
  const start = formatDate(from);
  const end = formatDate(to);
  this.value = `${start} to ${end}`;
});

function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
</script>
