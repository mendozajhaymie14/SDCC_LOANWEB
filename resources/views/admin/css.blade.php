<style>
*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

:root {
  --bg: #f8fafc;
  --surface: #ffffff;
  --card: #ffffff;
  --card2: #f1f5f9;
  --border: rgba(203, 213, 225, 0.8);
  --border2: rgba(148, 163, 184, 0.5);
  --accent: #0284c7;
  --accent2: #4338ca;
  --accent3: #059669;
  --gold: #d97706;
  --red: #dc2626;
  --green: #22c55e;
  --orange: #ea580c;
  --text: #0f172a;
  --muted: #64748b;
  --muted2: #334155;
  --sidebar-w: 240px;
  --topbar-h: 62px;
}

html, body {
  height: 100%;
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  display: flex;
  overflow: hidden;
}

/* SIDEBAR LAYOUT */
.sidebar {
  width: 68px;
  height: 100vh;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  overflow: hidden;
  z-index: 50;
  transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
}

.sidebar:hover {
  width: var(--sidebar-w);
  overflow-y: auto;
}

.sidebar-logo {
  height: var(--topbar-h);
  padding: 0 0.9rem;
  border-bottom: none; /* Changed from 1px solid var(--border) */
  display: flex;
  align-items: center;
  gap: 0.8rem;
  cursor: pointer;
  box-sizing: border-box;
  text-decoration: none;
}

.sidebar-logo:hover {
  text-decoration: none;
}

/* Custom Sidebar Logo Style */
.sidebar-custom-logo {
  width: 36px;
  height: 36px;
  object-fit: contain;
  border-radius: 50%;
  flex-shrink: 0;
}

.logo-mark {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'DM Sans', sans-serif;
  font-weight: 800;
  font-size: 0.9rem;
  color: #ffffff;
  flex-shrink: 0;
}

.logo-text {
  font-family: 'DM Sans', sans-serif;
  font-weight: 800;
  font-size: 0.85rem;
  color: #1b5e20;
  letter-spacing: 0.02em;
  text-decoration: none;
  border-bottom: none;
  line-height: 1.2;
  text-transform: uppercase;
}

.logo-text a {
  text-decoration: none;
  color: inherit;
}

.logo-line {
  display: block;
  text-decoration: none;
}

.sidebar-section {
  padding: 1.2rem 1.2rem 0.4rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  padding: 0.65rem 0.9rem;
  margin: 0.2rem 0.5rem;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--muted2);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  user-select: none;
  text-decoration: none;
}

.nav-item:hover {
  background: rgba(2, 132, 199, 0.08);
  color: var(--accent);
}

.nav-item.active {
  background: rgba(2, 132, 199, 0.12);
  color: var(--accent);
}

