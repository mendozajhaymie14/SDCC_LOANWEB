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

<script>
  (function () {
    var flash = document.querySelector('.flash');
    if (!flash) return;

    // On mobile, float the message above the keyboard area and auto-dismiss.
    if (window.innerWidth <= 760) {
      flash.classList.add('flash-floating');
      flash.style.display = 'flex';
      setTimeout(function () {
        flash.style.transition = 'opacity 0.3s, transform 0.3s';
        flash.style.opacity = '0';
        flash.style.transform = 'translateX(-50%) translateY(20px)';
        setTimeout(function () { flash.remove(); }, 300);
      }, 5000);
    }
  })();
</script>