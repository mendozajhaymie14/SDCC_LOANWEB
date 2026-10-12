<script>
  // ─── Navbar shadow on scroll ───
  const nav = document.getElementById('navbar');
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 20);
  });

  document.addEventListener('DOMContentLoaded', function () {

    // ─── Sidebar ───
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const openBtn  = document.getElementById('sidebarOpenBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    if (!sidebar || !overlay || !openBtn) return;

    function openSidebar() {
      sidebar.classList.add('open');
      overlay.classList.add('open');
      sidebar.setAttribute('aria-hidden', 'false');
      openBtn.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
      if (closeBtn) closeBtn.focus();
    }

    function closeSidebar() {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
      sidebar.setAttribute('aria-hidden', 'true');
      openBtn.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    openBtn.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeSidebar));

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
    });

    // ─── Notification dropdown ───
    const notifBtn = document.getElementById('notifToggleBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    if (notifBtn && notifDropdown) {
      notifBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        notifDropdown.classList.toggle('open');
        notifBtn.setAttribute('aria-expanded', notifDropdown.classList.contains('open'));
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function (e) {
        if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
          notifDropdown.classList.remove('open');
          notifBtn.setAttribute('aria-expanded', 'false');
        }
      });

      // Handle mark-as-read on notification click
      notifDropdown.querySelectorAll('.notif-item').forEach(item => {
        item.addEventListener('click', function () {
          const notifId = this.dataset.notificationId;
          const notifType = this.dataset.type;
          markAsRead(notifId);
        });
      });
    }

    function markAsRead(notifId) {
      fetch('/user/notifications/' + notifId + '/read', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
      }).then(response => response.json())
        .then(data => {
          if (data.success) {
            // Remove the notification from the dropdown
            const item = document.querySelector('[data-notification-id="' + notifId + '"]');
            if (item) item.remove();
            // Update badge count
            const badge = notifBtn.querySelector('.notif-badge');
            if (badge) {
              const count = parseInt(badge.textContent) - 1;
              if (count > 0) {
                badge.textContent = count;
              } else {
                badge.remove();
              }
            }
            // If no more notifications, show empty state
            if (!document.querySelector('.notif-item')) {
              const itemsContainer = document.querySelector('.notif-items');
              if (itemsContainer) {
                itemsContainer.innerHTML = '<div style="padding: 1.2rem; text-align: center; color: var(--muted);">No active reminders.</div>';
              }
            }
          }
        });
    }
  });
</script>