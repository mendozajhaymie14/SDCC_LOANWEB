const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

/**
 * Browser test for the registration form.
 *
 * It pins the three states the form must have:
 *
 *   1. "No, I am new"  — name, e-mail, password, confirm.
 *   2. "Yes, I am a member" — SDCC ID + Send code.
 *   3. after the code is verified — the member's name and e-mail are filled
 *      in read-only, and only the password is still asked for.
 *
 * Any console error fails the test.
 */

const LOG_PATH = path.resolve(__dirname, '..', '..', 'storage', 'logs', 'laravel.log');
const MEMBER_ID = 'SDCC-BROWSER-TEST-0001';

function collectConsoleErrors(page) {
    const errors = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => errors.push(error.message));

    return errors;
}

/**
 * The one-time code is mailed, and Laravel's `log` mailer writes the message
 * at debug level. Read it from the log — the code is never printed.
 */
function readCodeFromLog(fromOffset) {
    const fd = fs.openSync(LOG_PATH, 'r');
    const stat = fs.fstatSync(fd);
    const length = Math.max(0, stat.size - fromOffset);
    const buffer = Buffer.alloc(length);
    fs.readSync(fd, buffer, 0, length, fromOffset);
    fs.closeSync(fd);

    const matches = [...buffer.toString('utf8').matchAll(/##\s*(\d{6})/g)];

    return matches.length ? matches[matches.length - 1][1] : null;
}

function logSize() {
    return fs.existsSync(LOG_PATH) ? fs.statSync(LOG_PATH).size : 0;
}

test.describe('registration form', () => {
    test('"No, I am new" shows the name, e-mail and password fields', async ({ page }) => {
        const errors = collectConsoleErrors(page);

        await page.goto('/register');

        await expect(page.locator('#first_name')).toBeVisible();
        await expect(page.locator('#middle_name')).toBeVisible();
        await expect(page.locator('#last_name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('#password_confirmation')).toBeVisible();

        // The member half of the form stays hidden.
        await expect(page.locator('#member_id')).toBeHidden();
        await expect(page.getByRole('button', { name: /send code/i })).toBeHidden();

        // The membership link and the Gmail hint stay.
        await expect(page.getByRole('link', { name: 'Apply for membership here' })).toBeVisible();
        await expect(page.locator('#email')).toHaveAttribute('pattern', '.+@gmail\\.com');

        expect(errors).toEqual([]);
    });

    test('"Yes, I am a member" shows the SDCC ID and the Send code button', async ({ page }) => {
        const errors = collectConsoleErrors(page);

        await page.goto('/register');
        await page.locator('input[name="is_existing_member"][value="1"]').check();

        await expect(page.locator('#member_id')).toBeVisible();
        await expect(page.getByRole('button', { name: /send code/i })).toBeVisible();

        // The new-account fields are replaced by the member ID.
        await expect(page.locator('#first_name')).toBeHidden();
        await expect(page.locator('#middle_name')).toBeHidden();
        await expect(page.locator('#last_name')).toBeHidden();
        await expect(page.locator('#email')).toBeHidden();

        // The password is always asked for.
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('#password_confirmation')).toBeVisible();

        expect(errors).toEqual([]);
    });

    test('after the code is verified only the password is asked for', async ({ page }) => {
        const errors = collectConsoleErrors(page);

        await page.goto('/register');
        await page.locator('input[name="is_existing_member"][value="1"]').check();
        await page.locator('#member_id').fill(MEMBER_ID);

        const offset = logSize();
        await page.getByRole('button', { name: /send code/i }).click();

        await expect(page.locator('#member_code')).toBeVisible();

        const code = readCodeFromLog(offset);
        expect(code, 'the verification code was mailed').toMatch(/^\d{6}$/);

        await page.locator('#member_code').fill(code);
        await page.getByRole('button', { name: /verify code/i }).click();

        // The member's details are filled in for the visitor.
        await expect(page.locator('#existing_first_name')).toBeVisible();
        await expect(page.locator('#existing_first_name')).toHaveValue('Juan');
        await expect(page.locator('#existing_middle_name')).toHaveValue('Dela');
        await expect(page.locator('#existing_last_name')).toHaveValue('Cruz');
        await expect(page.locator('#existing_email')).toHaveValue('juan.browsertest@gmail.com');

        // ... and they cannot be typed over.
        expect(await page.locator('#existing_first_name').evaluate((el) => el.readOnly)).toBe(true);
        expect(await page.locator('#existing_email').evaluate((el) => el.readOnly)).toBe(true);

        // Only the password is still asked for.
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('#password_confirmation')).toBeVisible();
        await expect(page.locator('#first_name')).toBeHidden();
        await expect(page.locator('#email')).toBeHidden();

        expect(errors).toEqual([]);
    });
});
