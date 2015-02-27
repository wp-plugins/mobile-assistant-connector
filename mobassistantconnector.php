<?php
/*
Plugin Name: Mobile Assistant Connector
Plugin URI: http://woocommerce-manager.com
Description:  This plugin allows you to keep your online business under control wherever you are. All you need is just to have on hand your android mobile phone and Internet connection.
Author: eMagicOne
Author URI: http://woocommerce-manager.com
Version: 1.0.5
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/*-----------------------------------------------------------------------------+
| eMagicOne                                                                    |
| Copyright (c) 2015 eMagicOne.com <contact@emagicone.com>		               |
| All rights reserved                                                          |
+------------------------------------------------------------------------------+
|                                                                              |
| Mobile Assistant Connector					                               |
|                                                                              |
| Developed by eMagicOne,                                                      |
| Copyright (c) 2015                                            	           |
+-----------------------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 */

if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins'))))
{
    if (!class_exists('ma_connector'))
    {
		register_activation_hook( __FILE__, array( 'ma_connector','mobileassistantconnector_activation' ));
		register_deactivation_hook( __FILE__, array( 'ma_connector','mobileassistantconnector_deactivate' ));
		register_uninstall_hook(__FILE__, array( 'ma_connector','mobileassistantconnector_uninstall'));
		
		define('PUSH_TYPE_NEW_ORDER', 'new_order');
		define('PUSH_TYPE_CHANGE_ORDER_STATUS', 'order_changed');
		define('PUSH_TYPE_NEW_CUSTOMER', 'new_customer');
		define('MOBASSIST_DEBUG_MODE', false);			

		class ma_connector
        {	

			public static function mobileassistantconnector_activation()
			{
				global $wpdb;
				if ( ! current_user_can( 'activate_plugins' ) )
					return;
				$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
				check_admin_referer( "activate-plugin_{$plugin}" );

				$wpdb->query("
                  CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}mobileassistant_push_settings` (
                      `setting_id` int(11) NOT NULL AUTO_INCREMENT,
                      `registration_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                      `app_connection_id` int(5) NOT NULL,
                      `push_new_order` tinyint(1) NOT NULL DEFAULT '0',
                      `push_order_statuses` text COLLATE utf8_unicode_ci NOT NULL,
                      `push_new_customer` tinyint(1) NOT NULL DEFAULT '0',
                      `push_currency_code` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
                      PRIMARY KEY (`setting_id`))
				");

                if (!(get_option('mobassistantconnector')))
                {
                    $option_value = array(
                        'login'     => '1',
                        'pass'      => 'c4ca4238a0b923820dcc509a6f75849b'
                    );
                    $wpdb->replace($wpdb->options, array('option_name' => 'mobassistantconnector', 'option_value' => serialize($option_value)));
                }
			}

			public static function mobileassistantconnector_deactivate()
			{
				if ( ! current_user_can( 'activate_plugins' ) )
					return;
				$plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
				check_admin_referer( "deactivate-plugin_{$plugin}" );

				remove_filter('query_vars', 'mobileassistantconnector_add_query_vars');
				remove_action('template_redirect', 'mobileassistantconnector_check_vars');
				remove_action('woocommerce_checkout_update_order_meta', 'mobassist_push_new_order');
				
				//exit( var_dump( $_GET ) );
			}

			public static function mobileassistantconnector_uninstall()
			{
				if ( ! current_user_can( 'activate_plugins' ) )
					return;
				check_admin_referer( 'bulk-plugins' );

				// Important: Check if the file is the one
				// that was registered during the uninstall hook.
				if ( __FILE__ != WP_UNINSTALL_PLUGIN )
					return;

				# Uncomment the following line to see the function in action
				 //exit( var_dump( $_GET ) );
			}
			
		
			public function __construct()
            {
				
			if ( isset($_GET['page']) && $_GET['page'] == 'connector') {	
				add_action('admin_enqueue_scripts', array( &$this,'ema_option_styles'));
				add_action('admin_enqueue_scripts', array( &$this,'ema_option_scripts'));
			}
				add_filter('query_vars', array( &$this,'add_query_vars'));
				add_action('template_redirect', array( &$this,'the_template'));
				
				add_action('woocommerce_checkout_update_order_meta', 'mobassist_push_new_order');
                add_action('woocommerce_order_status_changed', 'mobassist_push_change_status');
                add_action('woocommerce_created_customer', 'mobassist_push_new_customer');				
							
				$plugin = plugin_basename(__FILE__);
				add_filter("plugin_action_links_$plugin", array( &$this,'setting_link'));

			}
			
		
			
			public function add_query_vars($vars)
            {
				$vars[] = "callback";
				$vars[] = "call_function";
				$vars[] = "hash";
				$vars[] = "test_config";
				$vars[] = "get_store_title";
				$vars[] = "get_store_stats";
				$vars[] = "get_data_graphs";
				$vars[] = 'show';
				$vars[] = 'page';
				$vars[] = 'search_order_id';
				$vars[] = 'orders_from';
				$vars[] = 'orders_to';
				$vars[] = 'customers_from';
				$vars[] = 'customers_to';
				$vars[] = 'date_from';
				$vars[] = 'date_to';
				$vars[] = 'graph_from';
				$vars[] = 'graph_to';
				$vars[] = 'stats_from';
				$vars[] = 'stats_to';
				$vars[] = 'products_to';
				$vars[] = 'products_from';
				$vars[] = 'order_id';
				$vars[] = 'user_id';
				$vars[] = 'params';
				$vars[] = 'val';
				$vars[] = 'search_val';
				$vars[] = 'statuses';
				$vars[] = 'last_order_id';
				$vars[] = 'sort_by';
				$vars[] = 'product_id';
				$vars[] = 'get_statuses';
				$vars[] = 'cust_with_orders';
				$vars[] = 'data_for_widget';
				$vars[] = 'custom_period';
				$vars[] = 'connector';
				
				return $vars;
			}

			public function the_template($template)
            {
				global $wp_query;
				
				if (!isset( $wp_query->query['connector']))
					return $template;

				if ($wp_query->query['connector'] == 'mobileassistant')
                {
					$this->execute_connector();
					exit;
				}

				return $template;
			}
						
			public function execute_connector()
            {
				$MainClass = new MobileAssistantConnector();
				$call_func = $MainClass->call_function;

				if (!method_exists($MainClass, $call_func))
                {
					$MainClass->generate_output('old_module');
				}

				$result = $MainClass->$call_func();
				$MainClass->generate_output($result);
			}

				
		
			public function ema_option_styles() {
				wp_register_style('ema_style', plugins_url('css/style.css', __FILE__));
				wp_enqueue_style('ema_style');	
			}

			public function ema_option_scripts() {
				wp_register_script('ema_tb', plugins_url('js/tb.js', __FILE__));
				wp_enqueue_script('ema_tb');
    
				wp_register_script('ema_qr', plugins_url('js/qrcode.min.js', __FILE__));
				wp_enqueue_script('ema_qr');

                wp_register_script('ema_qr_changed', plugins_url('js/qr_changed.js', __FILE__));
                wp_enqueue_script('ema_qr_changed');
			}

			// Add settings link on plugin page
			public function setting_link($links) {
				$settings_link = '<a href="options-general.php?page=connector">Settings</a>';
				array_unshift($links, $settings_link);
				return $links;
			}
			
		}

        $GLOBALS['ma_connector'] = new ma_connector();
        include_once('option.php');
        include_once('sa.php');
	}
} else {
	add_action ('admin_notices', 'connector_admin_notices');
}

function connector_admin_notices()
{
    echo '<div id="notice" class="error"><p>';
    echo '<b> Mobile Assistant Connector </b> add-on requires <a href="http://www.storeapps.org/woocommerce/"> WooCommerce </a> plugin. Please install and activate it.';
    echo '</p></div>', "\n";
				
}

?>