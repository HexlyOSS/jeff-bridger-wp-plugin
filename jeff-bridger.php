<?php
/**
 * Plugin Name: jeff-bridger
 * Plugin URI: https://jeff-bridger.io/
 * Description: The Bridge Abides
 * Version: 0.1
 * Author: Hexly LLC
 * Author URI: https://hexly.io
 * Text Domain: woocommerce
 * Domain Path: /
 *
 * @package jeff-bridger
 */

// defined( 'ABSPATH' ) || exit;

// if ( ! defined( 'WC_PLUGIN_FILE' ) ) {
// 	define( 'WC_PLUGIN_FILE', __FILE__ );
// }

/**
 * Load core packages and the autoloader.
 *
 * The new packages and autoloader require PHP 5.6+. If this dependency is not met, do not include them. Users will be warned
 * that they are using an older version of PHP. WooCommerce will continue to load, but some functionality such as the REST API
 * and Blocks will be missing.
 *
 * This requirement will be enforced in future versions of WooCommerce.
 */



register_activation_hook( __FILE__, 'onActivate');
register_deactivation_hook( __FILE__, 'onDeactivate' );

add_action('admin_menu', 'create_menu');
add_filter( 'cron_schedules', 'add_cron_interval');
add_action('plugins_loaded', 'jeff_cron_init');

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'add_plugin_page_settings_link');

function add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=jeff-bridger%2Fjeff-bridger.php' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

function onActivate() {
  wpLog('jeff-bridger standing by...');
}

function onDeactivate() {
  wpLog('jeff-bridger deactivated...');
}

function order_status_change($order_id) {
  $res = jeff_sync($order_id);
}

function jeff_sync($order_id) {
  $base_jeff_url = get_option('jb_base_url');
  $uri = $base_jeff_url . '/syncOrders/' . $order_id;

  wpLog(['$uri'=>$uri]);

  $res = wp_remote_get($uri);
  wpLog(['$res'=>$res]);
  return $res;
}

function order_meta($order_id, $meta_data) {
  $res = jeff_sync($order_id);
}

function wpLog($args) {
  error_log(print_r($args, true));
}

function jeff_cron_init() {
  $cron_job_exists = wp_next_scheduled( 'jeff_cron_event');
  // wpLog(['$cron_job_exists'=>$cron_job_exists]);
  if(!$cron_job_exists) {
    wpLog('Cron doesn\'t exist');
    // $schedules = wp_get_schedules();
    // wpLog(['wp_get_scheduled_event'=>'jeff_cron_event not found. Scheduling now!']);
    // wpLog(['$schedules'=>$schedules]);
    $cron_array = wp_schedule_event( time(), 'sixty_seconds', 'jeff_cron_event');
  }
  add_action('jeff_cron_event', 'jeff_cron_callback');

  $scheduled_jobs = _get_cron_array();
  // wpLog(['$scheduled_jobs'=>$scheduled_jobs]);
}

function add_cron_interval( $schedules ) {
    $schedules['sixty_seconds'] = array(
        'interval' => 60,
        'display'  => esc_html__( 'Every Minute' ),
    );
 
    return $schedules;
}

function jeff_cron_callback() {
  $args = [
    'status' => 'any',
    'limit'  => -1
  ];
  //Get woo orders
  $woo_orders = wc_get_orders($args);
  $woo_orders = array_map(function ($order){
    return $order->get_data();
  }, $woo_orders);
  $woo_ids_to_index = array_map(function($order) {
    return $order['id'];
  }, $woo_orders);
  $woo_ids_to_index = array_flip($woo_ids_to_index);
  // wpLog(['$woo_ids_to_index'=>$woo_ids_to_index]);
  
  //Get airtable orders
  $base_jeff_url = get_option('jb_base_url');
  $airtable_orders = wp_remote_retrieve_body(wp_remote_get($base_jeff_url . '/getAirtableOrders'));
  $airtable_orders = json_decode($airtable_orders, true);
  $airtable_orders = array_map(function ($order){
    return $order['fields'];
  }, $airtable_orders);
  $airtable_ids_to_index = array_map(function($order) {
    $woo_order_id = $order['wooOrderId'];
    return $woo_order_id ? $woo_order_id : -1;
  }, $airtable_orders);
  // wpLog(['$airtable_ids_to_index before'=>$airtable_ids_to_index]);
  $airtable_ids_to_index = array_flip($airtable_ids_to_index);
  // wpLog(['$airtable_ids_to_index after'=>$airtable_ids_to_index]);

  //Compare orders
  //If there are discrepancies, sync
  $discrepencies = [];
  foreach($woo_orders as $order) {
    $id = $order['id'];
    $status = $order['status'];

    if($status !== $airtable_orders[$airtable_ids_to_index[$id]]['status']) {
      // wpLog([
      //   $id=>'Status mismatch!',
      //   'refunds'=>$refunds,
      //   '$order'=>$order,
      //   '$id'=>$id,
      //   '$status'=>$status,
      //   '$airtable_ids_to_index[$id]'=>$airtable_ids_to_index[$id]
      // ]);
      $discrepencies[] = $id;
    }
  }
  // wpLog(['$discrepencies'=>$discrepencies]);
  
  //Limit it to 25 at a time
  $index = 0;
  while($index < 25) {
    if($discrepencies[$index]) {
      $res = jeff_sync($discrepencies[$index]);
      $res = wp_remote_retrieve_body($res);
      // wpLog(['$res'=>$res]);
      $index++;
    } else {
      $index = 25;
    }
  }
}

function create_menu(){
  add_menu_page('Jeff Bridger Settings', 'Jeff Bridger Settings', 'administrator', __FILE__, 'settings_page');

	add_action( 'admin_init', 'register_settings' );
}

function settings_page() {
  ?>
  <div class="wrap">
    <h1>Jeff Bridger</h1>

    <form method="post" action="options.php">
      <?php settings_fields( 'jeff-bridger-settings-group' ); ?>
      <?php do_settings_sections( 'jeff-bridger-settings-group' ); ?>

      <table class="form-table">
        <tr valign="top">
        <th scope="row">Base URL</th>
        <td><input type="text" name="jb_base_url" value="<?php echo esc_attr( get_option('jb_base_url') ); ?>" /></td>
        </tr>
      </table>

      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}

function register_settings() {
	register_setting( 'jeff-bridger-settings-group', 'jb_base_url' );
}
