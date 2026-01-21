# Daily Sales Report (Ventas Diarias)

## Overview

New comprehensive dashboard for daily sales reporting that replaces manual Excel files. Provides real-time analytics, advanced filtering, and multi-sheet Excel exports.

## Features

### 1. **Interactive Dashboard**
- **KPI Cards**: Total orders, total sales, and average ticket
- **Real-time Data**: Pulls directly from the database
- **Date Range Filtering**: Select any date range
- **Seller Filtering**: Filter by specific seller or view all
- **Responsive Design**: Works on desktop and mobile

### 2. **Data Table**
- Displays all processed orders with:
  - Order number (clickable to view details)
  - Date and time
  - Client name
  - Seller name
  - Zone and route
  - Order value
  - Status
- Pagination for large datasets (50 orders per page)
- Hover effects for better UX

### 3. **Excel Export**
The export generates a multi-sheet Excel file similar to your original format:

#### Sheet 1: BD (Base de Datos)
Contains all individual orders with columns:
- REGIONAL (Region)
- PEDIDO TUTI (Order number)
- FECHA Y HORA (Date and time)
- CLIENTE (Client name)
- VENDEDOR (Seller name)
- ESTADO (Status)
- VALOR (Order value)
- ZONA (Zone)
- RUTA (Route)

#### Sheet 2: Resumen por Región
Summary statistics by region:
- REGIÓN
- TOTAL PEDIDOS (Total orders)
- VENTAS TOTALES (Total sales)
- TICKET PROMEDIO (Average ticket)

#### Sheet 3: Resumen por Vendedor
Summary statistics by seller:
- VENDEDOR
- TOTAL PEDIDOS
- VENTAS TOTALES
- TICKET PROMEDIO

## How to Access

1. Go to **Admin Panel** → **Reportes**
2. Click on the **"Ventas Diarias"** card (purple card with dollar icon)
3. Use filters to customize your view
4. Click **"Excel"** button to download the report

## Customization Needed

### Region Mapping

The current region mapping is simplified. You need to update the `getRegion()` method in `app/Exports/DailySalesExport.php` to match your actual zone-to-region mapping:

```php
private function getRegion($zone): string
{
    if (!$zone) return 'N/A';
    
    $zoneCode = $zone->zone ?? '';
    
    // UPDATE THIS MAPPING based on your actual data
    if (str_starts_with($zoneCode, '1')) return 'MEDELLIN';
    if (str_starts_with($zoneCode, '4')) return 'CUCUTA';
    if (str_starts_with($zoneCode, '6')) return 'PEREIRA';
    if (str_starts_with($zoneCode, '7')) return 'BARRANQUILLA';
    if (str_starts_with($zoneCode, '5')) return 'MONTERIA';
    if (str_starts_with($zoneCode, '9')) return 'BOGOTA';
    if (str_starts_with($zoneCode, '62')) return 'CALI';
    
    return 'OTROS';
}
```

### Adding Monthly Historic Sheets

If you need to add monthly historic sheets (like "Enero", "Febrero", etc.) to the Excel export, you can:

1. **Option A**: Generate separate exports for each month
2. **Option B**: Add monthly summary sheets to the current export

To add a monthly summary sheet, add this to `app/Exports/DailySalesExport.php`:

```php
public function sheets(): array
{
    $sheets = [];
    
    // Main data sheet (BD)
    $sheets[] = new DailySalesDataSheet($this->dateFrom, $this->dateTo, $this->region, $this->seller);
    
    // Summary sheet by region
    $sheets[] = new DailySalesRegionSummarySheet($this->dateFrom, $this->dateTo, $this->region, $this->seller);
    
    // Summary sheet by seller
    $sheets[] = new DailySalesSellerSummarySheet($this->dateFrom, $this->dateTo, $this->region, $this->seller);
    
    // Add monthly sheets if date range spans multiple months
    $months = $this->getMonthsBetween($this->dateFrom, $this->dateTo);
    foreach ($months as $month) {
        $sheets[] = new DailySalesMonthlySheet($month['start'], $month['end'], $month['name']);
    }
    
    return $sheets;
}
```

## Database Requirements

The report queries the following tables:
- `orders` (main data source)
- `users` (for client and seller names)
- `zones` (for zone and route information)

Filters only **processed orders** (status_id = `Order::STATUS_PROCESSED`).

## Performance Considerations

- Data table uses pagination (50 items per page)
- Excel export processes all matching records (no pagination)
- For large date ranges (>1 year), consider adding a queue job for exports
- KPIs are calculated on each page load (consider caching for better performance)

## Routes

- Dashboard: `/admin/reports/daily-sales`
- Export: `/admin/reports/daily-sales/export`

## Files Created/Modified

### New Files:
- `app/Exports/DailySalesExport.php` - Export logic with multiple sheets
- `resources/views/admin/reports/daily-sales.blade.php` - Dashboard view
- `docs/DAILY_SALES_REPORT.md` - This documentation

### Modified Files:
- `app/Http/Controllers/Admin/ReportController.php` - Added dailySales() and exportDailySales() methods
- `routes/admin.php` - Added daily sales routes
- `resources/views/admin/reports/index.blade.php` - Added daily sales card

## Next Steps

1. **Review the region mapping** and update it to match your actual zones
2. **Test the export** with a small date range first
3. **Verify the Excel format** matches your requirements
4. **Add any additional sheets** you need (monthly historic, etc.)
5. **Customize the styling** if needed

## Additional Features to Consider

- [ ] Add region filter to dashboard
- [ ] Add status filter (not just processed orders)
- [ ] Add charts/graphs for visual analytics
- [ ] Add export scheduling (automated daily/weekly reports)
- [ ] Add email delivery of reports
- [ ] Add comparison periods (vs last month, vs last year)
- [ ] Add product/category breakdown sheets
- [ ] Cache KPIs for better performance
