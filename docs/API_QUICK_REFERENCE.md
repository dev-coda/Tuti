# API Quick Reference - Filtering, Sorting & Pagination

## Quick Parameter Reference

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `per_page` | int | Items per page (paginated) | `?per_page=25` |
| `limit` | int | Max items to return (non-paginated) | `?limit=100` |
| `offset` | int | Items to skip (non-paginated) | `?offset=50` |
| `sort_by` / `order_by` | string | Field to sort by | `?sort_by=created_at` |
| `sort_direction` / `order` | string | Sort direction (asc/desc) | `?sort_direction=desc` |

## Common Query Patterns

### 📄 Pagination (Standard)
```bash
# Page 1 with 25 items
GET /api/clientes?per_page=25

# Page 2 with 25 items  
GET /api/clientes?per_page=25&page=2
```

### 🎯 Limit & Offset (Custom Pagination)
```bash
# First 50 items
GET /api/productos?limit=50&offset=0

# Next 50 items
GET /api/productos?limit=50&offset=50

# Third batch of 50
GET /api/productos?limit=50&offset=100
```

### 🔼 Sorting
```bash
# Sort by name (ascending)
GET /api/clientes?sort_by=name&sort_direction=asc

# Sort by date (descending)
GET /api/pedidos?sort_by=created_at&sort_direction=desc

# Sort by price (ascending)
GET /api/productos?order_by=price&order=asc
```

### 🔍 Filtering

#### Text Search
```bash
GET /api/clientes?search=john
GET /api/productos?search=laptop
```

#### Exact Match
```bash
GET /api/productos?category_id=5
GET /api/pedidos?status_id=1
GET /api/clientes?zone=norte
```

#### Range Filters
```bash
# Price range
GET /api/productos?min_price=100&max_price=500

# Date range
GET /api/pedidos?date_from=2025-01-01&date_to=2025-12-31

# Amount range
GET /api/pedidos?min_total=100&max_total=1000
```

#### Multiple Values
```bash
GET /api/precios?product_ids=1,2,3,4,5
GET /api/inventarios?skus=ABC,DEF,GHI
```

#### Boolean
```bash
GET /api/clientes?has_whatsapp=true
GET /api/inventarios?active_products_only=false
```

## Common Combinations

### Get Recent Orders
```bash
GET /api/pedidos?sort_by=created_at&sort_direction=desc&per_page=20
```

### Search & Sort
```bash
GET /api/productos?search=laptop&sort_by=price&sort_direction=asc&per_page=15
```

### Filter, Sort & Paginate
```bash
GET /api/pedidos?user_id=123&date_from=2025-01-01&sort_by=total&sort_direction=desc&per_page=25
```

### Export Data (Non-Paginated)
```bash
GET /api/clientes?limit=1000&offset=0&sort_by=id&sort_direction=asc
```

## Endpoint Limits

| Endpoint | Default per_page | Max per_page | Max limit |
|----------|-----------------|--------------|-----------|
| `/api/clientes` | 15 | 100 | 1000 |
| `/api/productos` | 15 | 100 | 1000 |
| `/api/pedidos` | 15 | 100 | 1000 |
| `/api/precios` | 50 | 200 | 1000 |
| `/api/inventarios` | 50 | 200 | 1000 |

## cURL Examples

### Get Paginated Customers
```bash
curl -X GET "https://api.example.com/api/clientes?per_page=25&sort_by=name" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Get Products with Filters
```bash
curl -X GET "https://api.example.com/api/productos?category_id=5&min_price=100&sort_by=price&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Get Orders in Date Range
```bash
curl -X GET "https://api.example.com/api/pedidos?date_from=2025-01-01&date_to=2025-01-31&sort_by=created_at&sort_direction=desc&per_page=50" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Export Inventory (Non-Paginated)
```bash
curl -X GET "https://api.example.com/api/inventarios?bodega_code=BOD01&limit=500&offset=0&sort_by=product_id" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## JavaScript/Axios Examples

### Get Paginated Data
```javascript
const response = await axios.get('/api/clientes', {
  params: {
    per_page: 25,
    sort_by: 'name',
    sort_direction: 'asc'
  },
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

console.log(response.data.data); // Items
console.log(response.data.pagination); // Pagination info
```

### Get Filtered Products
```javascript
const response = await axios.get('/api/productos', {
  params: {
    search: 'laptop',
    category_id: 5,
    min_price: 100,
    max_price: 500,
    sort_by: 'price',
    per_page: 20
  },
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### Get Data with Limit/Offset
```javascript
const response = await axios.get('/api/pedidos', {
  params: {
    user_id: 123,
    limit: 50,
    offset: 0,
    sort_by: 'created_at',
    sort_direction: 'desc'
  },
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

console.log(response.data.data); // Items
console.log(response.data.meta); // Meta info (count, limit, offset)
```

## PHP/Laravel Examples

### Using HTTP Client
```php
$response = Http::withToken($token)->get('https://api.example.com/api/clientes', [
    'per_page' => 25,
    'sort_by' => 'name',
    'sort_direction' => 'asc',
    'search' => 'john'
]);

$clients = $response->json('data');
$pagination = $response->json('pagination');
```

### Building Query String
```php
$params = http_build_query([
    'category_id' => 5,
    'min_price' => 100,
    'max_price' => 500,
    'sort_by' => 'price',
    'per_page' => 20
]);

$url = "https://api.example.com/api/productos?{$params}";
```

## Python/Requests Examples

### Get Paginated Data
```python
import requests

headers = {
    'Authorization': f'Bearer {token}',
    'Accept': 'application/json'
}

params = {
    'per_page': 25,
    'sort_by': 'name',
    'sort_direction': 'asc'
}

response = requests.get(
    'https://api.example.com/api/clientes',
    headers=headers,
    params=params
)

data = response.json()
clients = data['data']
pagination = data['pagination']
```

### Get with Multiple Filters
```python
params = {
    'date_from': '2025-01-01',
    'date_to': '2025-01-31',
    'status_id': 1,
    'min_total': 100,
    'sort_by': 'total',
    'sort_direction': 'desc',
    'per_page': 50
}

response = requests.get(
    'https://api.example.com/api/pedidos',
    headers=headers,
    params=params
)
```

## Response Formats

### Paginated Response
```json
{
  "data": [...],
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
  "data": [...],
  "meta": {
    "count": 50,
    "limit": 50,
    "offset": 100
  }
}
```

## Tips & Tricks

### 💡 Pagination vs Limit/Offset

**Use Pagination when:**
- Building a UI with page numbers
- You need metadata (total pages, current page)
- Showing results to end users

**Use Limit/Offset when:**
- Exporting data
- Implementing custom pagination
- Building infinite scroll
- Batch processing

### 💡 Performance Tips

1. Always specify sorting for consistent results
2. Use specific filters to reduce dataset size
3. Don't request more data than you need
4. Cache frequently accessed data
5. Use limit/offset for large offsets instead of high page numbers

### 💡 Common Mistakes

❌ **Don't:**
```bash
# No filters on large dataset
GET /api/pedidos?per_page=1000

# Deep pagination
GET /api/productos?per_page=10&page=10000
```

✅ **Do:**
```bash
# Use specific filters
GET /api/pedidos?user_id=123&date_from=2025-01-01&per_page=50

# Use limit/offset for large offsets
GET /api/productos?limit=10&offset=100000
```

## See Also

- [Detailed API Documentation](API_FILTERING_PAGINATION.md)
- [Authentication Guide](api-documentation.md)
- [Error Handling](api-documentation.md)

