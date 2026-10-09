# WP Plugin Booking

GPL-2.0-or-later WordPress/WooCommerce equipment and service scheduling plugin.

## Status: 0.1.0 — development preview, not production-ready

This initial release provides a real WooCommerce product configuration interface, a WordPress admin overview, and a public booking-form **preview** with variable-length time selections. **It does not create reservations, check availability against existing orders, lock slots, or process booking checkout.** Do not use it for paid bookings yet.

## Install

1. Install and activate WooCommerce.
2. Copy this repository into `wp-content/plugins/wp-plugin-booking/` or ZIP the directory and upload through Plugins → Add New → Upload Plugin.
3. Activate **WP Plugin Booking**.
4. Edit a WooCommerce product. In Product data → General, enable equipment booking, enter hourly rate, opening/closing hours, increment, and open weekdays.
5. Save the product.
6. Create a test page with `[wpb_booking id="123"]`, replacing 123 with the product ID.
7. Visit **Equipment Booking** in the WordPress admin to see configured products.

The plugin creates **no WordPress pages or navigation entries** on activation. Week-start follows WordPress Settings → General. Dates follow the site timezone.

## Planned milestones

- 0.2.0: resource registry, weekly schedules and exceptions, persistent reservations, conflict-safe time-slot locking
- 0.3.0: WooCommerce cart/order integration, temporary holds, lifecycle synchronization, HPOS and Checkout Blocks
- 0.4.0: administrative calendar, maintenance blocks, staff assignment and commissioned services
- 1.0.0: security, concurrency, payment, DST, cancellation and POS integration tests

## Security and development notes

This preview intentionally disables checkout because an unprotected booking workflow could sell overlapping machine time. Test on staging only. Use WooCommerce CRUD APIs for orders, transactional InnoDB tables for occupancy, and server-side validation for every future booking request.

License: GPL-2.0-or-later.