.nav-icon {
  font-size: 1.1rem;
  width: 24px;
  min-width: 24px;
  text-align: center;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav-badge {
  margin-left: auto;
  background: var(--accent);
  color: #ffffff;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.15rem 0.45rem;
  border-radius: 50px;
  min-width: 18px;
  text-align: center;
}

.nav-badge.red { background: var(--red); color: #fff; }
.nav-badge.gold { background: var(--gold); color: #ffffff; }

.sidebar-footer {
  margin-top: auto;
  padding: 0.8rem 0.6rem;
  border-top: 1px solid var(--border);
}

.admin-pill {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.6rem;
  border-radius: 10px;
  background: var(--card2);
  cursor: pointer;
  transition: background 0.2s ease;
}

.admin-pill:hover { background: #e2e8f0; }

/* Admin Avatar Icon Styling */
.admin-avatar-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--green);
  color: #ffffff;
  font-size: 1.1rem;
}
.admin-role {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text);
  line-height: 1.2;
}

.admin-name {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--muted);
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.admin-more { margin-left: auto; color: var(--muted); font-size: 0.8rem; }

/* COLLAPSED MODE: STRICT HIDE FOR TEXT AND DESCRIPTIVE ELEMENTS */
.sidebar:not(:hover) .logo-text,
.sidebar:not(:hover) .sidebar-section,
.sidebar:not(:hover) .nav-label,
.sidebar:not(:hover) .nav-badge,
.sidebar:not(:hover) .admin-details,
.sidebar:not(:hover) .admin-more {
  display: none !important;
}

.sidebar:not(:hover) .admin-avatar-icon {
  display: flex !important;
  margin: 0 auto;
}

.sidebar:not(:hover) {
  align-items: center;
}

.sidebar:not(:hover) .nav-item {
  width: 44px;
  height: 44px;
  margin: 0.3rem auto;
  padding: 0;
  justify-content: center;
  align-items: center;
}

.sidebar:not(:hover) .nav-icon {
  width: 100%;
  margin: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sidebar:not(:hover) .sidebar-logo {
  width: 100%;
  justify-content: center;
  padding: 0;
}

.sidebar:not(:hover) .admin-pill {
  width: 44px;
  height: 44px;
  margin: 0 auto;
  padding: 0;
  justify-content: center;
  align-items: center;
}

.sidebar:not(:hover) .sidebar-footer {
  width: 100%;
  padding: 0.8rem 0;
  display: flex;
  justify-content: center;
}

/* MAIN CONTENT CONTAINER */
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;           /* Added to lock container height */
  overflow-y: auto;        /* Changed from overflow: hidden to allow main area scrolling */
  min-width: 0;
  background: var(--bg);
}

.topbar {
  height: var(--topbar-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 1.8rem;
  gap: 1.2rem;
  flex-shrink: 0;
  box-sizing: border-box;
}

.topbar-title {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  flex: 1;
}

.topbar-title span {
  color: var(--muted);
  font-weight: 400;
  font-size: 0.85rem;
  font-family: 'DM Sans', sans-serif;
  margin-left: 0.5rem;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0.45rem 1rem;
  width: 220px;
  transition: border-color 0.2s;
}

.search-box:focus-within { border-color: var(--accent); }

.search-box input {
  background: none;
  border: none;
  outline: none;
  color: var(--text);
  font-size: 0.85rem;
  font-family: 'DM Sans', sans-serif;
  width: 100%;
}

.search-box input::placeholder { color: var(--muted); }

.topbar-actions {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  background: var(--card);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.95rem;
  position: relative;
  transition: border-color 0.2s, background 0.2s;
}

.icon-btn:hover {
  border-color: var(--accent);
  background: rgba(56, 189, 248, 0.08);
}

.notif-dot {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--red);
  border: 1.5px solid var(--surface);
}

.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.4rem 1.6rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
}

/* Dashboard: let the Recent Applications card fill the full row height so it
   lines up with the taller right-hand column. */
#page-dashboard .body-grid > .panel {
  display: flex;
  flex-direction: column;
}
#page-dashboard .body-grid > .panel .table-wrap {
  flex: 1;
}

.page {
  display: none;
  flex: 1;
  overflow-y: auto;
  padding: 1.8rem 2rem;
}

.page.active { display: block; }

/* PAGE HEADER & FILTER BUTTONS */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-heading {
  font-family: 'DM Sans', sans-serif;
  font-size: 1.4rem;
  font-weight: 800;
}

.filter-row {
  display: flex;
  gap: 0.6rem;
  margin-bottom: 1.2rem;
  flex-wrap: wrap;
}

.filter-btn {
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.45rem 1.1rem;
  border-radius: 50px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--muted2);
  cursor: pointer;
  transition: all 0.15s ease;
}

.filter-btn:hover, .filter-btn.active {
  border-color: var(--accent);
  background: rgba(2, 132, 199, 0.1);
  color: var(--accent);
}

.btn-new {
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #ffffff;
  font-weight: 700;
  font-size: 0.85rem;
  padding: 0.6rem 1.4rem;
  border-radius: 50px;
  border: none;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: opacity 0.2s ease, transform 0.2s ease;
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
}

.btn-new:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* NOTIFICATIONS & PROFILE DROPDOWNS */
.notif-dropdown {
  position: fixed;
  top: 70px;
  right: 1.5rem;
  width: 340px;
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: 16px;
  z-index: 300;
  display: none !important;
  box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
  animation: slideUp 0.2s;
}

