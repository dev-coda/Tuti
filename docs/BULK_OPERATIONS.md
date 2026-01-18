# Bulk Operations System

## Overview

The Bulk Operations system provides a centralized interface for executing large-scale data operations asynchronously. Currently, it supports bulk synchronization of client data from the SOAP backend.

## Features

### 1. Bulk Client Data Synchronization

**Purpose:** Update all client information from the SOAP backend in a single operation.

**What it syncs:**
- User profile data (name, phones, whatsapp, business name)
- Account information (account_num, balance, quota_value)
- Customer classification (customer_type, price_group, tax_group, customer_status)
- Location data (city_code, county_id)
- Business rules (line_discount, is_locked, order_sequence)
- All customer zones/routes

**Process:**
1. Queries all users with role 'client' who have a document number
2. Dispatches an async job to the queue system
3. Iterates through each client, calling `UserRepository::syncUserRuteroData()`
4. Tracks which fields were updated for each client
5. Generates a detailed CSV report when complete

## Usage

### Accessing Bulk Operations

1. Navigate to **Admin Panel → Configuraciones → Procesos Masivos**
2. The page shows:
   - Available bulk operations
   - Number of clients that will be synced
   - Generated reports list

### Running Bulk Client Sync

1. Click **"Iniciar Sincronización Masiva"**
2. Confirm the operation
3. The system will:
   - Display a success message with session ID
   - Start processing in the background
   - Generate a report when complete

### Downloading Reports

1. Reports appear in the "Reportes Generados" section
2. Click **"Descargar"** to download the CSV
3. Reports can be deleted using the **"Eliminar"** button

## CSV Report Format

The generated CSV includes the following columns:

| Column | Description |
|--------|-------------|
| User ID | Database ID of the user |
| User Email | User's email address |
| User Name | User's full name |
| Document | User's document number (ID/NIT) |
| Status | `success`, `failed`, `error`, or `skipped` |
| Updated Fields | Comma-separated list of fields that were updated |
| Zones Synced | Number of zones after sync |
| Zones Changed | Whether the zone count changed (Yes/No) |
| Error | Error message if sync failed |
| Processed At | Timestamp of when this user was processed |

### Sample Report

```csv
User ID,User Email,User Name,Document,Status,Updated Fields,Zones Synced,Zones Changed,Error,Processed At
123,juan@example.com,Juan Pérez,12345678,success,"balance, quota_value, phone",3,No,,2026-01-18 14:30:00
124,maria@example.com,María García,87654321,success,"name, business_name, city_code, price_group",2,Yes,,2026-01-18 14:30:15
125,error@example.com,,,skipped,,0,N/A,No document number,2026-01-18 14:30:16
```

## Automatic Sync During Order Processing

**Confirmed:** User data IS automatically synced when placing orders.

Location: `app/Http/Controllers/CartController.php` (lines 640-657)

```php
if ($actingUser && $actingUser->document) {
    try {
        \Log::info('Syncing rutero data before order processing', [
            'user_id' => $actingUser->id,
            'document' => $actingUser->document,
        ]);
        UserRepository::syncUserRuteroData($actingUser);
        // Reload zones after sync
        $actingUser->refresh();
        $actingUser->load('zones');
    } catch (\Throwable $th) {
        \Log::warning('Failed to sync rutero data before order processing', [
            'user_id' => $actingUser->id,
            'error' => $th->getMessage(),
        ]);
        // Continue with existing data if sync fails
    }
}
```

**When it happens:**
- Every time a user (or seller on behalf of a client) processes an order
- Before the order is created
- Ensures the most up-to-date customer data is used

## Technical Implementation

### Job: `BulkSyncClientsData`

**File:** `app/Jobs/BulkSyncClientsData.php`

**Key Features:**
- Timeout: 2 hours (7200 seconds)
- Single attempt (no retries to avoid duplicate processing)
- Queue: `default`
- Connection: Uses configured queue connection (database if sync)

**Methods:**
- `handle()`: Main processing loop
- `generateReport()`: Creates CSV report
- `failed()`: Handles job failures

### Controller: `BulkOperationsController`

**File:** `app/Http/Controllers/Admin/BulkOperationsController.php`

**Routes:**
- `GET /admin/bulk-operations` - View page
- `POST /admin/bulk-operations/sync-clients-data` - Start sync
- `GET /admin/bulk-operations/reports/{filename}/download` - Download report
- `DELETE /admin/bulk-operations/reports/{filename}` - Delete report

### Storage

**Reports Directory:** `storage/app/reports/`

**File Naming:** `bulk-client-sync-{YYYYMMDDHHmmss}-{random}.csv`

Example: `bulk-client-sync-20260118143000-a7b3c9d2.csv`

## Best Practices

### When to Run Bulk Sync

1. **After SOAP Backend Updates**: When the external system has been updated
2. **Periodic Maintenance**: Monthly/quarterly to ensure data consistency
3. **After Data Issues**: If you suspect client data is out of sync
4. **Migration/Onboarding**: When importing a large number of new clients

### Performance Considerations

- Process runs asynchronously (doesn't block admin panel)
- Average time: ~0.5-1 second per client
- 1000 clients ≈ 10-15 minutes
- Queue workers must be running (`php artisan queue:work`)

### Monitoring

Check logs for progress:

```bash
tail -f storage/logs/laravel.log | grep "Bulk client sync"
```

Look for:
- `Starting bulk client sync`
- `Bulk client sync progress` (every 10 users)
- `Bulk client sync completed`
- `Bulk client sync report generated`

### Error Handling

The job will:
- Skip users without document numbers
- Continue processing if individual syncs fail
- Log all errors
- Always generate a report (even if partial)

## Security

- Admin-only access (requires authentication)
- Actions are logged with user information
- Confirmation required before starting bulk operations
- Reports contain sensitive data (only accessible to admins)

## Future Enhancements

Potential additions to the Bulk Operations system:

1. **Bulk Price Updates**: Sync product prices for all products
2. **Bulk Inventory Sync**: Update inventory for all products/warehouses
3. **Bulk Order Processing**: Retry failed orders in bulk
4. **Email Notifications**: Notify admins when bulk operations complete
5. **Progress Tracking**: Real-time progress bar in the UI
6. **Scheduling**: Schedule bulk operations for off-peak hours

## Troubleshooting

### Reports Not Generating

**Check:**
1. Queue workers are running: `php artisan queue:work`
2. Storage directory is writable: `storage/app/reports/`
3. Check logs: `tail -f storage/logs/laravel.log`

### Sync Taking Too Long

**Solutions:**
1. Increase job timeout in `BulkSyncClientsData`
2. Split into smaller batches
3. Check external SOAP service response times
4. Increase queue worker count

### Partial Syncs

**If only some clients sync:**
- Check the CSV report for errors
- Look for patterns (specific document types, missing data)
- Review SOAP service availability during sync

## Related Documentation

- [Rutero Sync Fix](./fixes/RUTERO_SYNC_FIX.md)
- [Deployment Guide](./DEPLOYMENT.md)
- [Queue Setup](./README-QUEUE.md)

## Change Log

- **2026-01-18**: Initial implementation of bulk operations system
  - Added BulkSyncClientsData job
  - Added BulkOperationsController
  - Added bulk operations admin interface
  - Added CSV report generation
  - Confirmed automatic sync during order processing
