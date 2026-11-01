/**
 * Shared validation script for Philippine mobile numbers and positive numbers.
 * Applies to all forms with data-validate attributes: phone, email, positive.
 * Shows green check border + tooltip on valid, red border + message on invalid.
 * Disables submit button while any field is invalid.
 *
 * Usage: add data-validate="phone|email|positive" to any input field.
 * Tooltip texts are defined by data-tooltip-phone, data-tooltip-email, data-tooltip-positive
 * or fall back to defaults.
 */

document.addEventListener('DOMContentLoaded', function () {
  // Tooltip text defaults
  const tooltips = {
    phone: 'Mobile number must be exactly 11 digits starting with 09 (e.g. 09171234567).',
    email: 'Please enter a valid email address.',
    positive: 'Please enter a positive number.',
  };

  // Initialize all fields with data-validate attribute
  const validatableFields = document.querySelectorAll('[data-validate]');

  validatableFields.forEach(function (field) {
    const validateType = field.getAttribute('data-validate');
    const tooltipEl = field.dataset.tooltip
      ? document.getElementById(field.dataset.tooltip)
      : null;

    // Show/hide validation state
    function validateField() {
      const value = field.value.trim();
      const isValid = validateFieldValue(value, validateType);
      const parent = field.parentElement;

      // Remove any existing validation classes/messages
      if (parent) {
        parent.querySelectorAll('.validation-tooltip, .green-check, .red-error').forEach(function (el) {
          el.remove();
        });
        parent.classList.remove('valid', 'invalid');
      }

      if (isValid !== null) {
        if (isValid) {
          // Valid: green border + checkmark + tooltip
          if (parent) {
            parent.classList.add('valid');
          }
          // Add green checkmark after the input
          const existingCheck = field.nextSibling;
          const checkmark = document.createElement('span');
          checkmark.className = 'green-check';
          checkmark.innerHTML = '✓';
          checkmark.style.cssText = `
            display: inline-block;
            margin-left: 8px;
            font-size: 1.2em;
            color: #10b981;
          `;
          // Insert after input but before next sibling
          field.parentElement.insertBefore(checkmark, field.nextSibling);

          // Add tooltip if specified
          if (tooltips[validateType] && !tooltipEl) {
            const tooltip = document.createElement('span');
            tooltip.className = 'validation-tooltip';
            tooltip.textContent = tooltips[validateType];
            tooltip.style.cssText = `
              margin-top: 4px;
              font-size: 0.75rem;
              color: #10b981;
              display: block;
            `;
            field.parentElement.appendChild(tooltip);
          }
        } else {
          // Invalid: red border + error message + tooltip
          if (parent) {
            parent.classList.add('invalid');
          }
          // Add error message after input
          const errorMsg = document.createElement('span');
          errorMsg.className = 'red-error';
          errorMsg.textContent = getErrorMessage(value, validateType);
          errorMsg.style.cssText = `
            margin-top: 4px;
            font-size: 0.75rem;
            color: #ef4444;
            display: block;
          `;
          field.parentElement.appendChild(errorMsg);

          // Add tooltip if specified
          if (tooltips[validateType] && !tooltipEl) {
            const tooltip = document.createElement('span');
            tooltip.className = 'validation-tooltip';
            tooltip.textContent = tooltips[validateType];
            tooltip.style.cssText = `
              margin-top: 4px;
              font-size: 0.75rem;
              color: #ef4444;
              display: block;
            `;
            field.parentElement.appendChild(tooltip);
          }
        }
      }

      // Update submit button state
      updateSubmitButton();
    }

    // Validate field value based on type
    function validateFieldValue(value, type) {
      switch (type) {
        case 'phone':
          // Must be 11 digits starting with 09
          return /^09\d{9}$/.test(value.replace(/[^0-9]/g, ''));
        case 'email':
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        case 'positive':
          return value !== '' && !isNaN(value) && parseFloat(value) > 0;
        default:
          return null;
      }
    }

    // Get error message based on type and value
    function getErrorMessage(value, type) {
      switch (type) {
        case 'phone':
          if (value === '' || value === null) {
            return 'Mobile number is required.';
          }
          const digits = value.replace(/[^0-9]/g, '');
          if (digits.length < 11) {
            return 'Mobile number must be 11 digits starting with 09.';
          }
          if (!/^09/.test(digits)) {
            return 'Mobile number must start with 09.';
          }
          return 'Mobile number must be exactly 11 digits.';
        case 'email':
          return 'Please enter a valid email address.';
        case 'positive':
          return 'Please enter a positive number greater than 0.';
        default:
          return 'Invalid input.';
      }
    }

    // Update submit button enable/disable state
    function updateSubmitButton() {
      const form = field.closest('form');
      if (!form) return;

      const submitBtn = form.querySelector('button[type="submit"]');
      if (!submitBtn) return;

      // Check all fields in the form
      const formFields = form.querySelectorAll('[data-validate]');
      let anyInvalid = false;

      formFields.forEach(function (f) {
        const vtype = f.getAttribute('data-validate');
        const fvalue = f.value.trim();
        const isValid = validateFieldValue(fvalue, vtype);
        if (isValid === false || (isValid === null && fvalue === '' && vtype === 'positive')) {
          anyInvalid = true;
        }
      });

      // Disable submit if any required field is invalid
      if (anyInvalid) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
      } else {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '';
        submitBtn.style.cursor = '';
      }
    }

    // Event listeners
    field.addEventListener('input', validateField);
    field.addEventListener('blur', validateField);

    // Initial validation on page load
    validateField();
  });
});