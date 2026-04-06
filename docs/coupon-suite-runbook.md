# Coupon Suite Validation Runbook

This runbook is for validating coupon stacking and XML mapping in stage/prod using the admin diagnostic tool.  
The tool only builds preview XML and never sends transmission requests.

## 1) Pre-flight checks

1. Confirm deployment includes:
   - Canonical coupon line engine (`CouponDiscountService`)
   - Canonical XML mapping (`OrderRepository`)
   - Admin scenario suite routes under `coupon-tests/suite`
2. Confirm active test coupons exist for each class:
   - Percentage cart (`PCTxx`)
   - Fixed amount cart (`FIXxx`)
   - Product/brand/category scoped coupons
3. Confirm at least one admin user and one test customer with zone assignment.

## 2) Stage validation

1. Open admin and go to `coupon-tests/suite`.
2. Pick a test customer and execute a scenario JSON covering:
   - Percentage vs fixed competition on same product
   - Multi-line fixed coupon distribution
   - Mixed applicability (one line gets coupon, one line does not)
   - Existing discount beats coupon
   - Package-quantity product with coupon
3. Validate suite summary:
   - `failed = 0`
   - Assertions show expected `discount` and `unitPrice`.
4. Export JSON and CSV from the results view and archive with deployment notes.

## 3) Production validation

1. Repeat the same suite in prod admin.
2. Verify all scenarios pass and review a random sample of raw XML from the UI.
3. Use `coupon-tests/preview` on 2-3 recent real orders to confirm diagnostic reconstruction is consistent.

## 4) Acceptance criteria

- For percentage lines: XML carries `%` in `<dyn:discount>`.
- For fixed lines: XML uses `<dyn:discount>0</dyn:discount>` and reduced `<dyn:unitPrice>`.
- Per product, best outcome is selected without summing coupons.
- No external XML transmission occurs from diagnostic tools.

## 5) Rollback trigger

Rollback or hotfix if any occurs:

- Fixed-amount scenario sends non-zero XML discount.
- XML `unitPrice` does not reflect expected fixed reduction (including min floor behavior).
- Stage/prod scenario suite fails for deterministic cases previously passing.
