@extends('admin.index')

@section('admin_content')
<div style="padding: 1.5rem; width: 100%; max-width: 1200px;">

    @if(session('error'))
      <div style="padding:10px 12px; border:1px solid #fca5a5; background:#fef2f2; color:#991b1b; border-radius:6px; margin-bottom:12px;">{{ session('error') }}</div>
    @endif
    @if(session('success'))
      <div style="padding:10px 12px; border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:6px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif

    <div class="panel app-panel">
      <div class="page-header">
        <div>
          <h2 class="panel-title">Encode Loan Application</h2>
          <span class="records-count">Type in a member's application. It is evaluated by the same engine and queued in the same table as a member's own submission.</span>
        </div>
        <a href="{{ route('applications.index') }}" class="btn-review">← Back to Applications</a>
      </div>

      <form action="{{ route('applications.encode.store') }}" method="POST">
        @csrf

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:12px;">
          <label style="font-size:0.75rem;">Member
            <input class="form-input" type="text" id="memberSearch" placeholder="Search members by name or member ID…" autocomplete="off" />
            <select class="form-select" name="member_id" id="memberPicker" required style="margin-top:6px;">
              <option value="">— Select a member —</option>
              @foreach($members as $m)
                <option value="{{ $m->id }}"
                        data-income="{{ $m->monthly_income ?? '' }}"
                        data-obligations="{{ $m->existing_monthly_obligations ?? '' }}"
                        data-share="{{ $m->share_capital ?? '' }}"
                        data-savings="{{ $m->savings_balance ?? '' }}"
                        data-credit="{{ $m->approved_credit_limit ?? '' }}">
                  {{ $m->full_name }} ({{ $m->member_id }})
                </option>
              @endforeach
            </select>
            @error('member_id')<div style="color:#b91c1c;font-size:0.75rem;margin-top:4px;">{{ $message }}</div>@enderror
          </label>

          <label style="font-size:0.75rem;">Loan Product
            <select class="form-select" name="loan_type" id="loanType" required>
              @foreach(config('loan_matrix.loan_types') as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
            @error('loan_type')<div style="color:#b91c1c;font-size:0.75rem;margin-top:4px;">{{ $message }}</div>@enderror
          </label>

          <label style="font-size:0.75rem;">Amount (₱)
            <input class="form-input" type="number" step="0.01" min="1" name="amount" value="{{ old('amount') }}" required />
            @error('amount')<div style="color:#b91c1c;font-size:0.75rem;margin-top:4px;">{{ $message }}</div>@enderror
          </label>

          <label style="font-size:0.75rem;">Payment Term (months)
            <select class="form-select" name="term_months" id="termSelect" required></select>
            @error('term_months')<div style="color:#b91c1c;font-size:0.75rem;margin-top:4px;">{{ $message }}</div>@enderror
          </label>

          <label style="font-size:0.75rem;">Purpose
            <input class="form-input" type="text" name="purpose" maxlength="255" value="{{ old('purpose') }}" required />
            @error('purpose')<div style="color:#b91c1c;font-size:0.75rem;margin-top:4px;">{{ $message }}</div>@enderror
          </label>
        </div>

        <h3 style="font-size:0.95rem; margin: 16px 0 6px;">Member Financial Profile (read from the member record)</h3>
        <table class="app-table">
          <tbody>
            <tr><td><strong>Monthly income</strong></td><td id="mIncome">—</td></tr>
            <tr><td><strong>Existing monthly obligations</strong></td><td id="mObligations">—</td></tr>
            <tr><td><strong>Share capital</strong></td><td id="mShare">—</td></tr>
            <tr><td><strong>Savings balance</strong></td><td id="mSavings">—</td></tr>
            <tr><td><strong>Approved credit limit</strong></td><td id="mCredit">—</td></tr>
          </tbody>
        </table>

        <button type="submit" class="btn-review" style="margin-top:12px;">Encode Application</button>
      </form>
    </div>

</div>

<script>
  const TERMS = @json(
    collect(config('loan_matrix.matrix'))->map(fn ($m) => $m['terms'] ?? [12])->all()
  );

  const loanType = document.getElementById('loanType');
  const termSelect = document.getElementById('termSelect');
  const memberPicker = document.getElementById('memberPicker');
  const memberSearch = document.getElementById('memberSearch');

  function refreshTerms() {
    const terms = TERMS[loanType.value] || [12];
    termSelect.innerHTML = '';
    terms.forEach(function (t) {
      const opt = document.createElement('option');
      opt.value = t;
      opt.textContent = t + ' months';
      termSelect.appendChild(opt);
    });
  }

  function money(v) {
    if (v === '' || v === null || v === undefined) return '—';
    return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  function fillProfile() {
    const opt = memberPicker.options[memberPicker.selectedIndex];
    if (!opt) return;
    document.getElementById('mIncome').textContent     = money(opt.dataset.income);
    document.getElementById('mObligations').textContent = money(opt.dataset.obligations);
    document.getElementById('mShare').textContent      = money(opt.dataset.share);
    document.getElementById('mSavings').textContent    = money(opt.dataset.savings);
    document.getElementById('mCredit').textContent     = money(opt.dataset.credit);
  }

  // Searchable member picker: type in the box to filter the dropdown.
  memberSearch.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    Array.from(memberPicker.options).forEach(function (opt) {
      if (opt.value === '') { opt.style.display = ''; return; }
      opt.style.display = opt.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  memberPicker.addEventListener('change', fillProfile);

  loanType.addEventListener('change', refreshTerms);
  refreshTerms();
</script>
@endsection