# WooCommerce Equipment Booking 0.2.0

Development build. Single physical machine per WooCommerce simple product.

## Added in 0.2.0
- Equipment Bookings > Reservations: active reservation/hold overview, links to orders and products.
- Equipment Bookings > Machines & Closures: machine listing, full-day closure dates per product.
- Customer product page: AJAX hourly availability feedback, disabled unavailable start times and invalid consecutive durations.
- Reservation validation respects configured closure dates.

## Known limitations
- Development/testing only; do not use for real customer payments yet.
- No interactive month-grid calendar yet; date picker is browser-native.
- Admin reservations are read-only; cancel via WooCommerce order.
- Does not implement Checkout Blocks (classic checkout required).
- Hourly boundaries only; opening/closing hours same day; no per-weekday different hours.
- AJAX availability is advisory; server-side revalidation and unique slot constraint are authoritative.
- 0.1.0 reservation handling needs production hardening, particularly checkout holds, cart race conditions, and DST transitions.
- No integration test was run against WordPress/WooCommerce/WPOS.
- Week start follows WordPress Settings > General, but the browser-native date input uses OS/browser presentation.

## Docker deployment
Prefer updating only the plugin files in the bind-mounted or named-volume wp-content/plugins directory. Never replace wp-config.php, database, uploads, themes, or unrelated plugins. See response for commands.
