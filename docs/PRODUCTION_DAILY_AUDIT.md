# Production Daily Audit Report - Quick Start

## Two Ways to Generate the Report

### Option 1: Web Interface (Recommended) ✨

1. **Login to Admin Panel**
   - Go to your admin dashboard
   - Navigate to **Reportes** (Reports)

2. **Generate Audit Report**
   - Select "Auditoría Diaria de Pedidos" from the dropdown
   - Choose the date you want to audit (defaults to yesterday)
   - Click "Generar Reporte"
   - The Excel file will download immediately

**Advantages:**
- No SSH access needed
- Instant download
- Easy date selection
- Visual interface

### Option 2: Command Line

#### Step 1: SSH into Production Server
```bash
ssh your-production-server
cd /path/to/tuti/project
```

#### Step 2: Generate Yesterday's Report
```bash
php artisan orders:daily-audit
```

#### Step 3: Download the Report
The report will be saved in `storage/app/reports/`. The exact path will be shown in the command output.

```bash
# Example output:
# File path: /var/www/tuti/storage/app/reports/orders_audit_20260121_1737564321.xlsx
```

To download the file to your local machine:
```bash
# From your local machine:
scp your-production-server:/var/www/tuti/storage/app/reports/orders_audit_*.xlsx ~/Downloads/
```

Or use an SFTP client like FileZilla to download from the `storage/app/reports/` directory.

## What the Report Shows

### Critical Columns to Review:

1. **Precio Sospechoso (<$500)** - Shows "SÍ" if the order has products priced suspiciously low **IN THE SOAP XML**
2. **Productos Sospechosos** - Lists the actual SKUs and prices from the SOAP request
3. **Tiene Package Quantity** - Shows "SÍ" if the order has bulk/package quantities
4. **Tiene Bonificación** - Shows "SÍ" if the order includes promotional/bonification items

### Important: SOAP Price Checking ⚠️
The report checks the **actual prices sent to the SOAP webservice**, not the database prices. This is critical because:
- Database prices may differ from what was actually transmitted
- SOAP prices are what the external system received
- This is the authoritative source for pricing audits

### Suspicious Pricing Alert
Products priced below $500 **in the SOAP XML** may indicate:
- Pricing errors in the calculation logic
- Missing decimal points
- Discount calculation issues
- Configuration issues that need immediate attention

## For Different Dates

### Get Report for a Specific Date
```bash
# Format: YYYY-MM-DD
php artisan orders:daily-audit 2026-01-20
```

### Get Report for Last Week
```bash
# Monday
php artisan orders:daily-audit 2026-01-15
# Tuesday
php artisan orders:daily-audit 2026-01-16
# And so on...
```

## Summary Statistics

The command will show you a quick summary table like:

```
+----------------------------------+-------+------------+
| Metric                           | Count | Percentage |
+----------------------------------+-------+------------+
| Total Orders                     | 150   | 100%       |
| With Package Quantity            | 45    | 30.0%      |
| With Bonification                | 23    | 15.3%      |
| With Suspicious Pricing (<$500)  | 8     | 5.3%       |
+----------------------------------+-------+------------+
```

## Action Items Based on Report

### If "With Suspicious Pricing" > 0
1. Open the Excel file
2. Filter by "Precio Sospechoso" = "SÍ"
3. Review the "Productos Sospechosos" column
4. Investigate each flagged order in the admin panel
5. Contact customers if pricing errors are confirmed

### If "With Package Quantity" is Unexpected
1. Verify inventory levels are correct
2. Check that package quantity pricing is applied correctly

### If "With Bonification" is Unexpected
1. Verify bonification rules are working as intended
2. Check that bonification inventory is tracked separately

## Troubleshooting

### Command Not Found
Make sure you're in the correct directory and the files have been deployed:
```bash
# Check if the command exists
php artisan list | grep daily-audit

# If not found, check if files exist
ls -la app/Console/Commands/GenerateDailyAuditReport.php
ls -la app/Exports/OrdersDailyAuditExport.php
```

### Permission Issues
```bash
# Make sure storage directory is writable
chmod -R 775 storage/app/reports
chown -R www-data:www-data storage/app/reports
```

### No Data in Report
- Verify the date is correct
- Check if orders exist for that date:
  ```bash
  php artisan tinker
  >>> \App\Models\Order::whereDate('created_at', '2026-01-21')->count()
  ```

## Files Location

All generated reports are stored in:
```
storage/app/reports/orders_audit_YYYYMMDD_timestamp.xlsx
```

## Cleanup

Reports are stored permanently. To clean up old reports:
```bash
# Delete reports older than 30 days
find storage/app/reports/orders_audit_*.xlsx -mtime +30 -delete
```

## Need Help?

1. Check Laravel logs: `storage/logs/laravel.log`
2. Review full documentation: `docs/DAILY_AUDIT_REPORT.md`
3. Contact the development team with the error message and date you tried to query
