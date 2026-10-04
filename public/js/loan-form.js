(function () {
  // ─── Remove spinner arrows + disable mouse wheel on number inputs ───
  document.querySelectorAll('input[type="number"]').forEach(function (input) {
    // Hide spinner arrows (Chrome, Safari, Edge, Firefox)
    input.style.mozAppearance = 'textfield';
    input.addEventListener('mousewheel', function (e) { e.preventDefault(); }, { passive: false });
  });
  var style = document.createElement('style');
  style.textContent = 'input[type="number"]::-webkit-outer-spin-button, ' +
                      'input[type="number"]::-webkit-inner-spin-button { ' +
                      '-webkit-appearance: none; margin: 0; } ' +
                      'input[type="number"] { -moz-appearance: textfield; }';
  document.head.appendChild(style);

  // ─── Mobile number: digits only, exactly 11 digits ───
  var mobileInput = document.getElementById('contact_number');
  if (mobileInput) {
    mobileInput.addEventListener('input', function () {
      var cleaned = mobileInput.value.replace(/\D/g, '');
      if (cleaned.length > 11) { cleaned = cleaned.slice(0, 11); }
      mobileInput.value = cleaned;
    });
    mobileInput.addEventListener('blur', function () {
      if (mobileInput.value && mobileInput.value.length !== 11) {
        mobileInput.setCustomValidity('Please enter exactly 11 digits.');
      } else {
        mobileInput.setCustomValidity('');
      }
    });
  }

  // ─── Amount: digits only, max 7 characters ───
  var amountInput = document.getElementById('amount');
  if (amountInput) {
    amountInput.addEventListener('input', function () {
      var cleaned = amountInput.value.replace(/\D/g, '');
      if (cleaned.length > 7) { cleaned = cleaned.slice(0, 7); }
      amountInput.value = cleaned;
      updateEstimate();
    });
  }


  // ─── Hide/show employer name based on member status ───
  var memberStatus = document.getElementById('member_status');
  var employerField = document.getElementById('employerNameField');

  if (memberStatus && employerField) {
    function toggleEmployer() {
      var val = memberStatus.value;
      if (val === 'Student') {
        employerField.style.display = 'none';
        var input = employerField.querySelector('input');
        if (input) { input.removeAttribute('name'); input.removeAttribute('required'); }
      } else {
        employerField.style.display = '';
        var input = employerField.querySelector('input');
        if (input) { input.setAttribute('name', 'employer_name'); }
      }
    }
    memberStatus.addEventListener('change', toggleEmployer);
    toggleEmployer();
  }

  // ─── SDCC Loan Matrix: drive limits, terms, collateral, and requirements ───
  var MATRIX = window.SDCC_LOAN_MATRIX || {};
  var loanType = document.getElementById('loan_type');
  var amountHint = document.getElementById('amountHint');
  var termSelect = document.getElementById('term_months');
  var collateralSelect = document.getElementById('collateral');
  var requirementsPanel = document.getElementById('requirementsPanel');

  function money(value) {
    return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function productConfig() {
    return MATRIX[loanType ? loanType.value : ''] || {};
  }

  function rebuildTermOptions(terms) {
    if (!termSelect) return;
    termSelect.innerHTML = '';
    var defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Select term';
    termSelect.appendChild(defaultOption);

    (terms || []).forEach(function (term) {
      var opt = document.createElement('option');
      opt.value = term;
      opt.textContent = term + ' months';
      termSelect.appendChild(opt);
    });
  }

  function renderRequirements(config) {
    if (!requirementsPanel) return;
    var requirements = config.requirements || [];
    if (!requirements.length) {
      requirementsPanel.innerHTML = '<p class="requirements-hint">No special documents are listed for this product.</p>';
      return;
    }
    requirementsPanel.innerHTML = '<ul class="requirements-list">' + requirements.map(function (item) {
      return '<li>' + item + '</li>';
    }).join('') + '</ul>';
  }

  function updateMatrixFields() {
    var config = productConfig();
    if (!config) {
      return;
    }

    // Amount limit
    var maxAmount = config.max_amount || 2000000;
    maxAmount = Math.min(maxAmount, 2000000);
    amountInput.max = maxAmount;
    amountInput.min = maxAmount < 1000 ? 1 : 1000;
    amountHint.textContent = '(' + money(amountInput.min) + ' – ' + money(maxAmount) + ')';

    // Terms
    rebuildTermOptions(config.terms || [12]);
    if (termSelect.value && (config.terms || []).indexOf(Number(termSelect.value)) === -1) {
      termSelect.value = '';
    }

    // Collateral
    if (collateralSelect) {
      var options = config.collateral_options || ['None'];
      collateralSelect.innerHTML = '';
      options.forEach(function (value) {
        var opt = document.createElement('option');
        opt.value = value;
        opt.textContent = value === 'None' ? 'None required' : value;
        collateralSelect.appendChild(opt);
      });
      collateralSelect.required = options.indexOf('None') === -1;
    }

    // Requirements
    renderRequirements(config);
  }

  if (loanType) {
    loanType.addEventListener('change', updateMatrixFields);
    updateMatrixFields();
  }

  // ─── Estimate: calculate monthly repayment from amount + term + product ───
  function updateEstimate() {
    var amount = parseInt(amountInput ? amountInput.value.replace(/\D/g, '') : '0', 10);
    var term = termSelect ? parseInt(termSelect.value, 10) : 0;
    var config = productConfig();
    var rate = config.interest_rate || 0.01;

    var estMonthlyEl = document.getElementById('estMonthly');
    var estPrincipalEl = document.getElementById('estPrincipal');
    var estInterestEl = document.getElementById('estInterest');
    var estTotalEl = document.getElementById('estTotal');
    var statusText = document.getElementById('statusText');
    var estimateStatus = document.getElementById('estimateStatus');
    var estRateLabel = document.getElementById('estRateLabel');

    if (!amount || !term || !config) {
      if (estMonthlyEl) estMonthlyEl.textContent = '₱0.00';
      if (estPrincipalEl) estPrincipalEl.textContent = '₱0.00';
      if (estInterestEl) estInterestEl.textContent = '₱0.00';
      if (estTotalEl) estTotalEl.textContent = '₱0.00';
      if (statusText) statusText.textContent = 'Enter amount and term';
      if (estimateStatus) estimateStatus.classList.remove('active');
      return;
    }

    var monthlyRate = rate / 12;
    var power = Math.pow(1 + monthlyRate, term);
    var monthlyPayment = amount * monthlyRate * power / (power - 1);
    var totalPayable = monthlyPayment * term;
    var totalInterest = totalPayable - amount;

    if (estMonthlyEl) estMonthlyEl.textContent = money(monthlyPayment);
    if (estPrincipalEl) estPrincipalEl.textContent = money(amount);
    if (estInterestEl) estInterestEl.textContent = money(totalInterest);
    if (estTotalEl) estTotalEl.textContent = money(totalPayable);

    if (estRateLabel) {
      estRateLabel.textContent = 'Estimated interest';
    }

    if (statusText) {
      statusText.textContent = 'Updated just now';
    }
    if (estimateStatus) {
      estimateStatus.classList.add('active');
    }
  }

  // ─── Wire estimate to amount + term changes ───
  if (amountInput) {
    amountInput.addEventListener('input', updateEstimate);
  }
  if (termSelect) {
    termSelect.addEventListener('change', updateEstimate);
  }
  if (loanType) {
    loanType.addEventListener('change', updateEstimate);
  }

  // ─── Document upload: accept JPG, PNG, PDF only ───
  var documentUpload = document.getElementById('required_documents');
  var filePreview = document.getElementById('filePreview');
  var fileDropzone = document.getElementById('fileDropzone');
  if (documentUpload) {
    documentUpload.addEventListener('change', function () {
      renderFilePreviews(documentUpload.files);
    });
    if (fileDropzone) {
      ['dragover', 'dragenter'].forEach(function (evt) {
        fileDropzone.addEventListener(evt, function (e) { e.preventDefault(); fileDropzone.classList.add('dragover'); });
      });
      ['dragleave', 'drop'].forEach(function (evt) {
        fileDropzone.addEventListener(evt, function (e) { e.preventDefault(); fileDropzone.classList.remove('dragover'); });
      });
      fileDropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files) {
          var dt = new DataTransfer();
          for (var i = 0; i < e.dataTransfer.files.length; i++) dt.items.add(e.dataTransfer.files[i]);
          documentUpload.files = dt.files;
          renderFilePreviews(documentUpload.files);
        }
      });
    }
  }

  function renderFilePreviews(files) {
    if (!filePreview) return;
    filePreview.innerHTML = '';
    var allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    for (var i = 0; i < files.length; i++) {
      (function (idx) {
        var f = files[idx];
        var ext = f.name.split('.').pop().toLowerCase();
        if (allowed.indexOf(ext) === -1) return;
        var item = document.createElement('div');
        item.className = 'file-preview-item';
        if (f.type.indexOf('image') === 0) {
          var img = document.createElement('img');
          img.src = URL.createObjectURL(f);
          item.appendChild(img);
        } else {
          var icon = document.createElement('div');
          icon.style.cssText = 'width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#e2f0e4;color:#1a5c2a;font-weight:700;font-size:11px;border-radius:8px;';
          icon.textContent = ext.toUpperCase();
          item.appendChild(icon);
        }
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'remove';
        remove.textContent = '×';
        remove.title = 'Remove file';
        remove.addEventListener('click', function () {
          var dt = new DataTransfer();
          for (var j = 0; j < files.length; j++) {
            if (j !== idx) dt.items.add(files[j]);
          }
          documentUpload.files = dt.files;
          renderFilePreviews(documentUpload.files);
        });
        item.appendChild(remove);
        filePreview.appendChild(item);
      })(i);
    }
  }

  // ─── Stepper / progress indicator ───
  var loanForm = document.getElementById('loanForm');
  var stepper = document.getElementById('formStepper');
  var steps = stepper ? stepper.querySelectorAll('.step') : [];
  var currentStep = 1;
  function updateStepper() {
    steps.forEach(function (s) {
      var n = parseInt(s.dataset.step, 10);
      s.classList.toggle('active', n === currentStep);
      s.classList.toggle('done', n < currentStep);
    });
  }
  if (stepper) {
    steps.forEach(function (s) {
      s.addEventListener('click', function () {
        var n = parseInt(s.dataset.step, 10);
        if (n > currentStep) return;
        currentStep = n;
        updateStepper();
      });
    });

    // Update the active step as the user scrolls through the form
    var stepSections = loanForm.querySelectorAll('fieldset[data-step]');
    if ('IntersectionObserver' in window && stepSections.length) {
      var stepObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            currentStep = parseInt(entry.target.dataset.step, 10);
            updateStepper();
          }
        });
      }, { root: null, rootMargin: '-30% 0px -40% 0px', threshold: 0 });
      stepSections.forEach(function (sec) { stepObserver.observe(sec); });
    }
  }

  // ─── Persist step in sessionStorage so accidental reloads restore position ───
  if (typeof sessionStorage !== 'undefined') {
    // Restore current step on load
    var savedStep = sessionStorage.getItem('sdcc_loan_step');
    if (savedStep && parseInt(savedStep, 10) <= steps.length) {
      currentStep = parseInt(savedStep, 10);
      updateStepper();
    }
    function saveStep() {
      sessionStorage.setItem('sdcc_loan_step', String(currentStep));
    }
    // Hook into updateStepper to persist
    var _origUpdateStepper = updateStepper;
    updateStepper = function () {
      _origUpdateStepper();
      saveStep();
    };
    // Clear step on successful submit
    loanForm.addEventListener('submit', function () {
      sessionStorage.removeItem('sdcc_loan_step');
    });
  }
})();