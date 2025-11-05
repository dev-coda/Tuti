# API Filtering, Sorting, and Pagination Guide

## Overview

All API endpoints support consistent filtering, sorting, and pagination mechanisms. This document explains how to use these features effectively.

## Table of Contents

1. [Pagination](#pagination)
2. [Limit and Offset](#limit-and-offset)
3. [Sorting](#sorting)
4. [Filtering](#filtering)
5. [Endpoint Examples](#endpoint-examples)

---

## Pagination

### Standard Pagination

Use standard pagination when you need to display results across multiple pages.

**Parameters:**
- `per_page` (integer): Number of items per page
  - Default varies by endpoint (typically 15-50)
  - Maximum varies by endpoint (typically 100-200)

**Example:**
```
GET /api/clientes?per_page=25
```

**Response Format:**
```json
{
  "data": [...],
  "pagination": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 25,
    "total": 250,
    "from": 1,
    "to": 25
  }
}
```

---

## Limit and Offset

### Non-Paginated Results

Use limit and offset when you need direct control over the result set without pagination metadata.

**Parameters:**
- `limit` (integer): Maximum number of items to return
  - Maximum: 1000
- `offset` (integer): Number of items to skip
  - Default: 0

**Example:**
```
GET /api/productos?limit=100&offset=200
```

**Response Format:**
```json
{
  "data": [...],
  "meta": {
    "count": 100,
    "limit": 100,
    "offset": 200
  }
}
```

**Important:** 
- If `limit` or `offset` is provided, standard pagination is disabled
- Use this for custom pagination implementations or data exports

---

## Sorting

### Sort Parameters

**Primary Parameters:**
- `sort_by` or `order_by` (string): Field to sort by
- `sort_direction` or `order` (string): Sort direction
  - Values: `asc` (ascending) or `desc` (descending)
  - Default: varies by endpoint

**Example:**
```
GET /api/pedidos?sort_by=created_at&sort_direction=desc
```

### Sortable Fields by Endpoint

#### Clientes (Customers)
- `id`, `name`, `email`, `document`, `created_at`, `updated_at`
- Default: `name` (asc)

#### Productos (Products)
- `id`, `name`, `price`, `created_at`, `sales_count`, `sku`
- Default: `name` (asc)

#### Pedidos (Orders)
- `id`, `total`, `created_at`, `delivery_date`, `status_id`
- Default: `created_at` (desc)

#### Precios (Prices)
- `id`, `sku`, `name`, `price`, `discount`, `updated_at`
- Default: `sku` (asc)

#### Inventarios (Inventory)
- `product_id`, `bodega_code`, `available`, `physical`, `reserved`
- Default: `product_id` (asc)

---

## Filtering

### Common Filter Patterns

#### 1. Search (Text)
```
GET /api/clientes?search=john
```

#### 2. Exact Match
```
GET /api/productos?brand_id=5
```

#### 3. Range Filters
```
GET /api/pedidos?min_total=100&max_total=500
```

#### 4. Date Filters
```
GET /api/pedidos?date_from=2025-01-01&date_to=2025-12-31
```

#### 5. Multiple Values (Comma-separated)
```
GET /api/precios?product_ids=1,2,3,4,5
GET /api/inventarios?skus=ABC123,DEF456,GHI789
```

#### 6. Boolean Filters
```
GET /api/clientes?has_whatsapp=true
```

---

## Endpoint Examples

### 1. Clientes (Customers) - `/api/clientes`

**Authentication:** Required (Bearer Token)

**Filters:**
- `search`: Search by name, email, document, or company
- `city_id`: Filter by city ID
- `state_id`: Filter by state ID
- `zone`: Filter by zone
- `role`: Filter by role name (e.g., 'customer', 'seller')
- `has_whatsapp`: Filter by WhatsApp availability (true/false)

**Examples:**

```bash
# Get first 25 customers sorted by name
GET /api/clientes?per_page=25&sort_by=name&sort_direction=asc

# Search customers with pagination
GET /api/clientes?search=john&per_page=10

# Get customers from a specific city
GET /api/clientes?city_id=5&sort_by=created_at&sort_direction=desc

# Get 50 customers starting from the 100th record
GET /api/clientes?limit=50&offset=100

# Get customers by role
GET /api/clientes?role=customer&per_page=20
```

---

### 2. Productos (Products) - `/api/productos`

**Authentication:** Required (Bearer Token)

**Filters:**
- `search`: Search by name, description, or SKU
- `category_id`: Filter by category ID
- `brand_id`: Filter by brand ID
- `sku`: Exact SKU match
- `min_price`: Minimum price
- `max_price`: Maximum price
- `bodega_code`: Get inventory for specific warehouse

**Examples:**

```bash
# Get products sorted by price (ascending)
GET /api/productos?sort_by=price&sort_direction=asc&per_page=20

# Search products by name
GET /api/productos?search=laptop&per_page=15

# Get products in a category with inventory from specific warehouse
GET /api/productos?category_id=10&bodega_code=BOD01&per_page=25

# Get products in price range
GET /api/productos?min_price=100&max_price=500&sort_by=price

# Get products by brand sorted by sales
GET /api/productos?brand_id=3&sort_by=sales_count&sort_direction=desc

# Get specific products (non-paginated)
GET /api/productos?limit=10&offset=0&sort_by=name
```

---

### 3. Pedidos (Orders) - `/api/pedidos`

**Authentication:** Required (Bearer Token)

**Filters:**
- `user_id`: Filter by customer ID
- `seller_id`: Filter by seller ID
- `zone_id`: Filter by zone ID
- `status_id`: Filter by status (0=pending, 1=processed, etc.)
- `coupon_id`: Filter by coupon ID
- `date_from`: Start date (YYYY-MM-DD)
- `date_to`: End date (YYYY-MM-DD)
- `delivery_date_from`: Delivery start date
- `delivery_date_to`: Delivery end date
- `min_total`: Minimum order total
- `max_total`: Maximum order total
- `search`: Search by order ID or customer info

**Examples:**

```bash
# Get recent orders (default sorting)
GET /api/pedidos?per_page=20

# Get orders by customer
GET /api/pedidos?user_id=123&sort_by=created_at&sort_direction=desc

# Get orders by date range
GET /api/pedidos?date_from=2025-01-01&date_to=2025-01-31&per_page=50

# Get orders by status
GET /api/pedidos?status_id=1&sort_by=delivery_date&per_page=25

# Get orders with minimum total
GET /api/pedidos?min_total=500&sort_by=total&sort_direction=desc

# Get orders by seller in date range
GET /api/pedidos?seller_id=5&date_from=2025-11-01&per_page=30

# Search orders
GET /api/pedidos?search=john&per_page=15

# Get orders for a specific customer by date (alternative endpoint)
GET /api/pedidos/cliente/123?date_from=2025-01-01&per_page=20
```

---

### 4. Precios (Prices) - `/api/precios`

**Authentication:** Required (Bearer Token)

**Filters:**
- `product_ids`: Comma-separated product IDs
- `skus`: Comma-separated SKUs
- `category_id`: Filter by category
- `brand_id`: Filter by brand
- `min_price`: Minimum price
- `max_price`: Maximum price

**Examples:**

```bash
# Get all prices sorted by SKU
GET /api/precios?sort_by=sku&sort_direction=asc&per_page=50

# Get prices for specific products
GET /api/precios?product_ids=1,2,3,4,5

# Get prices by SKUs
GET /api/precios?skus=ABC123,DEF456,GHI789

# Get prices for a category in price range
GET /api/precios?category_id=5&min_price=50&max_price=200&sort_by=price

# Get first 100 prices (non-paginated)
GET /api/precios?limit=100&offset=0&sort_by=sku

# Get prices for a brand
GET /api/precios?brand_id=10&sort_by=price&sort_direction=asc
```

---

### 5. Inventarios (Inventory) - `/api/inventarios`

**Authentication:** Required (Bearer Token)

**Filters:**
- `bodega_code`: Filter by warehouse code
- `product_id`: Filter by product ID
- `product_ids`: Comma-separated product IDs
- `sku`: Filter by SKU
- `skus`: Comma-separated SKUs
- `min_available`: Minimum available quantity
- `max_available`: Maximum available quantity
- `active_products_only`: Show only active products (default: true)
- `low_stock_only`: Show only low stock items (for byBodega)
- `out_of_stock_only`: Show only out of stock items (for byBodega)

**Examples:**

```bash
# Get inventory for all products in a warehouse
GET /api/inventarios?bodega_code=BOD01&sort_by=available&sort_direction=asc&per_page=50

# Get inventory for specific products
GET /api/inventarios?product_ids=1,2,3,4,5&per_page=25

# Get inventory by SKUs
GET /api/inventarios?skus=ABC123,DEF456&bodega_code=BOD01

# Get low stock items
GET /api/inventarios?min_available=0&max_available=10&sort_by=available

# Get inventory for a specific product
GET /api/inventarios/producto/123?bodega_code=BOD01

# Get inventory summary for a warehouse
GET /api/inventarios/bodega/BOD01?per_page=100

# Get out of stock items in a warehouse
GET /api/inventarios/bodega/BOD01?out_of_stock_only=true&per_page=50

# Get low stock items in a warehouse
GET /api/inventarios/bodega/BOD01?low_stock_only=true&per_page=30
```

---

## Combining Multiple Parameters

You can combine filtering, sorting, and pagination in a single request:

```bash
# Complex example: Get customer orders from January 2025, 
# with minimum total of $100, sorted by date descending, 
# 25 items per page
GET /api/pedidos?user_id=123&date_from=2025-01-01&date_to=2025-01-31&min_total=100&sort_by=created_at&sort_direction=desc&per_page=25

# Get products in a category, price range, 
# with inventory from specific warehouse, 
# sorted by price ascending
GET /api/productos?category_id=10&min_price=50&max_price=200&bodega_code=BOD01&sort_by=price&sort_direction=asc&per_page=30

# Get prices for specific SKUs sorted by price
GET /api/precios?skus=ABC123,DEF456,GHI789&sort_by=price&sort_direction=asc
```

---

## Best Practices

### 1. Use Pagination for Large Datasets
```bash
# Good: Paginated request
GET /api/productos?per_page=50

# Avoid: Getting all data at once
GET /api/productos?limit=10000
```

### 2. Use Specific Filters
```bash
# Good: Specific filters
GET /api/pedidos?user_id=123&date_from=2025-01-01

# Avoid: No filters on large datasets
GET /api/pedidos
```

### 3. Sort for Consistent Results
```bash
# Good: Always specify sort order
GET /api/clientes?sort_by=id&sort_direction=asc

# Avoid: Relying on default order
GET /api/clientes
```

### 4. Use Limit/Offset for Exports
```bash
# Good: For data exports or custom pagination
GET /api/productos?limit=100&offset=0
GET /api/productos?limit=100&offset=100
GET /api/productos?limit=100&offset=200
```

---

## Performance Tips

1. **Use Indexes**: Most sortable fields are indexed for performance
2. **Limit Results**: Don't request more data than needed
3. **Cache Responses**: Consider caching frequently requested data
4. **Use Specific Filters**: More specific queries = faster responses
5. **Avoid Deep Pagination**: Use limit/offset for large offsets instead of high page numbers

---

## Error Responses

### Invalid Sort Field
```json
{
  "data": [...],
  "message": "Sort applied with default field due to invalid sort_by parameter"
}
```

### Exceeded Maximum
```json
{
  "error": "per_page exceeds maximum allowed value of 100"
}
```

---

## Response Examples

### Paginated Response
```json
{
  "data": [
    {
      "id": 1,
      "name": "Product 1",
      ...
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 20,
    "total": 200,
    "from": 1,
    "to": 20
  }
}
```

### Limit/Offset Response
```json
{
  "data": [
    {
      "id": 1,
      "name": "Product 1",
      ...
    }
  ],
  "meta": {
    "count": 50,
    "limit": 50,
    "offset": 100
  }
}
```

---

## Additional Resources

- [API Authentication](api-documentation.md)
- [API Endpoints Reference](api-documentation.md)
- [Error Handling](api-documentation.md)

---

## Changelog

- **2025-11-05**: Added comprehensive filtering, sorting, limit, and offset support to all API endpoints
- **2025-11-05**: Created unified ApiPaginationTrait for consistent behavior across endpoints

