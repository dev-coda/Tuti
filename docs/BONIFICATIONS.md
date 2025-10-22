# Bonifications Module

## Overview

The bonifications module allows creating "Buy X, Get Y" promotions where customers receive free items when they purchase a certain quantity of products.

## How Bonifications Work

### Basic Concept

A bonification has the following attributes:

-   **name**: Display name of the bonification
-   **buy**: Minimum number of individual items required to qualify
-   **get**: Number of free individual items received
-   **product_id**: The free product that will be given
-   **max**: Maximum number of free items that can be given in a single order

### Package Quantity Considerations

#### Important: Bonifications Always Work with Individual Items

Bonifications are always calculated based on **individual items**, not package units. This is crucial when dealing with products that have a `package_quantity` greater than 1.

#### Qualification Logic

When checking if a customer qualifies for a bonification:

1. **Calculate total individual items purchased:**

    ```
    Total Individual Items = Quantity Ordered × Package Quantity
    ```

2. **Check qualification:**

    ```
    Bonification Quantity = floor(Total Individual Items / buy) × get
    ```

3. **Apply maximum limit:**
    ```
    If Bonification Quantity > max:
        Bonification Quantity = max
    ```

#### Examples

**Example 1: Product without package quantity (package_quantity = 1)**

-   Product: Single bottle of shampoo
-   Bonification: "Buy 10, Get 1 free"
-   Customer orders: 15 bottles
-   Calculation:
    -   Total individual items = 15 × 1 = 15
    -   Bonification = floor(15 / 10) × 1 = 1 free item

**Example 2: Product with package quantity**

-   Product: 6-pack of shampoo bottles (package_quantity = 6)
-   Bonification: "Buy 10, Get 1 free" (referring to individual bottles)
-   Customer orders: 10 packages
-   Calculation:
    -   Total individual items = 10 × 6 = 60 individual bottles
    -   Bonification = floor(60 / 10) × 1 = 6 free individual bottles

**Example 3: With maximum limit**

-   Product: 12-pack of soap bars (package_quantity = 12)
-   Bonification: "Buy 10, Get 2 free" with max = 10
-   Customer orders: 100 packages
-   Calculation:
    -   Total individual items = 100 × 12 = 1,200 individual bars
    -   Bonification (before max) = floor(1,200 / 10) × 2 = 240 free bars
    -   Bonification (after max) = 10 free bars (limited by max)

### Gift Products

The products given as gifts in bonifications are **always individual items**, regardless of the package_quantity of the original purchased product. This means:

-   If you buy packages of 6 and qualify for 6 free items, you receive 6 individual items (not 6 packages)
-   The bonification `product_id` field specifies which product is given as the gift
-   The gift product can be different from the purchased product

### Technical Implementation

The bonification calculation occurs in `CartController.php` during checkout:

```php
// Calculate total individual items purchased (considering package_quantity)
$packageQuantity = $p->package_quantity ?? 1;
$individualItemsPurchased = $row['quantity'] * $packageQuantity;

// Calculate bonification based on individual items
$bonification_quantity = floor($individualItemsPurchased / $bonification->buy * $bonification->get);

// Apply maximum limit
if ($bonification_quantity > $bonification->max) {
    $bonification_quantity = $bonification->max;
}
```

### Database Schema

**bonifications table:**

-   `id`: Primary key
-   `name`: Display name
-   `buy`: Minimum individual items to qualify
-   `get`: Number of free individual items
-   `product_id`: The free product to give
-   `max`: Maximum free items per order
-   `created_at`, `updated_at`

**bonification_product table:** (Many-to-many pivot)

-   `id`: Primary key
-   `bonification_id`: Foreign key to bonifications
-   `product_id`: Foreign key to products (products that qualify for this bonification)
-   `created_at`, `updated_at`

**order_product_bonifications table:**

-   `id`: Primary key
-   `order_id`: Foreign key to orders
-   `order_product_id`: Foreign key to order_products
-   `bonification_id`: Foreign key to bonifications
-   `product_id`: The free product
-   `quantity`: Number of free items given
-   `created_at`, `updated_at`

## Admin Interface

Administrators can manage bonifications through:

-   **List view:** `/admin/bonifications` - Shows all bonifications with their settings
-   **Create view:** `/admin/bonifications/create` - Create new bonifications
-   **Edit view:** `/admin/bonifications/{id}/edit` - Edit bonification and assign qualifying products

## Order Processing

When an order is placed:

1. Bonifications are calculated and stored in `order_product_bonifications` table
2. A separate SOAP order is sent for bonifications with price = 0
3. Bonifications are displayed separately in order views
4. Inventory is NOT decremented for bonification items (they are treated as gifts)

## Best Practices

1. **Set appropriate maximums:** Always set a reasonable `max` value to prevent abuse
2. **Clear naming:** Use descriptive names like "Buy 10 Get 1 Free - Shampoo"
3. **Individual items:** Remember that buy/get values always refer to individual items, not packages
4. **Testing:** Test bonifications with various package quantities to ensure correct behavior
