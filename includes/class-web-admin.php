<?php
if (!defined('ABSPATH')) exit;
final class WEB_Booking_Admin {
 static function init(){
  add_action('admin_menu',[__CLASS__,'menu']);
  add_action('admin_post_web_save_closures',[__CLASS__,'save_closures']);
  add_action('admin_post_web_cancel_booking',[__CLASS__,'cancel_booking']);
 }
 static function menu(){
  add_menu_page('Equipment Bookings','Equipment Bookings','manage_woocommerce','web-bookings',[__CLASS__,'reservations'],'dashicons-calendar-alt',56);
  add_submenu_page('web-bookings','Reservations','Reservations','manage_woocommerce','web-bookings',[__CLASS__,'reservations']);
  add_submenu_page('web-bookings','Machines & Closures','Machines & Closures','manage_woocommerce','web-machines',[__CLASS__,'machines']);
 }
 static function notice(){
  if(empty($_GET['web_notice']))return;
  $messages=['saved'=>'Machine closures saved.','cancelled'=>'Reservation order cancelled; occupied slots released.','error'=>'Unable to complete the action.'];
  $key=sanitize_key(wp_unslash($_GET['web_notice']));
  if(isset($messages[$key]))echo '<div class="notice notice-'.($key==='error'?'error':'success').' is-dismissible"><p>'.esc_html($messages[$key]).'</p></div>';
 }
 static function reservations(){
  if(!current_user_can('manage_woocommerce'))wp_die('Permission denied');
  global $wpdb;$table=WEB_Equipment_Booking::table();
  $rows=$wpdb->get_results("SELECT product_id, order_id, MIN(slot_start) AS starts_utc, MAX(slot_start) AS last_slot_utc, COUNT(*) AS hours, MIN(expires_at) AS expires_at FROM $table GROUP BY product_id, order_id ORDER BY starts_utc DESC LIMIT 200");
  echo '<div class="wrap"><h1>Equipment Reservations</h1>';self::notice();
  echo '<p>Current occupied reservations and pending checkout holds (up to 200 groups). Times are displayed in the WordPress site timezone. Cancel an order using WooCommerce to release its booking.</p>';
  echo '<table class="widefat striped"><thead><tr><th>Machine / product</th><th>Start</th><th>End</th><th>Hours</th><th>Order</th><th>Status</th><th>Hold expires (UTC)</th></tr></thead><tbody>';
  foreach($rows as $r){$order=wc_get_order((int)$r->order_id);$start=new DateTimeImmutable($r->starts_utc,new DateTimeZone('UTC'));$end=(new DateTimeImmutable($r->last_slot_utc,new DateTimeZone('UTC')))->modify('+1 hour');$link=get_edit_post_link((int)$r->product_id);$order_link=$order?$order->get_edit_order_url():'';
   echo '<tr><td><a href="'.esc_url($link?:'#').'">'.esc_html(get_the_title($r->product_id)).'</a></td><td>'.esc_html($start->setTimezone(wp_timezone())->format('Y-m-d g:i A')).'</td><td>'.esc_html($end->setTimezone(wp_timezone())->format('Y-m-d g:i A')).'</td><td>'.esc_html($r->hours).'</td><td>'.($order_link?'<a href="'.esc_url($order_link).'">#'.absint($r->order_id).'</a>':'#'.absint($r->order_id)).'</td><td>'.esc_html($order?$order->get_status():'Order missing').'</td><td>'.esc_html($r->expires_at?:'—').'</td></tr>';
  }
  if(!$rows)echo '<tr><td colspan="7">No active reservations.</td></tr>';
  echo '</tbody></table></div>';
 }
 static function machines(){
  if(!current_user_can('manage_woocommerce'))wp_die('Permission denied');
  $products=get_posts(['post_type'=>'product','post_status'=>['publish','draft','private'],'posts_per_page'=>200,'meta_key'=>WEB_Equipment_Booking::META,'meta_value'=>'yes','orderby'=>'title','order'=>'ASC']);
  echo '<div class="wrap"><h1>Machines & Date Closures</h1>';self::notice();
  echo '<p>Each bookable WooCommerce product represents one machine. Configure weekly hours and hourly price on the <strong>product edit screen</strong>. Enter full-day closure dates below (one YYYY-MM-DD per line). Existing reservations are not automatically cancelled by a closure.</p>';
  if(count($products)===200)echo '<div class="notice notice-warning"><p>Showing the first 200 machines.</p></div>';
  foreach($products as $p){$s=WEB_Equipment_Booking::settings($p->ID);$dates=get_post_meta($p->ID,'_web_closed_dates',true);$dates=is_array($dates)?$dates:[];
   echo '<div class="postbox" style="padding:16px;max-width:850px"><h2>'.esc_html($p->post_title).' (#'.absint($p->ID).')</h2>';
   echo '<p>Hours: '.esc_html($s['open'].'–'.$s['close']).' · Weekdays: '.esc_html(implode(', ',array_map(function($n){return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$n];},$s['days']))).' · Max: '.absint($s['max']).' hours · <a href="'.esc_url(get_edit_post_link($p->ID)).'">Edit product</a></p>';
   echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="web_save_closures"><input type="hidden" name="product_id" value="'.absint($p->ID).'">';wp_nonce_field('web_closures_'.$p->ID);
   echo '<p><label for="closures-'.absint($p->ID).'">Closed dates (one per line)</label></p><textarea id="closures-'.absint($p->ID).'" name="closed_dates" rows="4" cols="36">'.esc_textarea(implode("\n",$dates)).'</textarea><p><button class="button button-primary" type="submit">Save closures</button></p></form></div>';
  }
  if(!$products)echo '<p>No bookable products found. Edit a simple WooCommerce product and enable hourly equipment booking.</p>';
  echo '</div>';
 }
 static function save_closures(){
  if(!current_user_can('manage_woocommerce'))wp_die('Permission denied');$id=absint($_POST['product_id']??0);check_admin_referer('web_closures_'.$id);
  if(!$id||get_post_type($id)!=='product'||!WEB_Equipment_Booking::active($id))wp_die('Invalid product');
  $raw=sanitize_textarea_field(wp_unslash($_POST['closed_dates']??''));$dates=[];
  foreach(preg_split('/\r\n|\n|\r/',$raw) as $v){$v=trim($v);if($v==='')continue;$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$dt||$dt->format('Y-m-d')!==$v)wp_die('Invalid date: '.esc_html($v));$dates[]=$v;}
  update_post_meta($id,'_web_closed_dates',array_values(array_unique($dates)));
  wp_safe_redirect(add_query_arg(['page'=>'web-machines','web_notice'=>'saved'],admin_url('admin.php')));exit;
 }
 static function cancel_booking(){wp_die('Manage cancellation through the WooCommerce order screen.');}
}
