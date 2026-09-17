<!DOCTYPE html>
<html lang="en">
<head>
  @include('home.css')
</head>
<body>

  <header>
    @include('home.header')
  </header>

  @include('home.sidebar')

  @include('home.body')

  @include('home.footer')

  <script>
    // ─── Navbar shadow on scroll ───
    const nav = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 20);
    });

    // ─── Fade-in cards on scroll ───
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((e, i) => {
        if (e.isIntersecting) {
          e.target.style.animation = `fadeSlideUp 0.6s ${i * 0.08}s ease both`;
          observer.unobserve(e.target);
        }
      });
    }, { threshold: 0.1 });

    document.querySelectorAll('.feature-card, .step, .stat-card').forEach(el => {
      el.style.opacity = '0';
      observer.observe(el);
    });

    document.addEventListener('DOMContentLoaded', function () {

      // ─── Profile dropdown ───
      const profileBtn   = document.getElementById('profileDropdownBtn');
      const dropdownMenu = document.getElementById('profileDropdownMenu');

      if (profileBtn && dropdownMenu) {
        profileBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          dropdownMenu.classList.toggle('show');
        });
        document.addEventListener('click', function (e) {
          if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
          }
        });
      }

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

      // Close after tapping a link, so the anchor scroll is visible
      sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeSidebar);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
      });
    });

     // ─── Navbar shadow on scroll ───
    const nav = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 20);
    });

    // ─── FAQ Search Filter ───
    document.addEventListener('DOMContentLoaded', function () {
      const searchInput = document.getElementById('faqSearch');
      const faqItems = document.querySelectorAll('.faq-list details');
      const catButtons = document.querySelectorAll('.faq-cat-btn');
      let activeCategory = 'all';

      function filterFAQs() {
        const query = searchInput.value.toLowerCase().trim();
        faqItems.forEach(item => {
          const category = item.dataset.category;
          const text = item.textContent.toLowerCase();
          const matchesSearch = query === '' || text.includes(query);
          const matchesCategory = activeCategory === 'all' || category === activeCategory;
          item.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
        });
      }

      searchInput.addEventListener('input', filterFAQs);

      catButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          catButtons.forEach(b => {
            b.classList.remove('active');
            b.style.background = '';
            b.style.borderColor = '';
            b.style.color = '';
          });
          btn.classList.add('active');
          activeCategory = btn.dataset.cat;
          filterFAQs();
        });
      });

      // ─── Profile dropdown ───
      const profileBtn   = document.getElementById('profileDropdownBtn');
      const dropdownMenu = document.getElementById('profileDropdownMenu');
      if (profileBtn && dropdownMenu) {
        profileBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          dropdownMenu.classList.toggle('show');
        });
        document.addEventListener('click', function (e) {
          if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.remove('show');
          }
        });
      }

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
      sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeSidebar);
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
      });
    });
  </script>

</body>
</html>