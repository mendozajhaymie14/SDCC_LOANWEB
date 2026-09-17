{{-- Loads the landing page stylesheet first, then member-only styles on top. --}}
@include('home.css')

<style>
  /* ─── MEMBER PAGE SHELL ─── */
  .member-main {
    max-width: 1120px;
    margin: 0 auto;
    padding: 124px 32px 88px;
  }

  .nav-links a.is-current { color: var(--green-deep); }
  .nav-links a.is-current::after { transform: scaleX(1); }

  /* ─── FLASH MESSAGES ─── */
  .flash {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 22px;
    border-radius: 14px;
    font-size: 15px;
    margin-bottom: 28px;
  }
  .flash-success { background: var(--green-wash); color: var(--green-deep); border: 1px solid var(--green-pale); }
  .flash-error   { background: #fdeeee; color: #a32b2b; border: 1px solid #f3c9c9; }

  /* ─── PASSBOOK PANEL ─── */
  .passbook {
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, var(--green-deep) 0%, #124021 100%);
    border-radius: 24px;
    padding: 44px 48px;
    color: var(--white);
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 40px;
    align-items: center;
  }
  .passbook::after {
    content: '';
    position: absolute;
    right: -90px; top: -90px;
    width: 280px; height: 280px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(212,175,55,0.28), transparent 68%);
    pointer-events: none;
  }
  .passbook-greeting {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(32px, 4vw, 46px);
    letter-spacing: 1px;
    line-height: 1.05;
    margin-bottom: 10px;
  }
  .passbook-meta {
    font-size: 15px;
    color: var(--green-pale);
    line-height: 1.7;
    max-width: 44ch;
  }
  .passbook-ledger {
    position: relative;
    z-index: 1;
    border-left: 1px solid rgba(255,255,255,0.18);
    padding-left: 40px;
    display: grid;
    gap: 22px;
  }
  .ledger-row { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; }
  .ledger-label { font-size: 13px; color: rgba(255,255,255,0.72); }
  .ledger-value {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 30px;
    letter-spacing: 0.5px;
    color: var(--gold-light);
  }
  .ledger-value.small { font-size: 22px; color: var(--white); }

  .passbook-actions { margin-top: 26px; display: flex; gap: 14px; flex-wrap: wrap; }

  /* ─── APPLICATION LIST ─── */
  .panel-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin: 64px 0 22px;
  }
  .panel-title {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 30px;
    letter-spacing: 1px;
    color: var(--text-dark);
  }
  .panel-note { font-size: 14px; color: #5a7a5e; }

  .app-list { display: grid; gap: 14px; }

  .app-row {
    display: grid;
    grid-template-columns: 1.1fr 1fr 1fr 0.9fr auto;
    gap: 24px;
    align-items: center;
    background: var(--white);
    border: 1px solid #e2f0e4;
    border-radius: 16px;
    padding: 22px 26px;
  }
  .app-ref { font-size: 13px; letter-spacing: 1px; color: var(--green-mid); font-weight: 600; }
  .app-type { font-size: 16px; font-weight: 600; color: var(--text-dark); margin-top: 4px; }
  .app-cell-label { font-size: 12px; color: #7a927e; margin-bottom: 4px; }
  .app-cell-value { font-size: 16px; font-weight: 600; color: var(--text-dark); }

  .badge {
    display: inline-block;
    padding: 7px 16px;
    border-radius: 100px;
    font-size: 13px;
    font-weight: 600;
  }
  .badge-pending  { background: #fdf4dd; color: #8a6d13; }
  .badge-approved { background: var(--green-wash); color: var(--green-deep); }
  .badge-rejected { background: #fdeeee; color: #a32b2b; }

  .link-withdraw {
    background: none;
    border: none;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: #a32b2b;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
  }

  .empty-state {
    border: 1px dashed var(--green-pale);
    border-radius: 20px;
    padding: 56px 32px;
    text-align: center;
    background: var(--white);
  }
  .empty-state p { color: #5a7a5e; margin-bottom: 24px; line-height: 1.7; }

  /* ─── BUTTONS FOR LIGHT BACKGROUNDS ─── */
  .btn-solid {
    display: inline-block;
    text-decoration: none;
    padding: 14px 32px;
    border-radius: 100px;
    border: none;
    background: var(--green-mid);
    color: var(--white);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.15s;
  }
  .btn-solid:hover { background: var(--green-deep); transform: translateY(-2px); }

  .btn-outline {
    display: inline-block;
    text-decoration: none;
    padding: 14px 30px;
    border-radius: 100px;
    border: 2px solid rgba(255,255,255,0.45);
    background: transparent;
    color: var(--white);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
  }
  .btn-outline:hover { background: rgba(255,255,255,0.12); border-color: var(--white); }

  /* ─── LOAN FORM ─── */
  .form-header { margin-bottom: 40px; max-width: 60ch; }
  .form-header h1 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: clamp(38px, 5vw, 54px);
    letter-spacing: 0.5px;
    line-height: 1;
    color: var(--text-dark);
    margin-bottom: 14px;
  }
  .form-header p { color: #5a7a5e; line-height: 1.7; font-size: 16px; }

  .form-shell {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 32px;
    align-items: start;
  }

  .form-card {
    background: var(--white);
    border: 1px solid #e2f0e4;
    border-radius: 22px;
    padding: 40px;
  }

  .fieldset { border: none; }
  .fieldset + .fieldset { margin-top: 40px; padding-top: 36px; border-top: 1px solid #edf5ee; }
  .fieldset legend {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 22px;
    letter-spacing: 1.2px;
    color: var(--green-deep);
    margin-bottom: 22px;
  }

  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .field { margin-bottom: 20px; }
  .field.full { grid-column: 1 / -1; }

  .field label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-mid);
    margin-bottom: 8px;
  }
  .field .hint { font-weight: 400; color: #7a927e; }

  .field input,
  .field select,
  .field textarea {
    width: 100%;
    padding: 13px 16px;
    border: 1px solid #d8e8db;
    border-radius: 12px;
    background: var(--off-white);
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    color: var(--text-dark);
    transition: border-color 0.2s, background 0.2s;
  }
  .field textarea { min-height: 120px; resize: vertical; line-height: 1.6; }
  .field input:focus,
  .field select:focus,
  .field textarea:focus {
    outline: 2px solid var(--green-light);
    outline-offset: 1px;
    border-color: var(--green-mid);
    background: var(--white);
  }
  .field input.has-error,
  .field select.has-error,
  .field textarea.has-error { border-color: #d98a8a; background: #fdf7f7; }

  .error-text { display: block; margin-top: 6px; font-size: 13px; color: #a32b2b; }

  .checkbox-field {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    font-size: 14px;
    color: var(--text-mid);
    line-height: 1.6;
  }
  .checkbox-field input { width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0; }

  .form-actions { margin-top: 36px; display: flex; gap: 16px; align-items: center; }
  .btn-cancel { text-decoration: none; font-size: 15px; color: #5a7a5e; }
  .btn-cancel:hover { color: var(--green-deep); }

  /* ─── REPAYMENT ESTIMATE ─── */
  .estimate {
    position: sticky;
    top: 104px;
    background: var(--green-wash);
    border: 1px solid var(--green-pale);
    border-radius: 22px;
    padding: 32px 28px;
  }
  .estimate h2 {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 22px;
    letter-spacing: 1.2px;
    color: var(--green-deep);
    margin-bottom: 18px;
  }
  .estimate-amount {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 44px;
    line-height: 1;
    color: var(--green-deep);
    margin-bottom: 4px;
  }
  .estimate-caption { font-size: 13px; color: var(--text-mid); margin-bottom: 24px; }
  .estimate-line {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: var(--text-mid);
    padding: 9px 0;
    border-top: 1px solid rgba(26,92,42,0.12);
  }
  .estimate-foot { margin-top: 20px; font-size: 12px; color: #5a7a5e; line-height: 1.6; }

  @media (max-width: 900px) {
    .member-main { padding: 104px 20px 64px; }
    .passbook { grid-template-columns: 1fr; padding: 32px 26px; }
    .passbook-ledger { border-left: none; border-top: 1px solid rgba(255,255,255,0.18); padding-left: 0; padding-top: 26px; }
    .form-shell { grid-template-columns: 1fr; }
    .estimate { position: static; order: -1; }
    .form-card { padding: 28px 22px; }
    .grid-2 { grid-template-columns: 1fr; }
    .app-row { grid-template-columns: 1fr 1fr; gap: 16px; }
    .passbook-actions { flex-direction: column; align-items: stretch; }
    .passbook-actions a { text-align: center; }
  }

  @media (max-width: 640px) {
    .member-main { padding: 96px 16px 48px; }
    .passbook { padding: 24px 20px; }
    .passbook-greeting { font-size: 28px; }
    .ledger-value { font-size: 24px; }
    .panel-head { flex-direction: column; align-items: flex-start; gap: 8px; margin: 40px 0 16px; }
    .panel-title { font-size: 24px; }
    .form-card { padding: 20px 16px; }
    .form-actions { flex-direction: column; align-items: stretch; gap: 10px; }
    .form-actions a { text-align: center; }
    .app-row { grid-template-columns: 1fr; padding: 16px; gap: 10px; }
    .btn-solid { width: 100%; text-align: center; }
  }

  @media (max-width: 380px) {
    .member-main { padding: 88px 12px 40px; }
    .form-header h1 { font-size: 32px; }
  }
</style>