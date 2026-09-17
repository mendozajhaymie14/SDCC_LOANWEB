<meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>San Dionisio Credit Cooperative – Member AI Loan Marketplace</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green-deep:  #1a5c2a;
      --green-mid:   #2e8b3e;
      --green-light: #5dbf6e;
      --green-pale:  #b8efc2;
      --green-wash:  #e8f9eb;
      --gold:        #d4af37;
      --gold-light:  #f5e27a;
      --white:       #ffffff;
      --off-white:   #f4faf5;
      --text-dark:   #0d2b12;
      --text-mid:    #2d5a35;
    }

    html { scroll-behavior: smooth; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--off-white);
      color: var(--text-dark);
      overflow-x: hidden;
    }

    /* ─── NAVBAR ─── */
    nav {
      position: fixed;
      top: 0; left: 0; right: 0;
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 48px;
      height: 80px;
      background: rgba(255,255,255,0.88);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(46,139,62,0.12);
      transition: box-shadow 0.3s;
    }
    nav.scrolled { box-shadow: 0 4px 32px rgba(26,92,42,0.12); }

    .nav-logo {
      display: flex;
      align-items: center;
      gap: 14px;
      text-decoration: none;
    }
    .logo-img {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 2px 12px rgba(212,175,55,0.35);
  }
    .nav-name {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 20px;
      letter-spacing: 1.5px;
      color: var(--green-deep);
      line-height: 1.15;
    }

    .nav-links {
      display: flex;
      gap: 40px;
      list-style: none;
    }
    .nav-links a {
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      text-decoration: none;
      color: var(--text-mid);
      position: relative;
      padding-bottom: 4px;
      transition: color 0.2s;
    }
    .nav-links a::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 2px;
      background: var(--green-mid);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.25s ease;
    }
    .nav-links a:hover { color: var(--green-deep); }
    .nav-links a:hover::after { transform: scaleX(1); }

    .btn-login {
      display: inline-block;
      text-decoration: none;
      padding: 10px 28px;
      border-radius: 100px;
      border: 2px solid var(--green-mid);
      background: transparent;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.5px;
      color: var(--green-deep);
      cursor: pointer;
      transition: background 0.22s, color 0.22s, transform 0.15s;
    }
    .btn-login:hover {
      background: var(--green-mid);
      color: var(--white);
      transform: translateY(-2px);
    }

    .nav-auth {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn-register {
      padding: 10px 28px;
      border-radius: 100px;
      border: 2px solid var(--green-mid);
      background: var(--green-mid);
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.5px;
      color: var(--white);
      cursor: pointer;
      transition: background 0.22s, transform 0.15s;
    }
    .btn-register:hover {
      background: var(--green-deep);
      border-color: var(--green-deep);
      transform: translateY(-2px);
    }

    /* ─── SLIDER ─── */
    .slider {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: center;
      overflow: hidden;
    }

    .slider-bg {
      position: absolute;
      inset: 0;
      background:
        linear-gradient(135deg, rgba(26,92,42,0.82) 0%, rgba(46,139,62,0.65) 55%, rgba(93,191,110,0.45) 100%);
      z-index: 1;
    }

    .slider-photo {
      position: absolute;
      inset: 0;
      background: url("{{ asset('images/Background.jpg') }}") center/cover no-repeat;
      z-index: 0;
    }

    .slider-stripe {
      position: absolute;
      right: -60px;
      top: -40px;
      width: 520px;
      height: 110%;
      background: linear-gradient(160deg, rgba(212,175,55,0.18) 0%, rgba(212,175,55,0.04) 100%);
      transform: skewX(-8deg);
      z-index: 2;
      pointer-events: none;
    }

    .dots {
      position: absolute;
      z-index: 2;
      pointer-events: none;
    }
    .dots-tl { top: 120px; left: 60px; opacity: 0.25; }
    .dots-br { bottom: 80px; right: 80px; opacity: 0.2; }
    .dot-grid {
      display: grid;
      grid-template-columns: repeat(6, 10px);
      gap: 10px;
    }
    .dot-grid span {
      display: block;
      width: 5px; height: 5px;
      border-radius: 50%;
      background: var(--gold-light);
    }

    .slider-content {
      position: relative;
      z-index: 3;
      max-width: 1200px;
      margin: 0 auto;
      padding: 120px 48px 80px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      width: 100%;
    }

    .slider-left { max-width: 560px; }

    .slider-tag {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(212,175,55,0.22);
      border: 1px solid rgba(212,175,55,0.5);
      border-radius: 100px;
      padding: 6px 16px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: var(--gold-light);
      margin-bottom: 28px;
      animation: fadeSlideUp 0.7s ease both;
    }
    .slider-tag-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--gold);
      animation: pulse 2s infinite;
    }

    .slider-title {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(52px, 7vw, 88px);
      line-height: 0.95;
      letter-spacing: 1px;
      color: var(--white);
      margin-bottom: 28px;
      animation: fadeSlideUp 0.7s 0.1s ease both;
    }
    .slider-title em {
      font-style: normal;
      color: var(--gold-light);
      display: block;
    }

    .slider-sub {
      font-size: 17px;
      font-weight: 300;
      line-height: 1.7;
      color: rgba(255,255,255,0.88);
      max-width: 420px;
      margin-bottom: 44px;
      animation: fadeSlideUp 0.7s 0.2s ease both;
    }

    .slider-cta-group {
      display: flex;
      gap: 16px;
      align-items: center;
      animation: fadeSlideUp 0.7s 0.3s ease both;
    }

    .btn-primary {
      padding: 16px 36px;
      background: var(--white);
      color: var(--green-deep);
      border: none;
      border-radius: 100px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: 0.3px;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 6px 24px rgba(0,0,0,0.18);
    }
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(0,0,0,0.22);
    }

    .btn-ghost {
      padding: 16px 32px;
      background: transparent;
      color: var(--white);
      border: 2px solid rgba(255,255,255,0.5);
      border-radius: 100px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      font-weight: 500;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s, transform 0.2s;
    }
    .btn-ghost:hover {
      border-color: var(--white);
      background: rgba(255,255,255,0.12);
      transform: translateY(-2px);
    }

    /* Stats card */
    .slider-right {
      display: flex;
      flex-direction: column;
      gap: 20px;
      animation: fadeSlideUp 0.8s 0.35s ease both;
    }

    .stat-card {
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 20px;
      padding: 28px 32px;
      backdrop-filter: blur(12px);
      transition: transform 0.25s, background 0.25s;
    }
    .stat-card:hover {
      transform: translateX(8px);
      background: rgba(255,255,255,0.18);
    }
    .stat-number {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 52px;
      line-height: 1;
      color: var(--gold-light);
      letter-spacing: 1px;
    }
    .stat-label {
      font-size: 14px;
      font-weight: 500;
      color: rgba(255,255,255,0.8);
      margin-top: 6px;
      letter-spacing: 0.3px;
    }
    .stat-row {
      display: flex;
      gap: 16px;
    }
    .stat-card.half {
      flex: 1;
    }
    .stat-card.half .stat-number { font-size: 38px; }

    /* ─── FEATURES STRIP ─── */
    .features {
      background: var(--white);
      padding: 80px 48px;
    }
    .features-inner {
      max-width: 1200px;
      margin: 0 auto;
    }
    .section-label {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 3px;
      text-transform: uppercase;
      color: var(--green-mid);
      margin-bottom: 14px;
    }
    .section-title {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(36px, 5vw, 56px);
      color: var(--text-dark);
      letter-spacing: 0.5px;
      margin-bottom: 16px;
      line-height: 1;
    }
    .section-sub {
      font-size: 16px;
      color: #5a7a5e;
      max-width: 480px;
      line-height: 1.7;
      margin-bottom: 56px;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
    }
    .feature-card {
      border: 1px solid #e2f0e4;
      border-radius: 20px;
      padding: 36px 32px;
      background: var(--off-white);
      transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
      position: relative;
      overflow: hidden;
    }
    .feature-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--green-mid), var(--gold));
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
    }
    .feature-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 48px rgba(26,92,42,0.1);
      border-color: var(--green-pale);
    }
    .feature-card:hover::before { transform: scaleX(1); }

    .feature-icon {
      width: 52px; height: 52px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--green-pale), var(--green-wash));
      display: flex; align-items: center; justify-content: center;
      font-size: 24px;
      margin-bottom: 24px;
    }
    .feature-name {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 24px;
      letter-spacing: 0.5px;
      color: var(--green-deep);
      margin-bottom: 10px;
    }
    .feature-desc {
      font-size: 14px;
      line-height: 1.7;
      color: #5a7a5e;
    }

    /* ─── PROCESS ─── */
    .process {
      background: var(--green-deep);
      padding: 80px 48px;
      position: relative;
      overflow: hidden;
    }
    .process::before {
      content: '';
      position: absolute;
      top: -80px; right: -80px;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: rgba(212,175,55,0.08);
      pointer-events: none;
    }
    .process-inner {
      max-width: 1200px;
      margin: 0 auto;
    }
    .process .section-label { color: var(--gold-light); }
    .process .section-title { color: var(--white); }
    .process .section-sub { color: rgba(255,255,255,0.65); }

    .steps {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0;
      position: relative;
    }
    .steps::before {
      content: '';
      position: absolute;
      top: 36px; left: 10%; right: 10%;
      height: 2px;
      background: linear-gradient(90deg, var(--gold) 0%, rgba(212,175,55,0.2) 100%);
      z-index: 0;
    }

    .step {
      position: relative;
      z-index: 1;
      text-align: center;
      padding: 0 20px;
    }
    .step-num {
      width: 72px; height: 72px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--gold), var(--gold-light));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Bebas Neue', sans-serif;
      font-size: 28px;
      color: var(--green-deep);
      margin: 0 auto 24px;
      box-shadow: 0 4px 20px rgba(212,175,55,0.4);
    }
    .step-title {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 20px;
      color: var(--white);
      letter-spacing: 0.5px;
      margin-bottom: 10px;
    }
    .step-desc {
      font-size: 13px;
      color: rgba(255,255,255,0.6);
      line-height: 1.6;
    }

    /* ─── CTA BAND ─── */
    .cta-band {
      background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-light) 100%);
      padding: 80px 48px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cta-band::before {
      content: 'SDCC';
      position: absolute;
      font-family: 'Bebas Neue', sans-serif;
      font-size: 240px;
      color: rgba(255,255,255,0.06);
      top: 50%; left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
      letter-spacing: 10px;
      white-space: nowrap;
    }
    .cta-band h2 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(40px, 6vw, 72px);
      color: var(--white);
      letter-spacing: 1px;
      margin-bottom: 20px;
      position: relative;
    }
    .cta-band p {
      font-size: 17px;
      color: rgba(255,255,255,0.85);
      max-width: 460px;
      margin: 0 auto 40px;
      line-height: 1.7;
      position: relative;
    }
    .btn-cta {
      padding: 18px 48px;
      background: var(--white);
      color: var(--green-deep);
      border: none;
      border-radius: 100px;
      font-family: 'DM Sans', sans-serif;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 8px 28px rgba(0,0,0,0.15);
      position: relative;
    }
    .btn-cta:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 40px rgba(0,0,0,0.2);
    }
    .questions-section {
  padding: 80px 24px;
  text-align: center;
  background-color: #ffffff;
}