.notif-dropdown.open {
  display: block !important;
}

.notif-head {
  padding: 1rem 1.2rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notif-head-title {
  font-family: 'DM Sans', sans-serif;
  font-weight: 700;
  font-size: 0.9rem;
}

.notif-mark-all {
  font-size: 0.75rem;
  color: var(--accent);
  cursor: pointer;
}

.notif-item {
  display: flex;
  gap: 0.8rem;
  padding: 0.85rem 1.2rem;
  border-bottom: 1px solid rgba(56, 189, 248, 0.05);
  cursor: pointer;
  transition: background 0.15s;
}

.notif-item:hover { background: rgba(56, 189, 248, 0.05); }

.notif-time {
  font-size: 0.7rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.profile-dropdown {
  position: fixed;
  bottom: 90px;
  left: 1rem;
  width: 200px;
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: 14px;
  z-index: 300;
  display: none !important;
  box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
  animation: slideUp 0.2s;
  overflow: hidden;
}

.profile-dropdown.open {
  display: block !important;
}

.pd-item {
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.7rem;
  color: var(--muted2);
  transition: background 0.15s, color 0.15s;
}

.pd-item:hover {
  background: rgba(56, 189, 248, 0.07);
  color: var(--text);
}

.pd-item.danger { color: var(--red); }
.pd-item.danger:hover { background: rgba(248, 113, 113, 0.07); }
.pd-divider { height: 1px; background: var(--border); }

/* DASHBOARD KPI & CARDS */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.2rem;
  margin-bottom: 1.8rem;
}

.kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1.4rem 1.6rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  position: relative;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(2, 132, 199, 0.08);
}

.kpi-val {
  font-family: 'DM Sans', sans-serif;
  font-size: 2.1rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--text);
  margin: 0.4rem 0;
}

.qa-grid-classic {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.qa-card-classic {
  background: var(--card2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text);
  transition: all 0.2s ease;
}

.qa-card-classic:hover {
  background: #e2e8f0;
  border-color: var(--border2);
  transform: translateY(-2px);
}

.qa-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  background: var(--card2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1rem 0.6rem;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--muted2);
  transition: all 0.2s ease;
}

.qa-btn:hover {
  border-color: var(--accent);
  background: rgba(2, 132, 199, 0.08);
  color: var(--accent);
  transform: translateY(-2px);
}

/* Quick-action cards that are not wired up yet. */
.qa-card-classic.qa-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.qa-card-classic.qa-disabled:hover {
  transform: none;
  background: var(--card2);
  border-color: var(--border);
}

.qa-icon {
  font-size: 1.4rem;
}

.feed-item {
  display: flex;
  gap: 0.9rem;
  align-items: flex-start;
  padding: 0.8rem 0.5rem;
  border-bottom: 1px solid var(--card2);
  transition: background 0.15s ease;
  border-radius: 8px;
}

.feed-item:hover {
  background: var(--card2);
}

.feed-icon {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem; 
  flex-shrink: 0;
}

/* TABLES */
.table-wrap { 
  overflow-x: auto; 
}
.table-container { 
  display: block; 
  width: 100%;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead th {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--muted);
  padding: 0.7rem 0.8rem;
  text-align: left;
  border-bottom: 1px solid var(--border);
}

tbody tr {
  border-bottom: 1px solid rgba(203, 213, 225, 0.4);
  transition: background 0.15s;
  cursor: pointer;
}

tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(2, 132, 199, 0.04); }
tbody td { padding: 0.8rem 0.8rem; font-size: 0.84rem; }

.td-mono {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.78rem;
  color: var(--muted2);
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.25rem 0.7rem;
  border-radius: 50px;
}

.status-pill.approved { background: rgba(52, 211, 153, 0.15); color: var(--accent3); }
.status-pill.pending { background: rgba(251, 191, 36, 0.15); color: var(--gold); }
.status-pill.review { background: rgba(56, 189, 248, 0.15); color: var(--accent); }
.status-pill.rejected { background: rgba(248, 113, 113, 0.15); color: var(--red); }

.status-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: currentColor;
}

