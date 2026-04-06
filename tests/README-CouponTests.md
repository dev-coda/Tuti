# Coupon XML & Workflow Test Suite

Smoke tests for the multicoupon workflow, XML rules, and corner cases.

## Running the tests

Use the Pest binary (the `test` artisan namespace is used by custom commands):

```bash
./vendor/bin/pest tests/Feature/CouponXmlTest.php
```

Or with PHPUnit directly:

```bash
./vendor/bin/phpunit tests/Feature/CouponXmlTest.php
```

### Database driver (SQLite vs PostgreSQL)

Tests use **SQLite in-memory** when the `pdo_sqlite` PHP extension is available (typical local dev).

On many servers **SQLite is not installed** (`could not find driver`). Then tests use **PostgreSQL** with credentials from your `.env`, and a **separate database name** so production data is not touched:

- Default name: `{DB_DATABASE from .env}_phpunit` (e.g. `apptuti_phpunit`)
- Override: set `PHPUNIT_DB_DATABASE`, `PHPUNIT_DB_HOST`, `PHPUNIT_DB_USERNAME`, `PHPUNIT_DB_PASSWORD` as needed

Create the database once (PostgreSQL). **Use the app's DB connection** so host/user match:

```bash
php artisan test:create-db
```

Or manually with the same host/user as `.env`:

```bash
PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -p $DB_PORT -U $DB_USERNAME -d postgres -c "CREATE DATABASE apptuti_phpunit;"
```

(Adjust the name to match your `DB_DATABASE` + `_phpunit` or `PHPUNIT_DB_DATABASE`.)

Alternatively install SQLite for PHP:

```bash
# Debian/Ubuntu example
sudo apt install php8.2-sqlite3
```

### Doctrine DBAL

If migrations fail with “requires Doctrine DBAL”:

```bash
composer require --dev doctrine/dbal
```

## Coverage

| Category | Tests |
|----------|-------|
| **XML rules** | Percentage → `dyn:discount`, fixed → reduced `unitPrice` + `discount=0` |
| **Single coupons** | Percentage cart, fixed amount cart, coupon contribution vs existing discount |
| **Client-specific** | `APPLIES_TO_CUSTOMER` – applies when user in list, contributes 0 when not |
| **Multiple coupons** | Best discount per product (percentage vs percentage, percentage vs fixed), proportional fixed distribution |
| **applies_to** | Product, brand, category, cart |
| **Package pricing** | `calculate_package_price` with percentage coupon |
| **Mixed cart** | Some products with coupon, some without |
| **Minimum amount** | Validation rejects when cart below minimum |
| **No applicable** | Empty/wrong applies_to_ids returns failure |
| **Mixed XML** | Order with both % and fixed products → correct XML output, fixed-amount floor protection |
