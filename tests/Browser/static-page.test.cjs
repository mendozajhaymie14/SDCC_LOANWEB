/**
 * Static-page browser checks — NO server, NO login.
 *
 * Loads the HTML dumped by tests/Feature/StaticPageDumpTest.php (tests/tmp/*.html)
 * in headless chromium and asserts real visibility/click behaviour.
 * External fonts/CDN are blocked so the run never touches the network.
 *
 * Usage:
 *   node tests/Browser/static-page.test.cjs [all|notif|repayments]
 *
 * Exit 0 = all checks passed, 1 = at least one failed, 2 = dump missing.
 * Hard timeout 60 s (the process force-exits even if a check hangs).
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const HARD_TIMEOUT_MS = 60_000;
const DUMPS = {
    notif: 'notifications-log.html',
    repayments: 'repayments.html',
};

let checks = 0;
let failures = 0;

function ok(name, cond, extra) {
    checks++;
    if (cond) {
        console.log('PASS ' + name);
    } else {
        failures++;
        console.log('FAIL ' + name + (extra ? ' — ' + extra : ''));
    }
}

async function loadPage(browser, file) {
    const page = await browser.newPage();
    // Block every external request (fonts, CDN) so the run never waits on the network.
    await page.route(/^https?:\/\//, (route) => route.abort());
    await page.setDefaultTimeout(10_000);
    await page.setDefaultNavigationTimeout(10_000);
    await page.goto('file://' + path.resolve(__dirname, '..', 'tmp', file));
    return page;
}

const computed = (page, sel, prop) =>
    page.$eval(sel, (el, p) => getComputedStyle(el)[p], prop);

const isVisible = (page, sel) => page.isVisible(sel);

// ---------------------------------------------------------------------------
// Task 8 — /admin/notifications-log detail modal
// ---------------------------------------------------------------------------
async function suiteNotif(browser) {
    console.log('\n== Task 8: notification detail modal ==');
    const page = await loadPage(browser, DUMPS.notif);

    // Exactly one notification-detail modal element (footer's shared modals also exist on the page), hidden by default.
    const modalCount = await page.locator('#modal-notifDetailModal').count();
    ok('notif: exactly one notification-detail modal element', modalCount === 1, 'found ' + modalCount);
    ok('notif: modal NOT visible on load', !(await isVisible(page, '#modal-notifDetailModal')));
    ok('notif: modal has no bounding box on load', (await page.locator('#modal-notifDetailModal').boundingBox()) === null);
    ok('notif: backdrop hidden on load', (await computed(page, '#overlay', 'display')) === 'none');
    const anyModalVisible = await page.locator('.modal').evaluateAll((els) => els.some((e) => e.offsetParent !== null));
    ok('notif: no other modal visible on load', !anyModalVisible);

    // (2) Clicking a row opens it with the right title and fields.
    // (Table is newest-first; target rows by content, not index.)
    const rowA = page.locator('#notifLogsTableBody tr').filter({ hasText: 'Loan Approved' }).first();
    const rowB = page.locator('#notifLogsTableBody tr').filter({ hasText: 'Loan Rejected' }).first();
    await rowA.locator('td').first().click();
    ok('notif: row click makes modal visible', await isVisible(page, '#modal-notifDetailModal'));
    ok('notif: title is "<Type> email"', (await page.textContent('#notifModalTitle')).trim() === 'Loan Approved email',
        await page.textContent('#notifModalTitle'));
    let body = await page.textContent('#notifModalBody');
    ok('notif: body shows the loan ref field', /Loan[\s\S]*APP-0009/.test(body));
    ok('notif: body shows recipient field', /Recipient[\s\S]*borrower@example\.com/.test(body));
    ok('notif: body shows subject field', /Subject[\s\S]*approved/.test(body));

    // (3) X hides it completely: not visible, backdrop gone, body empty.
    await page.locator('#modal-notifDetailModal .modal-close').click();
    ok('notif: X hides the modal box', !(await isVisible(page, '#modal-notifDetailModal')));
    ok('notif: X hides the backdrop', (await computed(page, '#overlay', 'display')) === 'none');
    ok('notif: X clears the body content', (await page.$eval('#notifModalBody', (el) => el.innerHTML.trim())) === '');

    // (4) Reopen then the Close button hides it.
    await rowA.locator('td').first().click();
    ok('notif: reopen works', await isVisible(page, '#modal-notifDetailModal'));
    await page.locator('#modal-notifDetailModal button:has-text("Close")').click();
    ok('notif: Close button hides the modal box', !(await isVisible(page, '#modal-notifDetailModal')));
    ok('notif: Close clears the body content', (await page.$eval('#notifModalBody', (el) => el.innerHTML.trim())) === '');

    // (5) Esc hides.
    await rowA.locator('td').first().click();
    await page.keyboard.press('Escape');
    ok('notif: Esc hides the modal box', !(await isVisible(page, '#modal-notifDetailModal')));
    ok('notif: Esc hides the backdrop', (await computed(page, '#overlay', 'display')) === 'none');

    // (6) Backdrop click hides.
    await rowA.locator('td').first().click();
    await page.mouse.click(5, 5);
    ok('notif: backdrop click hides the modal box', !(await isVisible(page, '#modal-notifDetailModal')));
    ok('notif: backdrop click hides the backdrop', (await computed(page, '#overlay', 'display')) === 'none');

    // (7) Open row A then row B shows only B (no stacked content).
    await rowA.locator('td').first().click();
    body = await page.textContent('#notifModalBody');
    ok('notif: opening twice never stacks content',
        (body.match(/Recipient/g) || []).length === 1 && (body.match(/Subject/g) || []).length === 1, body.slice(0, 120));
    await page.locator('#modal-notifDetailModal .modal-close').click();
    await rowB.locator('td').first().click();
    const title2 = (await page.textContent('#notifModalTitle')).trim();
    body = await page.textContent('#notifModalBody');
    ok('notif: second row replaces content (title = row B type)', title2 === 'Loan Rejected email', title2);
    ok('notif: body shows row B subject only', /rejected/i.test(body) && !/approved/i.test(body), body.slice(0, 120));
    await page.locator('#modal-notifDetailModal .modal-close').click();

    // (8) Type pills use distinct colours per type (not one blue for all).
    const pillColor = async (label) => page.locator('#notifLogsTableBody .nl-pill', { hasText: label }).first()
        .evaluate((el) => getComputedStyle(el).color);
    const approvedColor = await pillColor('Loan Approved');
    const rejectedColor = await pillColor('Loan Rejected');
    ok('notif: type pills differ in colour by type', approvedColor !== rejectedColor, approvedColor + ' vs ' + rejectedColor);
    ok('notif: approved type pill is green', approvedColor === 'rgb(4, 120, 87)', approvedColor);
    ok('notif: rejected type pill is red', rejectedColor === 'rgb(185, 28, 28)', rejectedColor);

    // (9) Clicking a loan link in the Loan column opens ONLY the application modal
    //     (stopPropagation prevents the row click from opening the notif modal too).
    await page.locator('#notifLogsTableBody tr').filter({ hasText: 'Loan Approved' }).first().locator('.nl-loan-link').click();
    ok('notif: loan-column link does not open the notif modal', !(await isVisible(page, '#modal-notifDetailModal')));
    ok('notif: loan-column link opens the application modal', await isVisible(page, '#modal-applicationModal'));

    await page.close();
}

// ---------------------------------------------------------------------------
// Task 9 — /repayments tabs + modals
// ---------------------------------------------------------------------------
async function suiteRepay(browser) {
    console.log('\n== Task 9: repayments tabs + modals ==');
    const page = await loadPage(browser, DUMPS.repayments);

    // No modal visible on load.
    for (const id of ['#modal-recordRepaymentModal', '#modal-editRepaymentModal', '#modal-viewRepaymentModal', '#modal-reverseRepayment']) {
        ok('repay: ' + id + ' NOT visible on load', !(await isVisible(page, id)));
    }

    // 9.1 tabs: styled, one panel, default Payments, no reload on switch.
    const tabBg = await computed(page, '.rep-tab', 'backgroundColor');
    const tabPad = await computed(page, '.rep-tab', 'paddingLeft');
    const tabFont = await computed(page, '.rep-tab', 'fontFamily');
    ok('repay: tabs styled (transparent bg, not UA ButtonFace)', tabBg === 'rgba(0, 0, 0, 0)', tabBg);
    ok('repay: tabs styled (padding 1rem, not UA 6px)', tabPad === '16px', tabPad);
    ok('repay: tabs styled (DM Sans font)', /DM Sans/.test(tabFont), tabFont);
    const countBg = await computed(page, '.rep-tab-count', 'backgroundColor');
    const countRadius = await computed(page, '.rep-tab-count', 'borderRadius');
    ok('repay: count badge is a pill (red bg, 50px radius)', countBg === 'rgb(220, 38, 38)' && countRadius === '50px', countBg + '/' + countRadius);

    ok('repay: Payments panel visible on load', await isVisible(page, '#tab-payments'));
    ok('repay: Due panel hidden on load', !(await isVisible(page, '#tab-due')));

    const urlBefore = page.url();
    await page.locator('.rep-tab[data-tab="due"]').click();
    ok('repay: Due tab click shows Due panel only',
        (await isVisible(page, '#tab-due')) && !(await isVisible(page, '#tab-payments')));
    ok('repay: tab switch does not reload the page', page.url() === urlBefore);
    await page.locator('.rep-tab[data-tab="payments"]').click();
    ok('repay: clicking back restores Payments panel',
        (await isVisible(page, '#tab-payments')) && !(await isVisible(page, '#tab-due')));

    // Record Payment modal: open, X closes, reopen, Cancel closes.
    await page.getByRole('button', { name: /record payment/i }).click();
    ok('repay: Record Payment modal opens', await isVisible(page, '#modal-recordRepaymentModal'));
    await page.locator('#modal-recordRepaymentModal .modal-close').click();
    ok('repay: X fully hides Record modal', !(await isVisible(page, '#modal-recordRepaymentModal')));
    await page.getByRole('button', { name: /record payment/i }).click();
    ok('repay: Record Payment modal reopens', await isVisible(page, '#modal-recordRepaymentModal'));
    await page.locator('#modal-recordRepaymentModal .rep-modal-actions .btn-cancel').click();
    ok('repay: Cancel fully hides Record modal', !(await isVisible(page, '#modal-recordRepaymentModal')));

    // View / Edit / Reverse modals, once each.
    await page.locator('.btn-view-repayment').first().click();
    ok('repay: View modal opens', await isVisible(page, '#modal-viewRepaymentModal'));
    await page.locator('#modal-viewRepaymentModal .modal-close').click();
    ok('repay: X fully hides View modal', !(await isVisible(page, '#modal-viewRepaymentModal')));

    await page.locator('.btn-edit-repayment').first().click();
    ok('repay: Edit modal opens', await isVisible(page, '#modal-editRepaymentModal'));
    await page.locator('#modal-editRepaymentModal .rep-modal-actions .btn-cancel').click();
    ok('repay: Cancel fully hides Edit modal', !(await isVisible(page, '#modal-editRepaymentModal')));

    await page.locator('.btn-reverse-repayment').first().click();
    ok('repay: Reverse modal opens', await isVisible(page, '#modal-reverseRepayment'));
    ok('repay: Reverse modal requires a reason', await isVisible(page, '#reverseReason'));
    await page.locator('#modal-reverseRepayment .rep-modal-actions .btn-cancel').click();
    ok('repay: Cancel fully hides Reverse modal', !(await isVisible(page, '#modal-reverseRepayment')));

    // 9.3 Reverse button: red danger-text button, distinct from View/Edit.
    const revBg = await computed(page, '.btn-reverse-repayment', 'backgroundColor');
    const revColor = await computed(page, '.btn-reverse-repayment', 'color');
    const revBorder = await computed(page, '.btn-reverse-repayment', 'borderColor');
    const viewColor = await computed(page, '.btn-view-repayment', 'color');
    ok('repay: Reverse bg matches View/Edit (white)', revBg === 'rgb(255, 255, 255)', revBg);
    ok('repay: Reverse is danger-text (red)', revColor === 'rgb(220, 38, 38)', revColor);
    ok('repay: Reverse border is red', revBorder === 'rgb(220, 38, 38)', revBorder);
    ok('repay: Reverse text differs from View', revColor !== viewColor, revColor + ' vs ' + viewColor);

    await page.close();
}

// ---------------------------------------------------------------------------
async function main() {
    const which = process.argv[2] || 'all';
    const missing = Object.entries(DUMPS)
        .filter(([k]) => which === 'all' || which === k)
        .filter(([, f]) => !fs.existsSync(path.resolve(__dirname, '..', 'tmp', f)))
        .map(([, f]) => f);
    if (missing.length) {
        console.error('Missing dumps: ' + missing.join(', ') +
            '\nRun first: vendor/bin/phpunit --filter StaticPageDumpTest');
        process.exit(2);
    }

    const timer = setTimeout(() => {
        console.error('HARD TIMEOUT 60s exceeded');
        process.exit(3);
    }, HARD_TIMEOUT_MS);

    const browser = await chromium.launch();
    try {
        if (which === 'all' || which === 'notif') await suiteNotif(browser);
        if (which === 'all' || which === 'repayments') await suiteRepay(browser);
    } catch (e) {
        failures++;
        console.log('FAIL unexpected error: ' + (e && e.stack || e));
    } finally {
        await browser.close();
        clearTimeout(timer);
    }

    console.log('\n' + (checks - failures) + '/' + checks + ' checks passed, ' + failures + ' failed');
    process.exit(failures ? 1 : 0);
}

main();
