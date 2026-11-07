{{--
  Logout confirmation popup, shared by the member, home and admin layouts.

  Include this file once per page layout:   @include('partials.logout-confirm')
  (@once makes a second include on the same page harmless.)

  - Any <form action=".../logout"> shows the popup before it is submitted.
  - Admin pages can call  sdccConfirmLogout(formElement)  directly.
--}}
@once
<style>
  .sdcc-lo-overlay {
    position: fixed; inset: 0; z-index: 99999;
    display: none; align-items: center; justify-content: center;
    padding: 16px; background: rgba(15, 23, 42, .55);
    -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px);
  }
  .sdcc-lo-overlay.is-open { display: flex; animation: sdccLoFade .15s ease-out; }
  .sdcc-lo-card {
    width: 100%; max-width: 380px; background: #fff; color: #1e293b;
    border-radius: 18px; padding: 28px 24px 22px; text-align: center;
    box-shadow: 0 20px 50px rgba(0, 0, 0, .25);
    font-family: inherit; animation: sdccLoPop .18s ease-out;
  }
  .sdcc-lo-icon {
    width: 56px; height: 56px; margin: 0 auto 14px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #fee2e2; color: #dc2626;
  }
  .sdcc-lo-title { margin: 0 0 6px; font-size: 1.2rem; font-weight: 700; }
  .sdcc-lo-text  { margin: 0 0 20px; font-size: .95rem; color: #64748b; line-height: 1.5; }
  .sdcc-lo-actions { display: flex; gap: 10px; }
  .sdcc-lo-btn {
    flex: 1; padding: 11px 14px; border-radius: 10px; font-size: .95rem;
    font-weight: 600; font-family: inherit; cursor: pointer; border: 1px solid transparent;
  }
  .sdcc-lo-btn:focus-visible { outline: 3px solid #93c5fd; outline-offset: 2px; }
  .sdcc-lo-cancel { background: #fff; color: #334155; border-color: #cbd5e1; }
  .sdcc-lo-cancel:hover { background: #f1f5f9; }
  .sdcc-lo-confirm { background: #dc2626; color: #fff; }
  .sdcc-lo-confirm:hover { background: #b91c1c; }
  .sdcc-lo-confirm[disabled] { opacity: .7; cursor: wait; }
  @keyframes sdccLoFade { from { opacity: 0; } to { opacity: 1; } }
  @keyframes sdccLoPop  { from { opacity: 0; transform: translateY(8px) scale(.97); } to { opacity: 1; transform: none; } }
</style>

<div class="sdcc-lo-overlay" id="sdccLogoutOverlay" aria-hidden="true">
  <div class="sdcc-lo-card" role="dialog" aria-modal="true" aria-labelledby="sdccLogoutTitle" aria-describedby="sdccLogoutText">
    <div class="sdcc-lo-icon" aria-hidden="true">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
    </div>
    <h3 class="sdcc-lo-title" id="sdccLogoutTitle">Log out?</h3>
    <p class="sdcc-lo-text" id="sdccLogoutText">Are you sure you want to log out?</p>
    <div class="sdcc-lo-actions">
      <button type="button" class="sdcc-lo-btn sdcc-lo-cancel" id="sdccLogoutCancel">Cancel</button>
      <button type="button" class="sdcc-lo-btn sdcc-lo-confirm" id="sdccLogoutConfirm">Yes, log out</button>
    </div>
  </div>
</div>

<script>
(function () {
  if (window.__sdccLogoutConfirmLoaded) return;
  window.__sdccLogoutConfirmLoaded = true;

  var pendingForm = null;
  var lastFocus = null;

  function el(id) { return document.getElementById(id); }

  function openPopup(form) {
    var overlay = el('sdccLogoutOverlay');
    if (!overlay) { form.submit(); return; }       // never trap the user if markup is missing
    pendingForm = form;
    lastFocus = document.activeElement;
    var confirmBtn = el('sdccLogoutConfirm');
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Yes, log out';
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    el('sdccLogoutCancel').focus();
  }

  function closePopup() {
    var overlay = el('sdccLogoutOverlay');
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    pendingForm = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function confirmLogout() {
    if (!pendingForm) return;
    var btn = el('sdccLogoutConfirm');
    btn.disabled = true;
    btn.textContent = 'Logging out...';
    pendingForm.submit();                           // submit() does not re-trigger the 'submit' event
  }

  // Public helper (used by the admin "Log Out" menu item)
  window.sdccConfirmLogout = openPopup;

  // Intercept every logout form on the page (member sidebar, header, home page...)
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    var action = (form.getAttribute('action') || '').split('?')[0];
    if (!/\/logout\/?$/.test(action)) return;
    e.preventDefault();
    e.stopPropagation();
    openPopup(form);
  }, true);

  document.addEventListener('DOMContentLoaded', function () {
    var overlay = el('sdccLogoutOverlay');
    if (!overlay) return;
    el('sdccLogoutCancel').addEventListener('click', closePopup);
    el('sdccLogoutConfirm').addEventListener('click', confirmLogout);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closePopup(); });
  });

  document.addEventListener('keydown', function (e) {
    var overlay = el('sdccLogoutOverlay');
    if (!overlay || !overlay.classList.contains('is-open')) return;
    if (e.key === 'Escape') { closePopup(); return; }
    if (e.key === 'Tab') {                          // keep focus inside the popup
      var cancel = el('sdccLogoutCancel'), ok = el('sdccLogoutConfirm');
      if (e.shiftKey && document.activeElement === cancel) { e.preventDefault(); ok.focus(); }
      else if (!e.shiftKey && document.activeElement === ok) { e.preventDefault(); cancel.focus(); }
    }
  });
})();
</script>
@endonce