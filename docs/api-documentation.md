# API Documentation

## Authentication

All API endpoints require authentication using Laravel Sanctum. To authenticate:

1. **Generate API Token**: Create a token for your user account
2. **Include Token**: Add the token to the `Authorization` header as `Bearer {token}`

### Example Token Generation

```php
// Generate a token for a user
$user = User::find(1);
$token = $user->createToken('API Token')->plainTextToken;
```

### Example Request Headers

```bash
Authorization: Bearer {your-api-token}
Content-Type: application/json
Accept: application/json
```

## Available Endpoints

All endpoints support pagination and filtering. Base URL: `/api/`

### 1. Clientes (Customers/Users)

#### GET `/api/clientes`

Get a paginated list of customers.

**Parameters:**

-   `search` (optional): Search by name, email, or document
-   `city_id` (optional): Filter by city ID
-   `zone` (optional): Filter by zone
-   `per_page` (optional): Items per page (max 100, default 15)

**Example Response:**

```json
{
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "document": "12345678",
            "city": {
                "id": 1,
                "name": "Bogotá"
            },
            "roles": []
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75
    }
}
```

#### GET `/api/clientes/{id}`

Get a specific customer by ID.

### 2. Productos (Products)

#### GET `/api/productos`

Get a paginated list of products.

**Parameters:**

-   `search` (optional): Search by name, description, or SKU
-   `category_id` (optional): Filter by category ID
-   `brand_id` (optional): Filter by brand ID
-   `sku` (optional): Filter by specific SKU
-   `min_price` / `max_price` (optional): Price range filter
-   `bodega_code` (optional): Include inventory for specific warehouse
-   `sort_by` (optional): Sort by name, price, created_at, or sales_count
-   `sort_direction` (optional): asc or desc
-   `per_page` (optional): Items per page (max 100, default 15)

