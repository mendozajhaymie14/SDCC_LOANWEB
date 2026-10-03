{{-- Page sections for the landing page: hero, features, process, CTA. --}}


{{-- ─── SLIDER / HERO ─── --}}
<section class="slider" id="home">
  <div class="slider-photo"></div>
  <div class="slider-bg"></div>
  <div class="slider-stripe"></div>

  <div class="dots dots-tl">
    <div class="dot-grid">
      <span></span><span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
  </div>
  <div class="dots dots-br">
    <div class="dot-grid">
      <span></span><span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span><span></span>
      <span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
  </div>

  <div class="slider-content">
    <div class="slider-left">
      <div class="slider-tagg">
      </div>
      <h1 class="slider-title">
        Member<br>
        <em>AI</em> Loan<br>
        Marketplace
      </h1>
      <p class="slider-sub">
        Access fast loans from San Dionisio Credit Cooperative — all in one intelligent platform built for our members.
      </p>
      <p class="slider-trust">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        Trusted by 12,000+ members since 1985
      </p>
      <div class="slider-cta-group">
        <a href="{{ url('/register') }}" class="btn-primary">Create Account</a>
        <a href="{{ url('/member-application') }}" class="btn-ghost">Become a Member</a>
      </div>
    </div>

    <div class="slider-right">
      <div class="stat-card">
        <div class="stat-number">₱2.4B+</div>
        <div class="stat-label">Total Loans Disbursed to Members</div>
      </div>
      <div class="stat-row">
        <div class="stat-card half">
          <div class="stat-number">48hrs</div>
          <div class="stat-label">Average Approval Time</div>
        </div>
        <div class="stat-card half">
          <div class="stat-number">12K+</div>
          <div class="stat-label">Active Members</div>
        </div>
      </div>
      <div class="hero-mini-preview">
        <div class="hero-mini-preview-header">
          <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i>
          <span>How it works</span>
        </div>
        <div class="hero-mini-preview-steps">
          <div class="hmp-step">
            <span class="hmp-num">1</span>
            <span>Register</span>
          </div>
          <div class="hmp-arrow">→</div>
          <div class="hmp-step">
            <span class="hmp-num">2</span>
            <span>Choose Loan</span>
          </div>
          <div class="hmp-arrow">→</div>
          <div class="hmp-step">
            <span class="hmp-num">3</span>
            <span>Get Approved</span>
          </div>
          <div class="hmp-arrow">→</div>
          <div class="hmp-step">
            <span class="hmp-num">4</span>
            <span>Receive Funds</span>
          </div>
        </div>
        <a href="#how" class="hero-mini-preview-link">See full process <i class="fa-solid fa-arrow-right"></i></a>
      </div>
    </div>
  </div>
</section>


{{-- ─── FEATURES ─── --}}
<section class="features" id="features">
  <div class="features-inner">
    <div class="section-label">Why Choose Us</div>
    <h2 class="section-title">Everything You Need<br>In One Platform</h2>
    <p class="section-sub">San Dionisio Credit Cooperative brings member-focused financial services into the digital age.</p>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
        <div class="feature-name">Fast Processing</div>
        <p class="feature-desc">AI-assisted loan evaluation gets you an answer in record time — without the long queues or paperwork piles.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-robot"></i></div>
        <div class="feature-name">AI Loan Matching</div>
        <p class="feature-desc">Smart algorithms match you to the best loan product based on your membership standing and financial profile.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="feature-name">Secure &amp; Trusted</div>
        <p class="feature-desc">Member data is protected with bank-grade encryption. Your financial information stays private and safe.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-globe"></i></div>
        <div class="feature-name">Anytime, Anywhere</div>
        <p class="feature-desc">Apply for loans, check your balance, and track repayments from any device at any time of day.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-hands-holding"></i></div>
        <div class="feature-name">Member-Centric</div>
        <p class="feature-desc">Built exclusively for SDCC members — lower rates, flexible terms, and a cooperative spirit at the core.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
        <div class="feature-name">Real-Time Dashboard</div>
        <p class="feature-desc">See your loan status, payment schedule, and share capital growth in a clean, easy-to-read dashboard.</p>
      </div>
    </div>
    <div class="features-cta">
      <a href="{{ url('/register') }}" class="btn-primary">Create Account</a>
      <a href="{{ url('/member-application') }}" class="btn-outline">Become a Member</a>
    </div>
  </div>
</section>


{{-- ─── PROCESS ─── --}}
<section class="process" id="how">
  <div class="process-inner">
    <div class="section-label">How It Works</div>
    <h2 class="section-title">Apply in 4 Simple Steps</h2>
    <p class="section-sub">From registration to disbursement — the smoothest loan experience you'll find in any cooperative.</p>
    <div class="steps">
      <div class="step step-active">
        <div class="step-num">01</div>
        <div class="step-title">Register / Login</div>
        <p class="step-desc">Create your member account or log in with your existing SDCC credentials.</p>
      </div>
      <div class="step">
        <div class="step-num">02</div>
        <div class="step-title">Choose a Loan</div>
        <p class="step-desc">Browse loan products matched to your membership tier and financial needs.</p>
      </div>
      <div class="step">
        <div class="step-num">03</div>
        <div class="step-title">Submit Application</div>
        <p class="step-desc">Fill in your details online. AI reviews your application instantly for quick decisions.</p>
      </div>
      <div class="step">
        <div class="step-num">04</div>
        <div class="step-title">Receive Funds</div>
        <p class="step-desc">Approved loans are disbursed directly to your account within 48 hours.</p>
      </div>
    </div>
    <div class="process-cta">
      <a href="{{ url('/register') }}" class="btn-primary">Create Account</a>
      <a href="{{ url('/member-application') }}" class="btn-ghost">Become a Member</a>
    </div>
  </div>
</section>


{{-- ─── CTA BAND ─── --}}
<section class="questions-section">
  <h2 class="questions-title">HAVE QUESTIONS IN MIND?</h2>
  <p class="questions-sub">
    Everything you need to know about SDCC. See <a href="{{ route('faqs') }}">FAQs</a>
  </p>
</section>
