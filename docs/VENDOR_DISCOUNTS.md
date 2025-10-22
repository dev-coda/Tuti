# Vendor Discounts

## Overview

Vendors can have discount percentages that apply to all their products. However, these discounts can have minimum purchase amount requirements that must be met before the discount is applied.

## How Vendor Discounts Work

### Basic Concept

A vendor has the following discount-related attributes:

-   **discount**: Percentage discount applied to all products (e.g., 10 for 10% off)
-   **minimum_discount_amount**: Minimum cart total for this vendor's products before discount applies
-   **first_purchase_only**: If true, discount only applies to customers making their first purchase

### Discount Hierarchy

Discounts are applied in the following priority order (highest to lowest):

1. **Vendor discount** (if minimum amount is met)
2. **Brand discount**
3. **Product discount**

If a higher priority discount applies, it overrides any lower priority discounts.

### Minimum Amount Calculation

**Important**: The minimum amount check is performed on the cart total **before** the vendor discount is applied. This prevents circular logic where the discount affects the total, which affects whether the discount qualifies.

#### Calculation Flow

1. **Calculate vendor cart totals WITHOUT vendor discounts:**

    ```
    For each product in cart:
        Calculate price with product/brand discounts only
        Add to vendor's total
    ```

2. **Check if minimum is met:**

    ```
    If vendor_cart_total >= minimum_discount_amount:
        Vendor discount applies
    Else:
        Vendor discount does NOT apply
    ```

3. **Recalculate with vendor discount applied:**
    ```
    For qualifying vendors:
        Recalculate product prices with vendor discount
        Update cart totals
    ```

### Examples

**Example 1: Minimum met**

-   Vendor A has 15% discount with $100 minimum
-   Cart contains Vendor A products:
    -   Product 1: $60 (no discount)
    -   Product 2: $50 (no discount)
-   Vendor cart total = $110
-   ✅ Minimum met ($110 >= $100)
-   Final prices:
    -   Product 1: $51 ($60 - 15%)
    -   Product 2: $42.50 ($50 - 15%)

**Example 2: Minimum not met**

-   Vendor A has 15% discount with $100 minimum
-   Cart contains Vendor A products:
    -   Product 1: $40 (no discount)
    -   Product 2: $30 (no discount)
-   Vendor cart total = $70
-   ❌ Minimum NOT met ($70 < $100)
-   Final prices remain:
    -   Product 1: $40 (no discount applied)
    -   Product 2: $30 (no discount applied)

**Example 3: Mixed vendors**

-   Vendor A: 15% discount, $100 minimum
-   Vendor B: 10% discount, $50 minimum
-   Cart contains:
    -   Vendor A Product: $120
    -   Vendor B Product: $40
-   Vendor A total = $120 ✅ (minimum met)
-   Vendor B total = $40 ❌ (minimum not met)
-   Final prices:
    -   Vendor A Product: $102 (15% discount applied)
    -   Vendor B Product: $40 (no discount)

**Example 4: With package quantities**

-   Vendor A: 10% discount, $100 minimum
-   Product: 6-pack (package_quantity = 6), $5 per package
-   Customer orders 25 packages
-   Calculation:
    -   Base price per package = $5
    -   Total before discount = $5 × 25 = $125
    -   ✅ Minimum met ($125 >= $100)
    -   Final price per package = $4.50 ($5 - 10%)
    -   Final total = $4.50 × 25 = $112.50

### Technical Implementation

The vendor discount minimum check occurs in two places:

#### 1. Product Model (`getFinalPriceForUser` method)

```php
if ($vendor->minimum_discount_amount > 0) {
    if ($vendorCartTotal !== null) {
        // In cart context: check the total cart amount for this vendor
        $vendorMinimumMet = $vendorCartTotal >= $vendor->minimum_discount_amount;
    } else {
        // In catalog context: check individual product price
        // (Shows discount badge if single product meets minimum)
    }
}
```

#### 2. Cart Controller

**Cart Display:**

```php
// First pass: Calculate prices WITHOUT vendor discounts
foreach ($cart as $item) {
    $finalPrice = $product->getFinalPriceForUser($has_orders, 0); // Pass 0 to prevent vendor discount
}

// Calculate vendor totals
foreach ($byVendors as $vendor) {
    $vendorTotals[$key] = $vendor->sum('price');
}

// Second pass: Recalculate WITH vendor discounts if minimum met
foreach ($products as $product) {
    $vendorTotal = $vendorTotals[$product->vendor_id];
    $finalPrice = $product->getFinalPriceForUser($has_orders, $vendorTotal);
}
```

**Checkout Process:**

```php
// Calculate vendor totals WITHOUT vendor discounts
foreach ($cart as $row) {
    $priceInfo = $tempProduct->getFinalPriceForUser($has_orders, 0);
    $vendorTotals[$vendorId] += $priceInfo['price'] * $quantity;
}

// Apply vendor discounts only if minimum is met
foreach ($cart as $row) {
    $vendorTotal = $vendorTotals[$vendorId];
    $lineFinal = $p->getFinalPriceForUser($has_orders, $vendorTotal);
}
```

## First Purchase Restrictions

Vendor discounts can be restricted to first-time customers only using the `first_purchase_only` flag:

-   If `first_purchase_only = true`: Discount only applies to customers with no previous orders
-   If `first_purchase_only = false`: Discount applies to all customers (subject to minimum amount)

This is controlled by the `.env` configuration:

```env
ENFORCE_FIRST_PURCHASE_DISCOUNTS=true
```

## Admin Interface

Administrators can configure vendor discounts at:

-   **Path:** `/admin/vendors/{id}/edit`
-   **Fields:**
    -   `discount`: Percentage discount (0-100)
    -   `minimum_discount_amount`: Minimum cart total required
    -   `first_purchase_only`: Checkbox for first purchase restriction

## Interaction with Coupons

When a coupon is applied:

-   Coupon discounts take precedence over vendor discounts
-   Products affected by coupons do NOT receive vendor discounts
-   Non-coupon products can still receive vendor discounts if the minimum is met
-   Vendor totals are calculated using coupon-modified prices for affected products

## Alerts and Notifications

The cart displays alerts when:

1. A vendor has a minimum purchase requirement that isn't met
2. A vendor has a discount available but the minimum discount amount isn't met

The alert shows:

-   Vendor name
-   Current cart total for that vendor
-   Amount needed to reach the minimum
-   Discount percentage available

This helps customers understand how much more they need to add to their cart to qualify for the discount.
