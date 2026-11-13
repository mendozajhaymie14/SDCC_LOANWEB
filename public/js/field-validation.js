/*
 * SDCC field validation (client side)
 *
 * Mirrors the server rules so users get the same answer before submitting:
 *   phone     09 + 9 digits (a 10-digit number starting with 9 is accepted, like PhilippineMobileNumber)
 *   email     basic address format
 *   positive  number greater than zero (monthly income, loan amount)
 *   money     number of zero or more (savings, obligations, credit limit)
 *   name      letters, spaces and . , - '
 *   required  any other required field must not be empty
 *
 * Valid -> green border + checkmark. Invalid -> red border + message under the field.
 * Hovering a field shows a tooltip with the expected format.
 * Submitting is held until every visible field passes. The server still validates everything.
 *
 * Fields are picked up automatically from type/name, or set explicitly with
 * data-validate="phone|email|positive|money|name". Opt out with data-no-validate.
 * Works for fields added later (modals) because it listens on the document.
 */
(function () {
  'use strict';
  if (window.FieldValidation) return;

  var SKIP = /^(hidden|password|submit|button|reset|image|range|color)$/;
  var PHONE_NAMES = /^(phone|contact_number|contact)$/;
  var POSITIVE_NAMES = /^(monthly_income|amount)$/;
  var MONEY_NAMES = /^(existing_monthly_obligations|savings_balance|approved_credit_limit)$/;
  var NAME_NAMES = /^(name|full_name|first_name|middle_name|surname|last_name|applicant)$/;
  var INCOME_WARNING = 0.7; // same threshold as LoanEvaluationService::INCOME_WARNING_THRESHOLD

  var MESSAGES = {
    phone: 'Contact number must be 11 digits starting with 09.',
    email: 'Enter a valid email address, for example juan@gmail.com.',
    positive: 'Enter an amount greater than zero.',
    money: 'Enter 0 or a positive amount.',
    name: 'Use letters, spaces, and . , - \' only.',
    required: 'This field is required.'
  };

  var TOOLTIPS = {
    phone: 'Contact number must be 11 digits starting with 09, for example 09171234567.',
    email: 'Use a valid email address, for example juan@gmail.com.',
    positive: 'Enter an amount greater than zero.',
    money: 'Enter 0 or a positive amount.',
    name: 'Letters, spaces, and . , - \' only.'
  };

  var seq = 0;

  function isVisible(el) {
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function isRequired(el) {
    return el.required || el.hasAttribute('data-required');
  }

  function resolveType(el) {
    if (!el || !el.tagName || !/^(INPUT|SELECT|TEXTAREA)$/.test(el.tagName)) return null;
    if (el.hasAttribute('data-no-validate') || el.disabled || el.readOnly) return null;
    var t = (el.type || '').toLowerCase();
    if (SKIP.test(t)) return null;
    var name = el.name || '';

    if (MONEY_NAMES.test(name)) return 'money'; // server allows 0 for these
    var declared = el.getAttribute('data-validate');
    if (declared && MESSAGES[declared]) return declared;

    if (t === 'email') return 'email';
    if (PHONE_NAMES.test(name)) return 'phone';
    if (POSITIVE_NAMES.test(name)) return 'positive';
    if (NAME_NAMES.test(name) && t === 'text') return 'name';
    if (isRequired(el)) return 'required';
    return null;
  }

  function peso(n) {
    return '\u20B1' + Number(n).toLocaleString('en-PH');
  }

  function bad(msg) { return { state: 'invalid', msg: msg }; }

  function check(el, type) {
    var t = (el.type || '').toLowerCase();

    if (t === 'checkbox' || t === 'radio') {
      if (!isRequired(el)) return { state: 'empty' };
      var ok = t === 'checkbox'
        ? el.checked
        : !!(el.form && el.form.querySelector('input[type="radio"][name="' + el.name + '"]:checked'));
      return ok ? { state: 'valid' } : bad('Please select this option to continue.');
    }
    if (t === 'file') {
      if (!isRequired(el)) return { state: 'empty' };
      return el.files && el.files.length ? { state: 'valid' } : bad('Please attach a file.');
    }

    if (t === 'number' && el.validity && el.validity.badInput) return bad('Enter numbers only.');

    var v = String(el.value == null ? '' : el.value).trim();
    if (v === '') {
      return isRequired(el) ? bad(MESSAGES.required) : { state: 'empty' };
    }

    switch (type) {
      case 'phone': {
        var d = v.replace(/[^0-9]/g, '');
        if (/^9\d{9}$/.test(d)) d = '0' + d;
        return /^09\d{9}$/.test(d) ? { state: 'valid' } : bad(MESSAGES.phone);
      }
      case 'email':
        return checkEmail(v);
      case 'name':
        if (v.length < 2) return bad('Enter at least 2 letters.');
        return /^[A-Za-z\u00C0-\u024F][A-Za-z\u00C0-\u024F .,'\u2019-]*$/.test(v) ? { state: 'valid' } : bad(MESSAGES.name);
      case 'positive':
      case 'money': {
        var n = Number(v);
        if (!isFinite(n)) return bad('Enter numbers only.');
        if (type === 'positive' && n <= 0) return bad(MESSAGES.positive);
        if (type === 'money' && n < 0) return bad(MESSAGES.money);
        if (el.min !== '' && n < Number(el.min)) return bad('Enter at least ' + peso(el.min) + '.');
        if (el.max !== '' && n > Number(el.max)) return bad('Enter no more than ' + peso(el.max) + '.');
        return { state: 'valid' };
      }
      default:
        return { state: 'valid' };
    }
  }

  // Common generic endings. Any 2-letter ending is accepted as a country code (.ph, .jp, .uk ...).
  var GENERIC_TLDS = /^(com|net|org|edu|gov|mil|int|info|biz|name|pro|online|site|tech|app|dev|xyz|store|shop|email|cloud)$/;
  // Big providers only have one real domain, so a near-miss is almost certainly a typo.
  var PROVIDERS = { gmail: 'gmail.com', hotmail: 'hotmail.com', outlook: 'outlook.com', icloud: 'icloud.com' };

  function checkEmail(v) {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return bad(MESSAGES.email);
    var domain = v.slice(v.lastIndexOf('@') + 1).toLowerCase();
    var labels = domain.split('.');
    var tld = labels[labels.length - 1];
    if (!(/^[a-z]{2}$/.test(tld) || GENERIC_TLDS.test(tld))) {
      return bad('"' + domain + '" does not look like a real email domain.');
    }
    var brand = labels[0];
    if (PROVIDERS[brand] && domain !== PROVIDERS[brand]) {
      return bad('"' + domain + '" is not a real address. Did you mean ' + PROVIDERS[brand] + '?');
    }
    return { state: 'valid' };
  }

  function msgHost(el) {
    var t = (el.type || '').toLowerCase();
    return (t === 'checkbox' || t === 'radio') ? (el.closest('label') || el) : el;
  }

  function getMsg(el, create) {
    if (el._fvMsg && el._fvMsg.isConnected) return el._fvMsg;
    if (!create) return null;
    var m = document.createElement('div');
    m.className = 'fv-msg';
    m.id = 'fv-msg-' + (++seq);
    m.setAttribute('role', 'alert');
    msgHost(el).insertAdjacentElement('afterend', m);
    el._fvMsg = m;
    return m;
  }

  function clear(el) {
    el.classList.remove('fv-valid', 'fv-invalid', 'fv-warn');
    el.removeAttribute('aria-invalid');
    var m = getMsg(el, false);
    if (m) m.remove();
    el._fvMsg = null;
    if (el.getAttribute('aria-describedby') && /^fv-msg-/.test(el.getAttribute('aria-describedby'))) {
      el.removeAttribute('aria-describedby');
    }
  }

  function render(el, res) {
    if (res.state === 'empty') { clear(el); return; }

    el.classList.remove('fv-valid', 'fv-invalid', 'fv-warn');

    if (res.state === 'invalid') {
      el.classList.add('fv-invalid');
      el.setAttribute('aria-invalid', 'true');
      var m = getMsg(el, true);
      m.textContent = res.msg;
      m.classList.remove('is-warn');
      el.setAttribute('aria-describedby', m.id);
      return;
    }

    el.removeAttribute('aria-invalid');
    if (res.warn) {
      el.classList.add('fv-warn');
      var w = getMsg(el, true);
      w.textContent = res.warn;
      w.classList.add('is-warn');
      el.setAttribute('aria-describedby', w.id);
      return;
    }

    el.classList.add('fv-valid');
    var old = getMsg(el, false);
    if (old) old.remove();
    el._fvMsg = null;
    el.removeAttribute('aria-describedby');
  }

  // Logical check across fields: requested amount vs monthly income (warning only, never blocks).
  function incomeWarning(el) {
    if (el.name !== 'amount' || !el.form) return null;
    var inc = el.form.querySelector('[name="monthly_income"]');
    if (!inc) return null;
    var income = Number(inc.value), amount = Number(el.value);
    if (!(income > 0) || !(amount > 0)) return null;
    if (amount > income * INCOME_WARNING) {
      return 'Requested amount is over 70% of monthly income. Extra verification will be needed.';
    }
    return null;
  }

  function validateEl(el, touch, noCascade) {
    var type = resolveType(el);
    if (!type) return null;
    if (touch) el.setAttribute('data-fv-touched', '1');
    if (!el.hasAttribute('data-fv-touched')) return check(el, type);

    var res = check(el, type);
    if (res.state === 'valid') {
      var warn = incomeWarning(el);
      if (warn) res.warn = warn;
    }
    render(el, res);

    if (!noCascade && el.name === 'monthly_income' && el.form) {
      var amt = el.form.querySelector('[name="amount"]');
      if (amt && amt.hasAttribute('data-fv-touched')) validateEl(amt, false, true);
    }
    return res;
  }

  function clearForm(form) {
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('input,select,textarea').forEach(function (el) {
      el.removeAttribute('data-fv-touched');
      clear(el);
    });
  }

  // Tooltip with the expected format, set the first time a field is focused or hovered.
  function addTooltip(el) {
    var type = resolveType(el);
    if (!type || el.title || el.hasAttribute('data-fv-titled')) return;
    if (TOOLTIPS[type]) {
      el.title = TOOLTIPS[type];
      el.setAttribute('data-fv-titled', '1');
    }
  }

  document.addEventListener('mouseover', function (e) { addTooltip(e.target); });
  document.addEventListener('focusin', function (e) { addTooltip(e.target); });
  document.addEventListener('focusout', function (e) { validateEl(e.target, true); });
  document.addEventListener('change', function (e) { validateEl(e.target, true); });
  document.addEventListener('input', function (e) {
    if (e.target.hasAttribute && e.target.hasAttribute('data-fv-touched')) validateEl(e.target, false);
  });
  document.addEventListener('reset', function (e) {
    var f = e.target;
    setTimeout(function () { clearForm(f); }, 0);
  });

  // Replace the browser's own pop-up bubbles with our inline messages.
  // Native validation still blocks submit; we only change how it is shown.
  document.addEventListener('invalid', function (e) {
    var el = e.target;
    e.preventDefault();
    var type = resolveType(el);
    var res = type ? check(el, type) : { state: 'valid' };
    if (res.state !== 'invalid') res = bad(el.validationMessage || MESSAGES.required);
    el.setAttribute('data-fv-touched', '1');
    render(el, res);

    var form = el.form;
    if (form && !form._fvFocusing) {
      form._fvFocusing = true;
      setTimeout(function () {
        form._fvFocusing = false;
        el.focus({ preventScroll: true });
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }, 0);
    }
  }, true);

  // Hold the submit until every visible field passes our rules (the ones the browser can't check).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    var first = null;
    form.querySelectorAll('input,select,textarea').forEach(function (el) {
      if (!isVisible(el)) return;
      var res = validateEl(el, true);
      if (res && res.state === 'invalid' && !first) first = el;
    });
    if (first) {
      e.preventDefault();
      e.stopPropagation(); // keeps other submit handlers (loading spinners etc.) from running
      first.focus({ preventScroll: true });
      first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  }, true);

  // Modals fill their fields from JS. Re-check fields that already show a state so
  // nothing stale (red or green) is left over from the previous record.
  document.addEventListener('click', function () {
    setTimeout(function () {
      document.querySelectorAll('.fv-valid,.fv-invalid,.fv-warn').forEach(function (el) {
        if (isVisible(el)) validateEl(el, false);
      });
    }, 80);
  });

  window.FieldValidation = { validate: function (el) { return validateEl(el, true); }, reset: clearForm };
})();
