<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
wp_clear_scheduled_hook('web_equipment_cleanup');
// Preserve reservations and product configuration on uninstall to prevent accidental data loss.
