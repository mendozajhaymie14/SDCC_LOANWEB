<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  @include('admin.css')
</head>
<body>

<!-- SIDEBAR -->
@include('admin.sidebar')

<!-- MAIN WRAPPER -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title" id="topbarTitle">Admin Users <span>/ System</span></div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchAdminInput" placeholder="Search admins…" />
      </div>
    </div>
    <div class="topbar-actions">
      <div class="icon-btn" id="notifBtn" onclick="toggleNotif()" data-tip="Notifications">
        🔔<span class="notif-dot" id="notifDot"></span>
      </div>
      <div class="icon-btn" id="settingsBtn" onclick="toggleSettingsDropdown()" data-tip="Settings">⚙️</div>
    </div>
  </div>

  <div class="notif-dropdown" id="notifDropdown">
    <div class="notif-head">
      <div class="notif-head-title">Notifications</div>
      <div class="notif-mark-all" onclick="markAllRead()">Mark all read</div>
    </div>
  </div>

  <!-- PAGE CONTENT -->
  <div class="content-body">
    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Admin Users</h2>
          <span class="records-count">Total Records: {{ $admins->count() }}</span>
        </div>
      </div>

      <div class="table-wrap">
        <table class="app-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>NAME</th>
              <th>EMAIL</th>
              <th>PHONE</th>
              <th>JOINED DATE</th>
            </tr>
          </thead>
          <tbody>
            @forelse($admins as $admin)
              <tr>
                <td class="td-mono">#{{ $admin->id }}</td>
                <td class="applicant-name">{{ $admin->name }}</td>
                <td class="td-mono">{{ $admin->email }}</td>
                <td>{{ $admin->phone ?? 'N/A' }}</td>
                <td class="td-date">{{ $admin->created_at?->format('M d, Y') ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="empty-table-msg">No admin accounts found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  @include('admin.footer')
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

</body>
</html>