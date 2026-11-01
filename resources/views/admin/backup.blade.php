@extends('admin.index')

@section('admin_content')
<div class="content-body">
  <div class="panel app-panel">
    <div class="page-header">
      <div>
        <h2 class="panel-title">Database Backup</h2>
        <span class="records-count">Stored backups and manual backup tool</span>
      </div>
    </div>

    {{-- Flash messages --}}
    @if (session('status'))
      <div class="alert alert-success" style="background: #d1f0c3; border: 1px solid #84cc16; color: #22540c; padding: 0.75rem 1.25rem; border-radius: 8px; margin-bottom: 1rem;">
        {{ session('status') }}
      </div>
    @endif
    @if (session('error'))
      <div class="alert alert-error" style="background: #fecaca; border: 1px solid #fca5a5; color: #991b1b; padding: 0.75rem 1.25rem; border-radius: 8px; margin-bottom: 1rem;">
        {{ session('error') }}
      </div>
    @endif

    {{-- Backup file list --}}
    @if (!empty($backups))
      <div style="margin-bottom: 2rem;">
        <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Stored Backups</h3>
        <table class="min-w-full divide divide-gray-200">
          <thead>
            <tr>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b font-mono">Filename</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Size</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Date</th>
              <th class="py-3 px-4 text-gray-600 text-sm font-semibold border-b">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($backups as $backup)
              <tr class="hover:bg-gray-50 transition-colors {{ $loop->odd ? 'bg-white' : 'bg-gray-50' }}">
                <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $backup['filename'] }}</td>
                <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $backup['size'] }}</td>
                <td class="py-3 px-4 font-medium text-gray-600 text-sm">{{ $backup['date'] }}</td>
                <td class="py-3 px-4 font-medium text-gray-600 text-sm">
                  <form method="POST" action="{{ route('admin.backup.restore') }}" style="display: inline;"
                      onsubmit="return confirm('Are you sure you want to restore this backup? This will overwrite the current database. Type RESTORE to confirm.');">
                    @csrf
                    <input type="hidden" name="filename" value="{{ $backup['filename'] }}">
                    <button type="submit"
                        style="background: #f87171; color: #ffffff; padding: 0.35rem 0.75rem; border: none; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                      Restore
                    </button>
                  </form>
                  <a href="{{ route('admin.backup.download', $backup['filename']) }}"
                     style="margin-left: 0.5rem; color: #2b6cb0; text-decoration: none; font-size: 0.75rem;">
                    Download
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div style="padding: 2rem; text-align: center; color: #6b7280;">
        <p>No backup files found.</p>
        <p class="text-sm mt-2">Backups are stored in <code>storage/app/backups/</code>.</p>
      </div>
    @endif

    {{-- Manual backup form --}}
    <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e2e8f0;">
      <h3 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; color: #1e293b;">Manual Backup</h3>
      <p style="font-size: 0.875rem; color: #4a5568; margin-bottom: 1rem;">
        Creates a gzip SQL dump of the current database. Backups are kept for 14 days.
      </p>
      <form method="POST" action="{{ route('admin.backup') }}" style="display: inline;">
        @csrf
        <button type="submit"
            style="background-color: #2563eb; color: #ffffff; padding: 0.6rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; font-size: 0.9rem;">
          Back up now
        </button>
      </form>
    </div>
  </div>
</div>
@endsection