<!DOCTYPE html>
<html lang="en">
<head>
  @include('home.css')
</head>
<body class="faq-page">

  <header>
    @include('home.header')
  </header>

  @include('home.sidebar')

  <main>
    {{-- ─── FAQ PAGE HERO ─── --}}
    <section class="page-hero">
      <div class="bg-img"></div>
      <div class="bg-overlay"></div>
      <div class="hero-content">
        <a href="{{ url('/') }}" class="faq-back-button">
          <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <div class="slider-tagg">
          <span class="slider-tag-dot"></span>
          Help Center
        </div>
        <h1>Frequently Asked<br>Questions</h1>
        <p>
          Everything you need to know about applying for a loan with San Dionisio Credit Cooperative.
          Can't find your answer? <a href="{{ url('/contact') }}">Contact us</a>.
        </p>
      </div>
    </section>

    {{-- ─── FAQ CONTENT ─── --}}
    <section class="faq-section" id="faqs">
      <div class="faq-inner">

        {{-- Search Box --}}
        <div class="faq-search">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="faqSearch" placeholder="Search questions…" aria-label="Search FAQs">
          </div>
          <p class="filter-hint">Type to filter the list below</p>
        </div>

        {{-- Categories --}}
        <div class="faq-categories">
          <button class="faq-cat-btn active" data-cat="all">All</button>
          <button class="faq-cat-btn" data-cat="loans">Loan Products</button>
          <button class="faq-cat-btn" data-cat="apply">Applying</button>
          <button class="faq-cat-btn" data-cat="repayment">Repayment</button>
          <button class="faq-cat-btn" data-cat="account">Account</button>
          <button class="faq-cat-btn" data-cat="security">Security</button>
        </div>

        {{-- FAQ List --}}
        <div class="faq-list">

          {{-- Category: Loan Products --}}
          <details data-category="loans" open>
            <summary>What loan products does SDCC offer?</summary>
            <p>We offer six loan products tailored to different needs: <strong>Salary Loan</strong> (short-term against your regular income), <strong>Emergency Loan</strong> (quick release for medical or urgent needs), <strong>Business Loan</strong> (capital for small businesses or expansion), <strong>Educational Loan</strong> (tuition and school expenses), <strong>Appliance Loan</strong> (purchase household appliances on instalment), and <strong>Multi-Purpose Loan</strong> (flexible use for any personal need). Each product has specific eligibility criteria and terms.</p>
          </details>

          <details data-category="loans">
            <summary>What is the minimum and maximum loan amount?</summary>
            <p>The minimum loan amount we release is <strong>₱1,000</strong> and the maximum is <strong>₱2,000,000</strong>, depending on your membership standing, share capital, and repayment capacity. Your approved amount is based on our credit assessment which considers your income, existing obligations, and cooperative share capital.</p>
          </details>

          <details data-category="loans">
            <summary>What payment terms are available?</summary>
            <p>You can choose a repayment term of <strong>6, 12, 18, 24, 36, 48, or 60 months</strong>. Longer terms mean lower monthly payments but more total interest paid. Salary Loans typically have shorter maximum terms (up to 24 months), while Business and Multi-Purpose Loans can extend to 60 months.</p>
          </details>

          <details data-category="loans">
            <summary>What interest rate applies to my loan?</summary>
            <p>Our standard rate is a <strong>flat 12% per annum</strong> add-on rate. This means your monthly payment is calculated as (principal + total interest) divided by the number of months. For example, a ₱100,000 loan over 24 months: total interest = ₱24,000; monthly payment = ₱5,166.67. Your final rate may vary based on your loan product, membership standing, and credit assessment.</p>
          </details>

          <details data-category="loans">
            <summary>Are there any processing fees or hidden charges?</summary>
            <p>We charge a one-time <strong>processing fee of 1%</strong> of the loan amount (minimum ₱500, maximum ₱5,000), deducted from the loan proceeds. There are no hidden charges. Late payment fees apply at 3% of the unpaid monthly amortization. All fees are disclosed in your loan agreement before you sign.</p>
          </details>

          <details data-category="loans">
            <summary>Can I get a loan for debt consolidation?</summary>
            <p>Yes, our <strong>Multi-Purpose Loan</strong> can be used for debt consolidation. You'll need to provide statements of your existing debts during application. Consolidating multiple high-interest debts into one SDCC loan at 12% p.a. can significantly reduce your monthly payments and simplify your finances.</p>
          </details>

          {{-- Category: Applying --}}
          <details data-category="apply">
            <summary>How long does it take to get approved?</summary>
            <p>Most applications are reviewed within <strong>48 hours</strong>. You'll receive an email notification as soon as a decision is made. In some cases, additional verification may be required (e.g., employer confirmation, business permit validation), which could take 1-2 additional business days.</p>
          </details>

          <details data-category="apply">
            <summary>What documents do I need to submit?</summary>
            <p>Requirements vary by loan type, but generally include: <strong>Valid government ID</strong> (2 pcs), <strong>Proof of income</strong> (payslips, ITR, or business financials), <strong>Proof of billing</strong> (recent utility bill), and <strong>Cooperative membership records</strong>. Salary loans require a Certificate of Employment; Business loans need DTI/SEC registration and Mayor's Permit. Complete checklists are shown during the online application.</p>
          </details>

          <details data-category="apply">
            <summary>Can I apply if I already have a pending application?</summary>
            <p>No. To ensure fair treatment for all members, you can only have <strong>one application under review at a time</strong>. You'll need to wait for a decision on your current application before submitting a new one. If your application is rejected, you can reapply immediately.</p>
          </details>

          <details data-category="apply">
            <summary>What happens if I want to withdraw my application?</summary>
            <p>If your application is still <strong>pending</strong> (under review), you can withdraw it at any time from your dashboard. Once approved or rejected, the application cannot be withdrawn. Withdrawn applications don't affect your future eligibility.</p>
          </details>

          <details data-category="apply">
            <summary>Can I cancel my application after approval?</summary>
            <p>Once an application is approved and you've signed the loan agreement, the loan terms are binding. If you need to make changes after approval, please contact the admin team directly through the <strong>Borrowers</strong> page in the admin dashboard or visit our office. Pre-signing cancellation is possible but may affect future applications.</p>
          </details>

          <details data-category="apply">
            <summary>Do I need a co-maker or guarantor?</summary>
            <p>For loans up to <strong>₱500,000</strong>, a co-maker is generally not required if you have sufficient share capital and good repayment history. Loans above ₱500,000 or for members with limited share capital may require 1-2 co-makers who are also SDCC members in good standing. The system will notify you during application if co-makers are needed.</p>
          </details>

          {{-- Category: Repayment --}}
          <details data-category="repayment">
            <summary>How do I make loan payments?</summary>
            <p>Payments can be made through: <strong>Auto-debit</strong> from your SDCC savings account (recommended), <strong>Over-the-counter</strong> at our office, <strong>Bank transfer</strong> to SDCC's designated accounts, or <strong>Digital wallets</strong> (GCash, Maya) via our payment partners. Auto-debit ensures you never miss a due date.</p>
          </details>

          <details data-category="repayment">
            <summary>When is my payment due each month?</summary>
            <p>Your due date is fixed based on your loan release date — typically the <strong>same calendar day each month</strong>. If your due date falls on a weekend or holiday, payment is due the next business day. You can view your exact due dates and full amortization schedule in your dashboard.</p>
          </details>

          <details data-category="repayment">
            <summary>Can I pay off my loan early?</summary>
            <p>Yes, you can make <strong>full or partial prepayments</strong> at any time without penalty. Early repayment reduces your total interest since our loans use the add-on rate method. Contact us or use your dashboard to request a prepayment computation — we'll provide the updated balance and any interest rebate.</p>
          </details>

          <details data-category="repayment">
            <summary>What happens if I miss a payment?</summary>
            <p>A <strong>3% late fee</strong> applies to the unpaid monthly amortization. After 30 days past due, your account is flagged for collection follow-up. At 60 days, the loan may be restructured (with fees) or referred to legal. We strongly encourage reaching out <strong>before</strong> you miss a payment — we can discuss options like restructuring or a grace period.</p>
          </details>

          <details data-category="repayment">
            <summary>Can I change my payment due date?</summary>
            <p>Due dates are set at loan release and generally cannot be changed. However, if your income schedule has permanently changed (e.g., new employer payroll date), you can submit a request through your dashboard or visit our office. Approval is subject to review and may require a loan amendment.</p>
          </details>

          {{-- Category: Account --}}
          <details data-category="account">
            <summary>Who can become a member and apply for a loan?</summary>
            <p>Any resident or worker within the <strong>San Dionisio</strong> community area can become a member. You'll need to register with a valid email address (@gmail.com accepted), provide your contact details, and agree to our terms and privacy policy. Membership requires a minimum share capital of ₱1,000 (payable in instalments).</p>
          </details>

          <details data-category="account">
            <summary>How do I check my loan application status?</summary>
            <p>Log in to your member dashboard at <strong>/user/dashboard</strong>. Your application status shows as: <strong>Pending Review</strong>, <strong>For Compliance</strong> (needs more docs), <strong>Approved</strong>, <strong>Released</strong>, or <strong>Rejected</strong>. You'll also receive email updates at each stage.</p>
          </details>

          <details data-category="account">
            <summary>Where can I view my loan balance and payment history?</summary>
            <p>Your dashboard shows: <strong>Current balance</strong>, <strong>Next due date & amount</strong>, <strong>Full amortization schedule</strong>, <strong>Payment history</strong> with dates and amounts, and <strong>Share capital</strong> balance. You can also download statements as PDF.</p>
          </details>

          <details data-category="account">
            <summary>How do I update my contact information?</summary>
            <p>Go to <strong>Edit Profile</strong> in your dashboard (or sidebar menu). You can update your phone, email, and address there. For security, email changes require verification. Address changes may require a new proof of billing for loan-related correspondence.</p>
          </details>

          <details data-category="account">
            <summary>What is share capital and how does it affect my loan?</summary>
            <p>Share capital is your <strong>ownership stake</strong> in the cooperative — minimum ₱1,000. It determines your maximum loan eligibility (typically up to 10x your share capital) and earns annual dividends. You can increase your share capital anytime through your dashboard to unlock higher loan limits.</p>
          </details>

          {{-- Category: Security --}}
          <details data-category="security">
            <summary>Is my personal information secure?</summary>
            <p>Absolutely. We use <strong>bank-grade encryption</strong> (TLS 1.3) for all data transmission and AES-256 encryption for data at rest. We comply with the Data Privacy Act of 2012. Your personal information is never shared with third parties without your consent, except as required by law.</p>
          </details>

          <details data-category="security">
            <summary>How do I reset my password?</summary>
            <p>Click <strong>Forgot Password</strong> on the login page. Enter your registered email — you'll receive a secure reset link valid for 60 minutes. For security, the link can only be used once. If you don't receive the email, check spam or contact support.</p>
          </details>

          <details data-category="security">
            <summary>Can I enable two-factor authentication?</summary>
            <p>Yes! Go to <strong>Edit Profile → Security</strong> in your dashboard to enable 2FA using an authenticator app (Google Authenticator, Authy, etc.). We strongly recommend this for added account protection, especially if you have active loans.</p>
          </details>

          <details data-category="security">
            <summary>What should I do if I suspect unauthorized access to my account?</summary>
            <p>Immediately: <strong>1)</strong> Change your password via Forgot Password, <strong>2)</strong> Enable 2FA if not already on, <strong>3)</strong> Check your dashboard for any unauthorized loan applications, <strong>4)</strong> Contact us at <a href="mailto:info@sandionisocredit.coop">info@sandionisocredit.coop</a> or call 8826-1055. We'll secure your account and investigate.</p>
          </details>

        </div>

        {{-- FAQ CTA --}}
        <div class="faq-cta">
          <p>Still have questions?</p>
          <div>
            <a href="{{ url('/contact') }}" class="btn-primary btn-primary-green">Contact Us</a>
            <a href="{{ route('user.loans.create') }}" class="btn-primary btn-primary-gold">Apply for a Loan</a>
          </div>
        </div>

      </div>
    </section>
  </main>

  @include('home.footer')

</body>
</html>
