# Objectives Alignment Document

## Matrix Deviation: Micro Product

**Decision Date:** 2025 (Step 3 implementation)

**Issue:** The study document specifies no share capital anywhere. Phase A removed 4 products (character, recovery, secured, share_cap), keeping 7 products (regular, educational, emergency, healthcare, micro, utility, special).

The "Micro" product traditionally implies small loans that may not require collateral. However, the study document's "no share capital" rule extends to all products.

**Decision:** Keep Micro product requiring an assessed credit limit (via `LoanProductHelper::requiresCreditLimit()` returning `true` for all 7 products). Do NOT add a collateral field to the loan form.

**Rationale:**
1. Conservative approach: All loans should have an assessed credit limit to prevent over-lending
2. No collateral field added to member loan form (`public/js/loan-form.js` dead `#collateral` code removed)
3. `requiresCreditLimit()` exempt list simplified to `[]` — all 7 kept products require credit limit
4. Removed duplicate `UserController::requiresCreditLimit()` method; all callers now use `LoanProductHelper::requiresCreditLimit()`

**Files Changed (Step 3):**
- `app/Support/LoanProductHelper.php` — `requiresCreditLimit()` returns `true` (no exempt list)
- `app/Http/Controllers/UserController.php` — Removed duplicate `requiresCreditLimit()` method
- `app/Http/Controllers/AdminController.php` — Updated 2 callers to use `LoanProductHelper::requiresCreditLimit()`
- `public/js/loan-form.js` — Removed dead `#collateral` JS code (lines 50, 116-127)

**Verification:** `vendor/bin/phpunit` — failures dropped from 201 → 4. Remaining 4 are pre-existing ValidationTest design issues (test users aren't coop members), unrelated to share capital removal.