=== WooCommerce Equipment Booking ===
Contributors: community
Tags: woocommerce, booking, equipment, hourly
Requires at least: 6.4
Requires PHP: 7.4
License: GPLv2 or later
Stable tag: 0.1.0

Single physical machine per simple WooCommerce product. Configure an hourly rate as the regular product price, enable booking in Product data > General, and set weekdays, opening/closing times, and maximum duration. Customer chooses a date, hourly start and consecutive hours on the product page. The cart displays booking details and multiplies the hourly rate by duration.

IMPORTANT v0.1.0 LIMITATIONS:
- Classic WooCommerce product/cart/checkout only; Cart/Checkout Blocks are not supported.
- Order slots are allocated during checkout order creation, not on add-to-cart. A slot is not held while browsing/carting.
- Pending order slots expire after 30 minutes (hourly WP-Cron cleanup); successful payment should update the order status.
- This is an initial test build, not production-ready. Test gateway callbacks, concurrency, refunds, cancellations, timezone/DST, admin edits, stock behavior, and POS interoperability on staging.
- Booking weekdays/hours apply to each product; site-wide holidays, staff, and admin calendar are future features.
- Dates/times follow WordPress site timezone; slots are stored as UTC.
- Never activate alongside another booking plugin on the same test products.
