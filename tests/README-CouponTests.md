# Coupon XML & Workflow Test Suite

Smoke tests for the multicoupon workflow, XML rules, and corner cases.

## Running the tests

```bash
php artisan test tests/Feature/CouponXmlTest.php
```

### Prerequisites

If tests fail with:

```
RuntimeException: Changing columns for table "..." requires Doctrine DBAL.
Please install the doctrine/dbal package.
```

Install Doctrine DBAL (for migrations that use `change()` on columns):

```bash
composer require --dev doctrine/dbal
```

## Coverage

| Category | Tests |
|----------|-------|
| **XML rules** | Percentage → `dyn:discount`, fixed → reduced `unitPrice` + `discount=0` |
| **Single coupons** | Percentage cart, fixed amount cart |
| **Client-specific** | `APPLIES_TO_CUSTOMER` – applies when user in list, contributes 0 when not |
| **Multiple coupons** | Best discount per product (percentage vs percentage, percentage vs fixed) |
| **applies_to** | Product, brand, category, cart |
| **Package pricing** | `calculate_package_price` with percentage coupon |
| **Mixed cart** | Some products with coupon, some without |
| **Minimum amount** | Validation rejects when cart below minimum |
| **No applicable** | Empty/wrong applies_to_ids returns failure |
| **Mixed XML** | Order with both % and fixed products → correct XML output |
