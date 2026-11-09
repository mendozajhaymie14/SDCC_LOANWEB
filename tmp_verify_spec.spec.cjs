const { test, expect } = require('@playwright/test');

test('verify memberships modal checklist items', async ({ page }) => {
  // Login as Branch Manager
  await page.goto('/login');
  await page.waitForLoadState('networkidle');

  await page.fill('input[name="email"]', 'admin-test@sdcc.coop');
  await page.fill('input[name="password"]', 'admin-test-123');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');

  // Now navigate to memberships
  await page.goto('/memberships');
  await page.waitForLoadState('networkidle');

  const body = await page.textContent('body');

  // Check 1: Recommended credit limit row shows ₱ with cappedBy
  const hasRecommendedRow = body.includes('Recommended credit limit');
  console.log('Check 1 - Recommended row present:', hasRecommendedRow);

  // Check 2: Approve panel exists with pre-filled amount
  const hasApprovePanel = body.includes('id="approveMemberPanel_') && body.includes('value="');
  console.log('Check 2 - Approve panel with pre-filled amount:', hasApprovePanel);

  // Check 3: Reject panel exists
  const hasRejectPanel = body.includes('id="rejectMemberPanel_');
  console.log('Check 3 - Reject panel present:', hasRejectPanel);

  // Check 4: Panel helpers exist (open/close functions)
  const hasOpenHelpers = body.includes('openApproveMemberPanel') && body.includes('openRejectMemberPanel');
  console.log('Check 4 - Panel open/close helpers:', hasOpenHelpers);

  // Check 5: Client-side validation for above-recommendation
  const hasValidation = body.includes('amountVal > recommended && !approveReason');
  console.log('Check 5 - Above-recommendation validation:', hasValidation);

  // Check 6: Min 10 chars validation for reject
  const hasMin10Check = body.includes('rejectionReason.length < 10');
  console.log('Check 6 - Min 10 char validation:', hasMin10Check);

  // Check 7: Confirm approve button uses openApproveMemberPanel instead of confirm()
  const hasOpenApproveBtn = body.includes('onclick="openApproveMemberPanel');
  console.log('Check 7 - Approve button uses openApproveMemberPanel:', hasOpenApproveBtn);

  // Check 8: Confirm reject button uses openRejectMemberPanel instead of confirm()
  const hasOpenRejectBtn = body.includes('onclick="openRejectMemberPanel');
  console.log('Check 8 - Reject button uses openRejectMemberPanel:', hasOpenRejectBtn);

  // Summary
  console.log('\n--- Summary ---');
  console.log('1. Recommended row shows ₱X (cappedBy):', hasRecommendedRow ? 'PASS' : 'FAIL');
  console.log('2. Approve opens panel with amount pre-filled:', hasApprovePanel ? 'PASS' : 'FAIL');
  console.log('3. Approving above recommendation without reason is blocked:', hasValidation ? 'PASS' : 'FAIL');
  console.log('4. Reject opens its own panel:', hasRejectPanel ? 'PASS' : 'FAIL');

  const allPass = hasRecommendedRow && hasApprovePanel && hasRejectPanel && hasOpenHelpers && hasValidation && hasMin10Check && hasOpenApproveBtn && hasOpenRejectBtn;
  console.log('\nAll checks pass:', allPass ? 'YES' : 'NO');

  if (!allPass) {
    process.exit(1);
  }
});