.loan-type-tag {
  font-size: 0.72rem;
  font-weight: 500;
  padding: 0.2rem 0.6rem;
  border-radius: 6px;
  background: rgba(129, 140, 248, 0.12);
  color: var(--accent2);
  border: 1px solid rgba(129, 140, 248, 0.2);
}

/* MODALS & OVERLAYS */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: none;
  align-items: center;
  justify-content: center;
}

.overlay.open {
  display: flex !important;
}

.modal {
  display: none;
  background: var(--surface);
  color: var(--text);
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--border2);
  border-radius: 20px;
  padding: 2rem;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
  z-index: 1001;
  margin: auto;
}

.modal.open {
  display: block !important;
  animation: slideUp 0.2s ease-out;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.modal-title {
  font-family: 'DM Sans', sans-serif;
  font-weight: 700;
  font-size: 1.1rem;
}

.modal-close {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--card2);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.95rem;
  color: var(--muted);
  transition: all 0.2s;
}

.modal-close:hover {
  color: var(--text);
  border-color: var(--border2);
  background: #e2e8f0;
}

@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

/* FORMS */
.form-group { margin-bottom: 1.2rem; }
.form-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted2);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.5rem;
}

.form-input, .form-select, .form-textarea {
  width: 100%;
  background: var(--card2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0.65rem 1rem;
  color: var(--text);
  font-size: 0.88rem;
  font-family: 'DM Sans', sans-serif;
  outline: none;
  transition: border-color 0.2s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--accent); }
