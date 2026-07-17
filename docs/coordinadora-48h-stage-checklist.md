# Coordinadora 48H Stage Checklist

## Pre-flight

- Run migrations in stage (includes `zones.dane_code`).
- Confirm each target zone resolves a destination DANE code (`fulfillment_provider_48h` + one of: explicit `dane_code`, DANE stored in `zip_code`, or the owner user's city). The admin user-edit view shows the resolved code as the field placeholder.
- Confirm 48H shipping method (`express`) is enabled.
- Verify Coordinadora credentials and FV mock token are present in stage environment.
- Verify the Coordinadora environment variables:
  - `COORDINADORA_BASE_URL` / `COORDINADORA_OAUTH_URL` (defaults point to `api-test.coordinadora.tech`; set production URLs when going live).
  - `COORDINADORA_KEY` / `COORDINADORA_SECRET` (Basic Auth credentials for `/oauth/token`).
  - `COORDINADORA_NIT` (falls back to `COORDINADORA_TRACKING_NIT`), `COORDINADORA_DIV`, `COORDINADORA_CUENTA`, `COORDINADORA_PRODUCTO`.
  - `COORDINADORA_ORIGIN_DANE` (8-digit DANE of the dispatch city, e.g. `05001000` for Medellín) plus `COORDINADORA_ORIGIN_NAME`, `COORDINADORA_ORIGIN_ADDRESS`, `COORDINADORA_ORIGIN_PHONE` for guide creation.
  - `COORDINADORA_ID_PROCESO`, `COORDINADORA_USUARIO`, `COORDINADORA_GUIDES_PATH`.

## DANE Destination Sourcing

- The cotizador (`POST /cotizador/nacional`) locates Colombian shipments by DANE codes: `origen` comes from `COORDINADORA_ORIGIN_DANE` and `destino` from the zone. Postal-code fields are sent empty (they are Mexico-only per the vendor docs).
- Zone resolution order (see `Zone::coordinadoraDaneCode()`):
  1. `zones.dane_code` (accepts 5-digit divipola `11001` or 8-digit `11001000`; stored normalized).
  2. A DANE-looking value in the legacy `zones.zip_code` field (6-digit real postal codes are ignored).
  3. The owner user's numeric `city_code` synced from Dynamics.
  4. Catalog lookup by the user's city/state names against `storage/states.csv` (official divipola listing).
- If no DANE code resolves, the quote endpoint returns 422 and checkout falls back gracefully; no request is sent to Coordinadora.

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
