<footer id="contact">
  <div class="footer-container">
    
    <!-- Column 1: Logo, Tagline & Social Icons -->
    <div class="footer-col footer-col-brand">
      <img src="{{ asset('images/Logo.jpg') }}" alt="SDCC Logo" class="footer-logo">
      <p class="footer-tagline">
        Pursuing and leading for a sustainable total cooperative system towards the dignity of human life.
      </p>
      <div class="footer-socials">
  <a href="#" class="social-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
  <a href="#" class="social-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
  <a href="#" class="social-btn" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
  <a href="#" class="social-btn" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
</div>
    </div>

    <!-- Column 2: Contact Us -->
    <div class="footer-col">
      <h3 class="footer-title">CONTACT US</h3>
      <p>0554 Quirino Avenue, San Dionisio</p>
      <p>Parañaque City 1700</p>
      <p class="footer-spacer">8826-1055 | 8820-2402</p>
      <p><a href="mailto:info@sandionisocredit.coop" class="footer-email">info@sandionisocredit.coop</a></p>
    </div>

    <!-- Column 3: Help Links -->
    <div class="footer-col">
      <h3 class="footer-title">HELP</h3>
      <ul class="footer-links">
        <li><a href="{{ route('faqs') }}">FAQs</a></li>
        <li><a href="{{ url('/contact') }}">Contact</a></li>
        <li><a href="{{ url('/feedback') }}">Feedback</a></li>
      </ul>
    </div>

  </div>

  <!-- Dark Green Bottom Copyright Bar -->
  <div class="footer-copyright">
    &copy; 2026 SAN DIONISIO CREDIT COOPERATIVE &middot; PARAÑAQUE CITY
  </div>
</footer>