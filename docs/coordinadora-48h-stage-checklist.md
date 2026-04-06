# Coordinadora 48H Stage Checklist

## Pre-flight

- Run migrations in stage.
- Confirm each target zone has `zip_code` and `fulfillment_provider_48h`.
- Confirm 48H shipping method (`express`) is enabled.
- Verify Coordinadora credentials and FV mock token are present in stage environment.

## Checkout Quote Validation

- Open cart with a user whose zone provider is `coordinadora` and has ZIP.
- Select `express` method and validate shipping quote appears.
- Change zone and validate quote refreshes.
- Select non-`express` method and validate shipping quote resets to `0`.

## Order Processing Validation

- Place an `express` order on a Coordinadora zone.
- Verify order stores:
  - `shipping_provider = coordinadora`
  - `shipping_quote_amount > 0` (for quoted cases)
  - `fv_number`
  - `coordinadora_guide_number`
  - `coordinadora_status_*`
- Verify order status moves to `Procesado`.

## XML / Tronex Regression

- Place a Tronex order and verify legacy XML flow still works.
- For XML orders with shipping amount, verify line item `FL0001` is present.
- Validate no behavior changes for coupon/discount XML mapping.

## Admin + Mi Cuenta Visibility

- In admin order list/detail, verify provider, FV and guía fields render correctly.
- In `mi-cuenta` order list/detail, verify Coordinadora status block and guide number are visible.
- Verify zone ZIP/provider can be updated from admin user edit view.

## Rollback Triggers

- Repeated Coordinadora auth failures.
- Guide creation failing consistently for valid addresses.
- Missing FV reference on processed Coordinadora orders.
- Unexpected regressions in Tronex XML processing.