.questions-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 48px;
  letter-spacing: 1.5px;
  color: #1a5c2a;
  margin-bottom: 12px;
}

.questions-sub {
  font-size: 14px;
  color: #5dbf6e;
}

.questions-sub a {
  color: #237a37;
  font-weight: 600;
  text-decoration: underline;
}

   /* ─── FOOTER SECTION ─── */
footer {
  background-color: #237a37;
  color: #ffffff;
  padding-top: 60px;
  width: 100%;
}

.footer-container {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 24px 60px;
  display: grid;
  grid-template-columns: 1.4fr 1fr 0.8fr;
  gap: 40px;
  align-items: start;
}

.footer-col-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.footer-logo {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 16px;
}

.footer-tagline {
  font-size: 13px;
  line-height: 1.5;
  color: rgba(255, 255, 255, 0.9);
  max-width: 280px;
  margin-bottom: 24px;
}

.footer-socials {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.social-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.2);
  color: #ffffff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  text-decoration: none;
  transition: background-color 0.2s ease, transform 0.2s ease;
}

.social-btn:hover {
  background-color: rgba(255, 255, 255, 0.35);
  transform: translateY(-2px);
}

.footer-title {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 20px;
  letter-spacing: 1.5px;
  margin-bottom: 20px;
  color: #ffffff;
}

