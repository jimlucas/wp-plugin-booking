<?php
/**
 * Plugin Name: WooCommerce Equipment Booking
 * Description: Hourly, single-machine-per-product reservations integrated with WooCommerce orders.
 * Version: 0.2.1
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: woocommerce-equipment-booking
 */
if (!defined('ABSPATH')) exit;
final class WEB_Equipment_Booking {
 const VERSION='0.2.1';
 const META='_web_bookable';
 const TABLE='web_reserved_slots';
 static function table(){global $wpdb; return $wpdb->prefix.self::TABLE;}
 static function boot(){
  add_action('before_woocommerce_init',function(){if(class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')){\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',__FILE__,true);\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks',__FILE__,false);}});
  add_action('plugins_loaded',[__CLASS__,'init']);
  register_activation_hook(__FILE__,[__CLASS__,'activate']);
 }
 static function activate(){global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$t=self::table();$charset=$wpdb->get_charset_collate();dbDelta("CREATE TABLE $t (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 product_id bigint(20) unsigned NOT NULL,
 slot_start datetime NOT NULL,
 order_id bigint(20) unsigned NOT NULL,
 expires_at datetime DEFAULT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY product_slot (product_id,slot_start),
 KEY order_id (order_id),
 KEY expires_at (expires_at)
 ) $charset;");}
 static function init(){if(!class_exists('WooCommerce'))return;
  add_action('woocommerce_product_options_general_product_data',[__CLASS__,'fields']);
  add_action('woocommerce_admin_process_product_object',[__CLASS__,'save_fields']);
  add_action('woocommerce_before_add_to_cart_button',[__CLASS__,'form']);
  add_filter('woocommerce_add_to_cart_validation',[__CLASS__,'validate_add'],10,5);
  add_filter('woocommerce_add_cart_item_data',[__CLASS__,'cart_data'],10,3);
  add_filter('woocommerce_get_item_data',[__CLASS__,'item_data'],10,2);
  add_action('woocommerce_before_calculate_totals',[__CLASS__,'price_cart']);
  add_action('woocommerce_checkout_create_order_line_item',[__CLASS__,'order_item'],10,4);
  add_action('woocommerce_checkout_order_created',[__CLASS__,'reserve_order']);
  add_action('woocommerce_store_api_checkout_order_processed',[__CLASS__,'reserve_order']);
  foreach(['cancelled','failed','refunded'] as $status) add_action('woocommerce_order_status_'.$status,[__CLASS__,'release_order']);
  add_action('woocommerce_before_cart',[__CLASS__,'validate_cart']);
  add_action('woocommerce_check_cart_items',[__CLASS__,'validate_cart']);
  add_action('woocommerce_checkout_process',[__CLASS__,'validate_cart']);
  add_filter('woocommerce_quantity_input_args',[__CLASS__,'quantity'],10,2);
  add_action('web_equipment_cleanup',[__CLASS__,'cleanup']);
  if(!wp_next_scheduled('web_equipment_cleanup'))wp_schedule_event(time()+300,'hourly','web_equipment_cleanup');
  add_action('wp_enqueue_scripts',[__CLASS__,'scripts']);
  add_action('wp_ajax_web_slots',[__CLASS__,'ajax_slots']);
  add_action('wp_ajax_nopriv_web_slots',[__CLASS__,'ajax_slots']);
  require_once __DIR__.'/includes/class-web-admin.php';
  WEB_Booking_Admin::init();
  add_filter('woocommerce_is_sold_individually',[__CLASS__,'sold_individually'],10,2);
  add_filter('woocommerce_add_to_cart_redirect',[__CLASS__,'cart_redirect']);
 }
 static function active($product_id){return get_post_meta($product_id,self::META,true)==='yes';}
 static function settings($id){
  $days=get_post_meta($id,'_web_days',true);$days=is_array($days)?array_map('intval',$days):[1,2,3,4,5];
  return ['days'=>$days,'open'=>get_post_meta($id,'_web_open',true)?:'10:00','close'=>get_post_meta($id,'_web_close',true)?:'18:00','max'=>max(1,min(24,(int)(get_post_meta($id,'_web_max',true)?:8)))];
 }
 static function fields(){echo '<div class="options_group">';woocommerce_wp_checkbox(['id'=>self::META,'label'=>'Enable hourly equipment booking','description'=>'One product represents one physical machine. Use a simple product with its hourly rate as the regular price.']);
  woocommerce_wp_text_input(['id'=>'_web_open','label'=>'Opening time (HH:MM)','placeholder'=>'10:00','description'=>'24-hour time, whole hours only.','desc_tip'=>true]);
  woocommerce_wp_text_input(['id'=>'_web_close','label'=>'Closing time (HH:MM)','placeholder'=>'18:00','description'=>'24-hour time, whole hours only.','desc_tip'=>true]);
  woocommerce_wp_text_input(['id'=>'_web_max','label'=>'Maximum booking hours','type'=>'number','custom_attributes'=>['min'=>'1','max'=>'24']]);
  $days=get_post_meta(get_the_ID(),'_web_days',true);$days=is_array($days)?$days:[1,2,3,4,5];echo '<p class="form-field"><label>Available weekdays</label><span style="display:inline-flex;flex-wrap:wrap;align-items:center;gap:10px;max-width:calc(100% - 170px)">';foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $n=>$name)printf('<label style="float:none;width:auto;margin:0;display:inline-flex;align-items:center;gap:4px;white-space:nowrap"><input style="float:none;margin:0" type="checkbox" name="_web_days[]" value="%d" %s/> %s</label>',$n,checked(in_array($n,$days),true,false),esc_html($name));echo '</span></p></div>';
 }
 static function save_fields($p){$id=$p->get_id();$p->update_meta_data(self::META,isset($_POST[self::META])?'yes':'no');
  foreach(['_web_open','_web_close'] as $k){$v=isset($_POST[$k])?sanitize_text_field(wp_unslash($_POST[$k])):'';if(preg_match('/^(?:[01][0-9]|2[0-3]):00$/',$v))$p->update_meta_data($k,$v);}
  $p->update_meta_data('_web_max',max(1,min(24,absint($_POST['_web_max']??8))));$p->update_meta_data('_web_days',array_values(array_intersect(range(0,6),array_map('intval',(array)($_POST['_web_days']??[])))));
 }
 static function form(){global $product;if(!$product||!$product->is_type('simple')||!self::active($product->get_id()))return;$s=self::settings($product->get_id());$now=new DateTimeImmutable('now',wp_timezone());$tomorrow=$now->modify('+1 day')->format('Y-m-d');
  echo '<div class="web-booking" data-close="'.esc_attr((int)substr($s['close'],0,2)).'" data-product="'.esc_attr($product->get_id()).'">';echo '<p><label for="web_date">Reservation date</label><br/><input required type="date" id="web_date" name="web_date" min="'.esc_attr($now->format('Y-m-d')).'" value="'.esc_attr($tomorrow).'"/></p>';
  echo '<p><label for="web_start">Starting time</label><br/><select required id="web_start" name="web_start">';$o=(int)substr($s['open'],0,2);$c=(int)substr($s['close'],0,2);for($h=$o;$h<$c;$h++)printf('<option value="%02d:00">%s</option>',$h,esc_html(wp_date('g:i A',strtotime(sprintf('2020-01-01 %02d:00:00',$h)))));echo '</select></p>';
  echo '<p><label for="web_hours">Consecutive hours</label><br/><select required id="web_hours" name="web_hours">';for($i=1;$i<=$s['max'];$i++)printf('<option value="%d">%d hour%s</option>',$i,$i,$i===1?'':'s');echo '</select></p><div id="web-availability" role="status" aria-live="polite">Checking availability…</div><p class="web-hint">Availability is checked when added to cart and again at checkout. Bookings are confirmed only when checkout succeeds.</p></div>';
 }
 static function parse($id,$date,$start,$hours){$s=self::settings($id);if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!preg_match('/^(?:[01]\d|2[0-3]):00$/',$start)||$hours<1||$hours>$s['max'])return new WP_Error('invalid','Invalid booking date, time, or duration.');
  $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$start,wp_timezone());if(!$dt||$dt->format('Y-m-d H:i')!==$date.' '.$start)return new WP_Error('date','Invalid or nonexistent local date/time.');
  $end=$dt->modify('+'.$hours.' hours');if($dt->format('Y-m-d')!==$end->format('Y-m-d'))return new WP_Error('hours','Booking must end on the same day.');
  if(!in_array((int)$dt->format('w'),$s['days'],true)||$start<$s['open']||$end->format('H:i')>$s['close'])return new WP_Error('closed','Machine is not available during those hours.');
  if(self::closed_date($id,$date))return new WP_Error('closed_date','This machine is closed on the selected date.');
  if($dt<=new DateTimeImmutable('now',wp_timezone()))return new WP_Error('past','Choose a future reservation time.');
  $slots=[];for($i=0;$i<$hours;$i++)$slots[]=$dt->modify('+'.$i.' hours')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');return $slots;
 }
 static function closed_date($id,$date){$dates=get_post_meta($id,'_web_closed_dates',true);return is_array($dates)&&in_array($date,$dates,true);}
 static function available($id,$slots,$exclude_order=0){global $wpdb;self::cleanup();$table=self::table();if(!$slots)return false;$marks=implode(',',array_fill(0,count($slots),'%s'));$args=array_merge([$id],$slots);$query=$wpdb->prepare("SELECT COUNT(*) FROM $table WHERE product_id=%d AND slot_start IN ($marks)".($exclude_order?' AND order_id!='.absint($exclude_order):''),$args);return (int)$wpdb->get_var($query)===0;}
 static function validate_add($ok,$id,$qty=1,$variation_id=0,$variations=[]){if(!self::active($id))return $ok;$date=sanitize_text_field(wp_unslash($_POST['web_date']??''));$start=sanitize_text_field(wp_unslash($_POST['web_start']??''));$hours=absint($_POST['web_hours']??0);$slots=self::parse($id,$date,$start,$hours);if(is_wp_error($slots)){wc_add_notice($slots->get_error_message(),'error');return false;}if(!self::available($id,$slots)){wc_add_notice('Those hours are already reserved. Please select another time.','error');return false;}return $ok;}
 static function cart_data($data,$id,$variation){if(!self::active($id))return $data;$data['web_booking']=['date'=>sanitize_text_field(wp_unslash($_POST['web_date']??'')),'start'=>sanitize_text_field(wp_unslash($_POST['web_start']??'')),'hours'=>absint($_POST['web_hours']??0)];return $data;}
 static function item_data($data,$item){if(empty($item['web_booking']))return $data;$b=$item['web_booking'];foreach(['date'=>'Date','start'=>'Start','hours'=>'Hours'] as $key=>$label)$data[]=['key'=>$label,'value'=>esc_html((string)$b[$key])];return $data;}
 static function price_cart($cart){if(is_admin()&&!wp_doing_ajax())return;foreach($cart->get_cart() as $item){if(empty($item['web_booking']))continue;$p=$item['data'];$base=$p->get_regular_price();if($base==='')$base=$p->get_price();$p->set_price((float)$base*(int)$item['web_booking']['hours']);}}
 static function order_item($item,$key,$values,$order){if(empty($values['web_booking']))return;$b=$values['web_booking'];$item->add_meta_data('_web_date',$b['date'],true);$item->add_meta_data('_web_start',$b['start'],true);$item->add_meta_data('_web_hours',$b['hours'],true);$item->add_meta_data('Reservation',$b['date'].' '.$b['start'].' ('.$b['hours'].' hours)',true);}
 static function reserve_order($order){if(!$order instanceof WC_Order)return;if($order->get_meta('_web_reserved')==='yes')return;global $wpdb;$table=self::table();$id=$order->get_id();$inserted=false;
  // InnoDB unique(product_id,slot_start) ensures two concurrent orders cannot own one machine-hour.
  $wpdb->query('START TRANSACTION');foreach($order->get_items('line_item') as $item){$date=$item->get_meta('_web_date');if(!$date)continue;$pid=$item->get_product_id();$slots=self::parse($pid,$date,$item->get_meta('_web_start'),(int)$item->get_meta('_web_hours'));if(is_wp_error($slots)){ $wpdb->query('ROLLBACK');throw new Exception('Reservation unavailable: '.$slots->get_error_message());}
   foreach($slots as $slot){$ok=$wpdb->insert($table,['product_id'=>$pid,'slot_start'=>$slot,'order_id'=>$id,'expires_at'=>gmdate('Y-m-d H:i:s',time()+30*MINUTE_IN_SECONDS)],['%d','%s','%d','%s']);if(!$ok){$wpdb->query('ROLLBACK');throw new Exception('One or more reserved hours were just taken. Please choose another time.');}$inserted=true;}
  }
  $wpdb->query('COMMIT');if($inserted){$order->update_meta_data('_web_reserved','yes');$order->save();}
 }
 static function release_order($order_id){global $wpdb;$wpdb->delete(self::table(),['order_id'=>(int)$order_id],['%d']);$order=wc_get_order($order_id);if($order){$order->delete_meta_data('_web_reserved');$order->save();}}
 static function cleanup(){global $wpdb;$table=self::table();$ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT order_id FROM $table WHERE expires_at IS NOT NULL AND expires_at < %s",gmdate('Y-m-d H:i:s')));foreach($ids as $id){$order=wc_get_order((int)$id);if(!$order||in_array($order->get_status(),['pending','failed','cancelled'],true))self::release_order((int)$id);else $wpdb->update($table,['expires_at'=>null],['order_id'=>(int)$id],['%s'],['%d']);}}
 static function validate_cart(){if(!WC()->cart)return;foreach(WC()->cart->get_cart() as $item){if(empty($item['web_booking']))continue;$b=$item['web_booking'];$slots=self::parse($item['product_id'],$b['date'],$b['start'],(int)$b['hours']);if(is_wp_error($slots)||!self::available($item['product_id'],is_wp_error($slots)?[]:$slots))wc_add_notice('A machine reservation in your cart is no longer available. Please remove it and choose a new time.','error');}}
 static function quantity($args,$product){if(self::active($product->get_id())){$args['min_value']=1;$args['max_value']=1;$args['input_value']=1;}return $args;}
 static function sold_individually($sold,$product){return self::active($product->get_id())?true:$sold;}
 static function cart_redirect($url){return $url;}
 static function ajax_slots(){
  check_ajax_referer('web_slots','nonce');
  $id=absint($_GET['product_id']??0);$date=sanitize_text_field(wp_unslash($_GET['date']??''));
  if(!$id||!self::active($id)||get_post_status($id)!=='publish'){wp_send_json_error(['message'=>'Invalid product.'],400);}
  $settings=self::settings($id);$o=(int)substr($settings['open'],0,2);$c=(int)substr($settings['close'],0,2);
  $result=[];
  for($h=$o;$h<$c;$h++){
   $time=sprintf('%02d:00',$h);$slots=self::parse($id,$date,$time,1);
   $result[]=['time'=>$time,'available'=>!is_wp_error($slots)&&self::available($id,$slots)];
  }
  wp_send_json_success(['slots'=>$result]);
 }
 static function scripts(){
  if(!is_product())return;
  wp_enqueue_script('web-booking',plugins_url('assets/booking.js',__FILE__),['jquery'],self::VERSION,true);
  wp_localize_script('web-booking','webBooking',['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('web_slots')]);
  wp_enqueue_style('web-booking',plugins_url('assets/booking.css',__FILE__),[],self::VERSION);
 }

}
WEB_Equipment_Booking::boot();
