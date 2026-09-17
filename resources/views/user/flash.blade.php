@if (session('success'))
  <div class="flash flash-success" role="status">
    <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
  </div>
@endif

@if (session('error'))
  <div class="flash flash-error" role="alert">
    <i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}
  </div>
@endif