.form-select { cursor: pointer; }
.form-textarea { resize: vertical; min-height: 80px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-actions { display: flex; gap: 0.8rem; margin-top: 1rem; }

.btn-submit {
  width: 100%;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  color: #ffffff;
  font-weight: 700;
  font-size: 0.95rem;
  padding: 0.8rem;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  letter-spacing: 0.02em;
  transition: opacity 0.2s, transform 0.2s;
  margin-top: 0.5rem;
}

.btn-submit:hover { opacity: 0.88; transform: translateY(-1px); }

.btn-cancel {
  width: 100%;
  background: transparent;
  color: var(--muted2);
  font-weight: 500;
  font-size: 0.9rem;
  padding: 0.75rem;
  border-radius: 10px;
  border: 1px solid var(--border);
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: border-color 0.2s, color 0.2s;
  margin-top: 0.5rem;
}

.btn-cancel:hover { border-color: var(--border2); color: var(--text); }

/* Content Body Layout */
.content-body {
  padding: 1.5rem;
  flex: 1;                 /* Added flex growth */
}

/* Panel & Card Containers */
.app-panel {
  background: #ffffff;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border-color, #e2e8f0);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.panel-title {
  font-family: 'DM Sans', sans-serif;
  font-weight: 700;
  font-size: 1.25rem;
  color: var(--text-main, #0f172a);
  margin: 0;
}

.records-count {
  font-size: 0.85rem;
  color: var(--muted, #64748b);
}

.app-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.875rem;
}

.app-table thead tr {
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  color: var(--muted, #64748b);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.app-table th {
  padding: 0.75rem 0.5rem;
}

.app-table tbody tr {
  border-bottom: 1px solid #f1f5f9;
}

.app-table td {
  padding: 0.85rem 0.5rem;
}

.applicant-name {
  font-weight: 600;
}

.td-date {
  color: var(--muted, #64748b);
}

.btn-review {
  background: transparent;
  border: 1px solid var(--border-color, #cbd5e1);
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn-review:hover {
  background: #f8fafc;
}

.empty-table-msg {
  text-align: center;
  color: var(--muted, #64748b);
  padding: 2.5rem 0;
}

.search-icon {
  color: var(--muted, #64748b);
  font-size: 0.85rem;
}

/* TOAST NOTIFICATION CONTAINER (TOP-CENTER) */
.toast-container {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  align-items: center;
  pointer-events: none;
}

.toast {
  pointer-events: auto;
  min-width: 280px;
  background: var(--surface, #ffffff);
  color: var(--text, #0f172a);
  border: 1px solid var(--border, #cbd5e1);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
  border-radius: 12px;
  padding: 0.75rem 1.2rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.88rem;
  font-weight: 600;
  animation: toastSlideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast.error {
  border-left: 4px solid var(--red, #dc2626);
}

.toast.success {
  border-left: 4px solid var(--accent3, #059669);
}

.toast.info {
  border-left: 4px solid var(--accent, #0284c7);
}

.toast-msg {
  flex: 1;
}

.toast-close {
  cursor: pointer;
  color: var(--muted, #64748b);
  font-size: 0.85rem;
  margin-left: 0.5rem;
  transition: color 0.15s;
}

.toast-close:hover {
  color: var(--text, #0f172a);
}

@keyframes toastSlideDown {
  from {
    transform: translateY(-20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* ═══ RESPONSIVE ═══ */
@media (max-width: 1024px) {
  :root { --sidebar-w: 68px; }

  .sidebar { width: 68px !important; }
  .sidebar:hover { width: 68px !important; overflow-y: auto; }
  .sidebar .logo-text,
  .sidebar .sidebar-section,
  .sidebar .nav-label,
  .sidebar .nav-badge,
  .sidebar .admin-details,
  .sidebar .admin-name,
  .sidebar .admin-more { display: none !important; }
  .sidebar .admin-avatar-icon { display: flex !important; margin: 0 auto; }
  .sidebar .nav-item { width: 44px; height: 44px; margin: 0.3rem auto; padding: 0; justify-content: center; }
  .sidebar .nav-icon { width: 100%; margin: 0; }
  .sidebar .sidebar-logo { width: 100%; justify-content: center; padding: 0; }
  .sidebar .admin-pill { width: 44px; height: 44px; margin: 0 auto; padding: 0; justify-content: center; }
  .sidebar .sidebar-footer { width: 100%; padding: 0.8rem 0; display: flex; justify-content: center; }

  .main { padding-left: 68px; }

  .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
  .kpi-val { font-size: 1.6rem; }

  .body-grid { grid-template-columns: 1fr !important; }
  .body-grid .panel { min-width: 0; }
}

@media (max-width: 768px) {
  .topbar { padding: 0 1rem; gap: 0.6rem; }
  .search-box { width: 120px; }
  .search-box input { font-size: 0.75rem; }
  .page { padding: 1rem 0.8rem; }
  .kpi-grid { grid-template-columns: 1fr 1fr; gap: 0.8rem; }
  .kpi-card { padding: 1rem; }
  .kpi-val { font-size: 1.35rem; }
  .qa-grid-classic { grid-template-columns: 1fr 1fr; gap: 0.6rem; }
  .qa-card-classic { padding: 1rem 0.6rem; font-size: 0.75rem; }
  .panel { padding: 1rem; }
  .filter-row { gap: 0.4rem; }
  .filter-btn { font-size: 0.7rem; padding: 0.35rem 0.8rem; }
  .btn-new { font-size: 0.75rem; padding: 0.5rem 1rem; }
  .topbar-title { font-size: 0.95rem; }
  .page-header { flex-wrap: wrap; gap: 0.5rem; }
}

@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
  .qa-grid-classic { grid-template-columns: 1fr; }
  .topbar { padding: 0 0.6rem; gap: 0.4rem; height: 52px; }
  .search-box { width: 100px; }
  .search-box input::placeholder { font-size: 0.65rem; }
  .page { padding: 0.6rem; }
  .kpi-val { font-size: 1.2rem; }
  .panel { padding: 0.8rem; border-radius: 12px; }
  table { font-size: 0.72rem; }
  thead th { font-size: 0.62rem; padding: 0.5rem 0.4rem; }
  tbody td { padding: 0.5rem 0.4rem; font-size: 0.72rem; }
  .btn-review { padding: 2px 6px; font-size: 0.65rem; }
  .status-pill { font-size: 0.6rem; padding: 0.15rem 0.5rem; }
  .loan-type-tag { font-size: 0.6rem; }
  .topbar-title { font-size: 0.82rem; }
  .icon-btn { width: 28px; height: 28px; font-size: 0.8rem; }
}
</style>