.footer-col p {
  font-size: 13px;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.9);
}

.footer-spacer {
  margin-top: 14px;
}

.footer-email {
  color: #fce881;
  text-decoration: none;
  font-weight: 500;
}

.footer-email:hover {
  text-decoration: underline;
}

.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li {
  margin-bottom: 10px;
}

.footer-links a {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  font-size: 13px;
  transition: opacity 0.2s;
}

.footer-links a:hover {
  opacity: 0.75;
}

.footer-copyright {
  background-color: #1a5c2a;
  text-align: center;
  padding: 16px;
  font-size: 11px;
  letter-spacing: 1.2px;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 500;
}

@media (max-width: 850px) {
  .footer-container {
    grid-template-columns: 1fr;
    text-align: center;
    gap: 36px;
  }
  .footer-col-brand {
    align-items: center;
  }
}

    /* ─── ANIMATIONS ─── */
    @keyframes fadeSlideUp {
      from { opacity: 0; transform: translateY(24px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: 0.5; transform: scale(0.75); }
    }

    /* ─── RESPONSIVE ─── */
    @media (max-width: 900px) {
      nav { padding: 0 24px; }
      .nav-links { display: none; }
      .home-content { grid-template-columns: 1fr; gap: 40px; padding: 100px 24px 60px; }
      .home-right { display: none; }
      .features { padding: 60px 24px; }
      .features-grid { grid-template-columns: 1fr; }
      .steps { grid-template-columns: 1fr 1fr; gap: 40px; }
      .steps::before { display: none; }
      .process { padding: 60px 24px; }
      footer { flex-direction: column; gap: 20px; text-align: center; padding: 32px 24px; }
    }

    @media (max-width: 600px) {
      nav { padding: 0 16px; height: 64px; }
      .nav-logo img { width: 42px; height: 42px; }
      .nav-name { font-size: 17px; }
      .slider-content { padding: 100px 16px 50px; gap: 32px; }
      .slider-title { font-size: clamp(44px, 12vw, 64px); }
      .slider-sub { font-size: 15px; }
      .slider-cta-group { flex-wrap: wrap; }
      .btn-primary, .btn-ghost { padding: 14px 24px; font-size: 14px; }
      .stat-number { font-size: 40px; }
      .features { padding: 48px 16px; }
      .features-grid { gap: 18px; }
      .feature-card { padding: 28px 20px; }
      .feature-name { font-size: 20px; }
      .process { padding: 48px 16px; }
      .steps { grid-template-columns: 1fr; gap: 32px; }
      .step-num { width: 56px; height: 56px; font-size: 22px; }
      .cta-band { padding: 48px 16px; }
      .cta-band h2 { font-size: clamp(36px, 11vw, 56px); }
      .questions-section { padding: 48px 16px; }
      .questions-title { font-size: 36px; }
      .footer-container { padding: 0 16px 40px; gap: 24px; }
      .footer-logo { width: 72px; height: 72px; }
    }

    @media (max-width: 380px) {
      .slider-content { padding: 90px 12px 40px; }
      .slider-title { font-size: clamp(38px, 14vw, 52px); }
      .slider-tag { font-size: 10px; padding: 4px 10px; }
      .nav-logo img { width: 36px; height: 36px; }
      .nav-name { font-size: 15px; }
      .btn-primary, .btn-ghost { padding: 12px 20px; font-size: 13px; }
      .stat-card { padding: 20px; }
      .stat-number { font-size: 32px; }
    }

    /* ─── FACEBOOK STYLE PROFILE MENU ─── */
    .user-menu-wrapper {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .welcome-user {
      font-size: 14px;
      color: var(--text-mid);
      white-space: nowrap;
    }

    .welcome-user strong {
      color: var(--green-deep);
      font-weight: 600;
    }

    .profile-dropdown {
      position: relative;
      display: inline-block;
    }

    /* Outer button containing picture + badge */
    .fb-profile-btn {
      position: relative;
      background: transparent;
      border: none;
      padding: 0;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      outline: none;
    }

    /* Circular Profile Picture */
    .fb-avatar-img {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--green-pale);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .fb-profile-btn:hover .fb-avatar-img {
      border-color: var(--green-mid);
      transform: scale(1.03);
    }

    /* Overlapping Dropdown Badge */
    .arrow-badge {
      position: absolute;
      bottom: -2px;
      right: -2px;
      width: 18px;
      height: 18px;
      background-color: var(--text-dark);
      color: var(--white);
      border-radius: 50%;
      border: 2px solid var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 9px;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }

    .fb-profile-btn:hover .arrow-badge {
      background-color: var(--green-deep);
      transform: scale(1.1);
    }

    /* Dropdown Menu Container */
    .dropdown-menu-custom {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      margin-top: 10px;
      min-width: 170px;
      background: var(--white);
      border: 1px solid rgba(46, 139, 62, 0.15);
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(26, 92, 42, 0.15);
      overflow: hidden;
      z-index: 200;
    }

    /* Show menu when clicked */
    .dropdown-menu-custom.show {
      display: block;
    }

    .dropdown-menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      padding: 12px 18px;
      background: transparent;
      border: none;
      color: var(--text-dark);
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      text-align: left;
      cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }

    .dropdown-menu-item:hover {
      background: var(--green-wash);
      color: var(--green-deep);
    }

    .dropdown-menu-item.logout-btn {
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      color: #d9534f;
    }

    .dropdown-menu-item.logout-btn:hover {
      background: #fff0f0;
      color: #c9302c;
    }

    /* ─── HAMBURGER (mobile only) ─── */
    .nav-toggle {
      display: none;
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 10px;
      margin-left: 8px;
      color: var(--green-deep);
      font-size: 20px;
      line-height: 1;
    }

    /* ─── SLIDE-OUT SIDEBAR ─── */
    .sidebar {
      position: fixed;
      top: 0;
      right: 0;
      bottom: 0;
      width: min(320px, 86vw);
      z-index: 200;
      background: var(--white);
      border-left: 1px solid rgba(46,139,62,0.14);
      box-shadow: -18px 0 48px rgba(13,43,18,0.16);
      transform: translateX(100%);
      transition: transform 0.28s ease;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }
    .sidebar.open { transform: translateX(0); }

    .sidebar-overlay {
      position: fixed;
      inset: 0;
      z-index: 199;
      background: rgba(13,43,18,0.42);
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.28s ease, visibility 0.28s ease;
    }
    .sidebar-overlay.open { opacity: 1; visibility: visible; }

    .sidebar-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 22px 24px;
      border-bottom: 1px solid #edf5ee;
    }
    .sidebar-brand {
      font-family: 'Bebas Neue', sans-serif;
      font-size: 18px;
      letter-spacing: 1.4px;
      color: var(--green-deep);
      line-height: 1.15;
    }
    .sidebar-close {
      background: transparent;
      border: none;
      cursor: pointer;
      font-size: 20px;
      color: var(--text-mid);
      padding: 6px;
      line-height: 1;
    }
    .sidebar-close:hover { color: var(--green-deep); }

    .sidebar-user {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 22px 24px;
      background: var(--green-wash);
      border-bottom: 1px solid #edf5ee;
    }
    .sidebar-user img {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--green-pale);
    }
    .sidebar-user-name { font-size: 15px; font-weight: 600; color: var(--green-deep); }
    .sidebar-user-meta { font-size: 13px; color: var(--text-mid); }

    .sidebar-nav {
      list-style: none;
      padding: 14px 0;
      flex: 1;
    }
    .sidebar-nav a,
    .sidebar-nav button {
      display: flex;
      align-items: center;
      gap: 14px;
      width: 100%;
      padding: 15px 24px;
      background: transparent;
      border: none;
      text-align: left;
      text-decoration: none;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      font-weight: 500;
      color: var(--text-mid);
      cursor: pointer;
      transition: background 0.18s, color 0.18s;
    }
    .sidebar-nav a:hover,
    .sidebar-nav button:hover {
      background: var(--green-wash);
      color: var(--green-deep);
    }
    .sidebar-nav i { width: 18px; text-align: center; color: var(--green-mid); }
    .sidebar-nav .logout i { color: #d9534f; }
    .sidebar-nav .logout:hover { background: #fff0f0; color: #c9302c; }
    .sidebar-divider { height: 1px; background: #edf5ee; margin: 10px 24px; }

    .sidebar-foot {
      padding: 22px 24px 28px;
      border-top: 1px solid #edf5ee;
      display: grid;
      gap: 12px;
    }
    .sidebar-foot .btn-login,
    .sidebar-foot .btn-register { text-align: center; }

    @media (max-width: 900px) {
      .nav-toggle { display: block; }
      .welcome-user { display: none; }

      /* The older rules above target .home-content / .home-right, but the
         markup uses .slider-content / .slider-right, so the hero never
         collapsed on mobile. These do what those were meant to do. */
      .slider-content { grid-template-columns: 1fr; gap: 40px; padding: 100px 24px 60px; }
      .slider-right { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
      .sidebar, .sidebar-overlay { transition: none; }
    }

    /* ─── FAQ Page Specific Styles ─── */

    /* Hide topbar navigation links on FAQ page */
    body.faq-page .nav-links {
      display: none !important;
    }

    /* ─── Go Back Button ─── */
    .faq-back-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      background: var(--green-mid);
      color: var(--white);
      text-decoration: none;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      border-radius: 100px;
      transition: all 0.2s;
      margin-bottom: 30px;
    }
    .faq-back-button:hover {
      background: var(--green-deep);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(26,92,42,0.15);
    }

    /* ─── Page Hero ─── */
    .page-hero {
      background: linear-gradient(135deg, var(--green-deep) 0%, var(--green-mid) 100%);
      padding: 140px 48px 80px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .page-hero .bg-img {
      position: absolute;
      inset: 0;
      background: url("{{ asset('images/Background.jpg') }}") center/cover no-repeat;
      opacity: 0.15;
      z-index: 0;
    }
    .page-hero .bg-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(26,92,42,0.9) 0%, rgba(46,139,62,0.75) 100%);
      z-index: 1;
    }
    .page-hero .hero-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      margin: 0 auto;
    }
    .page-hero .slider-tagg {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(212,175,55,0.22);
      border: 1px solid rgba(212,175,55,0.5);
      border-radius: 100px;
      padding: 6px 16px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: var(--gold-light);
      margin-bottom: 24px;
    }
    .page-hero .slider-tag-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--gold);
      animation: pulse 2s infinite;
    }
    .page-hero h1 {
      font-family: 'Bebas Neue', sans-serif;
      font-size: clamp(48px, 7vw, 80px);
      line-height: 1.05;
      letter-spacing: 1px;
      color: var(--white);
      margin-bottom: 20px;
    }
    .page-hero p {
      font-size: 18px;
      font-weight: 300;
      line-height: 1.7;
      color: rgba(255,255,255,0.9);
      max-width: 600px;
      margin: 0 auto;
    }
    .page-hero p a {
      color: var(--gold-light);
      font-weight: 600;
      text-decoration: underline;
    }

    /* ─── FAQ Section ─── */
    .faq-section {
      background: var(--off-white);
      padding: 80px 48px;
    }
    .faq-inner {
      max-width: 900px;
      margin: 0 auto;
    }
    .faq-search {
      margin-bottom: 48px;
      position: relative;
    }
    .faq-search .search-box {
      max-width: 560px;
      margin: 0 auto;
      position: relative;
    }
    .faq-search .search-box i {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--green-mid);
      font-size: 18px;
    }
    .faq-search input[type="search"] {
      width: 100%;
      padding: 16px 16px 16px 56px;
      font-size: 16px;
      font-family: 'DM Sans', sans-serif;
      border: 2px solid #e2f0e4;
      border-radius: 100px;
      background: var(--white);
      color: var(--text-dark);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      box-sizing: border-box;
    }
    .faq-search input[type="search"]:focus {
      border-color: var(--green-mid);
      box-shadow: 0 0 0 4px var(--green-pale);
    }
    .faq-search .filter-hint {
      text-align: center;
      margin-top: 12px;
      font-size: 13px;
      color: var(--text-mid);
    }

    /* ─── Category Buttons ─── */
    .faq-categories {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: center;
      margin-bottom: 48px;
    }
    .faq-cat-btn {
      padding: 10px 22px;
      border: 2px solid #e2f0e4;
      background: var(--white);
      color: var(--text-mid);
      border-radius: 100px;
      font-family: 'DM Sans', sans-serif;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .faq-cat-btn.active,
    .faq-cat-btn:hover {
      border-color: var(--green-mid);
      background: var(--green-mid);
      color: var(--white);
    }

    /* ─── FAQ Items ─── */
    .faq-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .faq-list details {
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid #e2f0e4;
      background: var(--white);
      transition: border-color 0.2s;
    }
    .faq-list details[open] {
      border-color: var(--green-mid);
    }
    .faq-list summary {
      padding: 20px 28px;
      font-family: 'DM Sans', sans-serif;
      font-size: 16px;
      font-weight: 600;
      color: var(--text-dark);
      cursor: pointer;
      list-style: none;
      position: relative;
      transition: color 0.2s;
    }
    .faq-list summary::-webkit-details-marker {
      display: none;
    }
    .faq-list summary::after {
      content: "";
      position: absolute;
      right: 28px;
      top: 50%;
      width: 12px;
      height: 12px;
      border-top: 2px solid var(--green-mid);
      border-right: 2px solid var(--green-mid);
      transform: translateY(-50%) rotate(-45deg);
      transition: transform 0.2s ease;
    }
    .faq-list details[open] summary::after {
      transform: translateY(-50%) rotate(135deg);
    }
    .faq-list summary:hover {
      color: var(--green-deep);
    }
    .faq-list p {
      padding: 0 28px 24px 28px;
      font-size: 15px;
      line-height: 1.7;
      color: var(--text-mid);
    }
    .faq-list strong {
      color: var(--green-deep);
      font-weight: 700;
    }

    /* ─── FAQ CTA ─── */
    .faq-cta {
      text-align: center;
      margin-top: 60px;
      padding: 40px;
      background: var(--white);
      border-radius: 20px;
      border: 1px solid #e2f0e4;
    }
    .faq-cta p {
      font-size: 16px;
      color: var(--text-mid);
      margin-bottom: 20px;
    }
    .faq-cta .btn-primary {
      padding: 14px 32px;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      font-weight: 700;
      text-decoration: none;
      transition: transform 0.2s, box-shadow 0.2s;
      display: inline-block;
      margin: 0 8px;
      border-radius: 100px;
    }
    .faq-cta .btn-primary.btn-primary-green {
      background: var(--green-deep);
      color: var(--white);
      border: none;
    }
    .faq-cta .btn-primary.btn-primary-green:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(0,0,0,0.22);
    }
    .faq-cta .btn-primary.btn-primary-gold {
      background: var(--gold);
      color: var(--green-deep);
      border: none;
    }
    .faq-cta .btn-primary.btn-primary-gold:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(212,175,55,0.2);
    }

    /* ─── Responsive ─── */
    @media (max-width: 900px) {
      .page-hero,
      .faq-section {
        padding-left: 24px;
        padding-right: 24px;
      }
      .faq-back-button {
        margin-bottom: 20px;
      }
    }
    @media (max-width: 600px) {
      .page-hero {
        padding-top: 100px;
        padding-bottom: 60px;
      }
      .page-hero h1 {
        font-size: clamp(36px, 10vw, 56px);
      }
      .faq-section {
        padding-top: 60px;
        padding-bottom: 60px;
      }
      .faq-categories {
        justify-content: center;
      }
      .faq-cat-btn {
        flex: 1;
        min-width: 100px;
        text-align: center;
      }
      .faq-cta .btn-primary {
        margin: 4px 0;
        width: 100%;
        text-align: center;
      }
    }
    </style>