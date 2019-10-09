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
// add_action('woocommerce_order_status_pending_to_processing', 'order_status_change', 10, 2);
add_action('woocommerce_order_status_pending', 'order_status_change');
add_action('woocommerce_order_status_processing', 'order_status_change');
add_action('woocommerce_order_status_on_hold', 'order_status_change');
add_action('woocommerce_order_status_cancelled', 'order_status_change');
add_action('woocommerce_order_status_completed', 'order_status_change');
add_action('woocommerce_order_status_refunded', 'order_status_change');
add_action('woocommerce_order_status_failed', 'order_status_change');

add_action('woocommerce_order_edit_status', 'order_edit_status', 10, 2);

add_action('woocommerce_process_shop_order_meta', 'order_meta', 10, 2);
add_action('admin_menu', 'create_menu');

// FOR DEV ON THE CRON CALLBACK
add_action('woocommerce_after_register_post_type', 'jeff_cron_callback');

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'add_plugin_page_settings_link');

function add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'admin.php?page=jeff-bridger%2Fjeff-bridger.php' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

function order_edit_status($order_id, $to) {
  wpLog(['$order_id'=>$order_id, '$to'=>$to]);
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
  $uri = $base_jeff_url . $order_id;

  wpLog(['$uri'=>$uri]);

  // $order = new WC_order($order_id);
  // wpLog(['$order'=>$order->get_data()]);

  $res = wp_remote_get($uri);
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
    // wpLog(['wp_get_scheduled_event'=>'jeff_cron_event not found. Scheduling now!']);
    $cron_array = wp_schedule_event( time(), 'hourly', 'jeff_cron_event');
  }

  // $scheduled_jobs = _get_cron_array();
  // wpLog(['$scheduled_jobs'=>$scheduled_jobs]);

  add_action('jeff_cron_event', 'jeff_cron_callback');
}

function jeff_cron_callback() {
  $args = [
    'status' => 'any',
    'limit'  => -1
  ];
  //Get woo orders
  $woo_orders = wc_get_orders($args);
  $woo_order_data_arr = array_map(function ($order){
    return $order->get_data();
  }, $woo_orders);
  // wpLog(['$woo_order_data_arr'=>$woo_order_data_arr]);
  //Get airtable orders

  //Compare orders

  //If there are discrepancies, sync
  foreach($woo_order_data_arr as $order) {
    $id = $order['id'];
    // $res = jeff_sync($id);
    wpLog(['$id'=>$id]);
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