**Example Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Product Name",
      "sku": "PROD-001",
      "price": 100.00,
      "final_price": {
        "price": 90.00,
        "discount": 10.00
      },
      "inventory": 25,
      "brand": {
        "id": 1,
        "name": "Brand Name"
      },
      "categories": [],
      "images": []
    }
  ],
  "pagination": {...}
}
```

#### GET `/api/productos/{id}`

Get a specific product with full details.

### 3. Precios (Prices)

#### GET `/api/precios`

Get product pricing information.

**Parameters:**

-   `product_ids` (optional): Comma-separated list of product IDs
-   `skus` (optional): Comma-separated list of SKUs
-   `category_id` / `brand_id` (optional): Filter by category or brand
-   `per_page` (optional): Items per page (max 200, default 50)

**Example Response:**

```json
{
  "data": [
    {
      "product_id": 1,
      "sku": "PROD-001",
      "name": "Product Name",
      "base_price": 100.00,
      "discount": 10.00,
      "final_price": {
        "price": 90.00,
        "discount": 10.00
      },
      "tax_rate": 19.0,
      "price_with_tax": 107.10
    }
  ],
  "pagination": {...}
}
```

### 4. Promociones (Promotions/Coupons)

#### GET `/api/promociones`

Get available promotions and coupons.

**Parameters:**

-   `search` (optional): Search by name, code, or description
-   `type` (optional): Filter by coupon type (fixed_amount, percentage)
-   `applies_to` (optional): Filter by what the coupon applies to
-   `active_only` (optional): Only active coupons (default true)
-   `valid_only` (optional): Only currently valid coupons (default false)

#### POST `/api/promociones/validar`

Validate a coupon code.

**Request Body:**

```json
{
    "code": "SAVE10",
    "user_id": 1,
    "cart_total": 100.0
}
```

**Response:**

```json
{
    "valid": true,
    "coupon": {
        "id": 1,
        "code": "SAVE10",
        "name": "10% Discount",
        "type": "percentage",
        "value": 10.0,
        "discount_amount": 10.0
    }
}
```

### 5. Inventarios (Inventory)

#### GET `/api/inventarios`

Get product inventory information.

**Parameters:**

-   `bodega_code` (optional): Filter by warehouse code
-   `product_id` / `product_ids` (optional): Filter by product(s)
-   `sku` / `skus` (optional): Filter by SKU(s)
-   `min_available` / `max_available` (optional): Filter by stock levels
-   `active_products_only` (optional): Only active products (default true)

#### GET `/api/inventarios/producto/{id}`

Get inventory for a specific product across all warehouses.

#### GET `/api/inventarios/bodega/{code}`

Get inventory summary for a specific warehouse.

**Example Response:**

```json
{
  "bodega_code": "BOG01",
  "summary": {
    "total_products": 150,
    "out_of_stock": 5,
    "low_stock": 12
  },
  "data": [
    {
      "product_id": 1,
      "product_name": "Product Name",
      "product_sku": "PROD-001",
      "bodega_code": "BOG01",
      "available": 25,
      "physical": 30,
      "reserved": 5,
      "safety_stock": 10,
      "is_low_stock": false,
      "is_out_of_stock": false
    }
  ],
  "pagination": {...}
}
```

### 6. Pedidos (Orders)

#### GET `/api/pedidos`

Get a paginated list of orders.

**Parameters:**

-   `user_id` / `seller_id` / `zone_id` (optional): Filter by user, seller, or zone
-   `status_id` (optional): Filter by order status
-   `date_from` / `date_to` (optional): Filter by creation date range
-   `delivery_date_from` / `delivery_date_to` (optional): Filter by delivery date
-   `min_total` / `max_total` (optional): Filter by order total
-   `search` (optional): Search by order ID or customer info

#### GET `/api/pedidos/{id}`

Get a specific order with full details including products.

#### GET `/api/pedidos/cliente/{customer_id}`

Get orders for a specific customer.

**Example Response:**

```json
{
  "data": [
    {
      "id": 123,
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "total": 150.00,
      "status_id": 1,
      "status_name": "Pendiente",
      "delivery_date": "2024-01-15",
      "products_count": 3,
      "created_at": "2024-01-10T10:00:00Z"
    }
  ],
  "pagination": {...}
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

## Rate Limiting

API requests are limited to 1000 requests per minute per user.

## Usage Examples

### Using cURL

```bash
# Get products
curl -X GET "https://your-domain.com/api/productos" \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"

# Get products with inventory for specific warehouse
curl -X GET "https://your-domain.com/api/productos?bodega_code=BOG01&per_page=20" \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"

# Validate coupon
curl -X POST "https://your-domain.com/api/promociones/validar" \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -d '{"code":"SAVE10","user_id":1,"cart_total":100.00}'
```

### Using JavaScript/Fetch

```javascript
const apiToken = "your-api-token";
const baseURL = "https://your-domain.com/api";

const headers = {
    Authorization: `Bearer ${apiToken}`,
    "Content-Type": "application/json",
    Accept: "application/json",
};

// Get products
const getProducts = async () => {
    const response = await fetch(`${baseURL}/productos`, { headers });
    return await response.json();
};

// Validate coupon
const validateCoupon = async (code, userId, cartTotal) => {
    const response = await fetch(`${baseURL}/promociones/validar`, {
        method: "POST",
        headers,
        body: JSON.stringify({
            code,
            user_id: userId,
            cart_total: cartTotal,
        }),
    });
    return await response.json();
};
```

## Status Codes

-   `200 OK`: Request successful
-   `401 Unauthorized`: Invalid or missing authentication token
-   `404 Not Found`: Resource not found
-   `422 Unprocessable Entity`: Validation errors
-   `429 Too Many Requests`: Rate limit exceeded
-   `500 Internal Server Error`: Server error

## Pagination

All list endpoints support pagination with the following structure:

```json
{
  "data": [...],
  "pagination": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

Use the `per_page` parameter to control the number of items returned (with maximum limits as specified for each endpoint).
