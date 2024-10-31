<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.
 *
 *
 * @wordpress-plugin
 * Plugin Name:       NetDebit WooCommerce Gateway
 * Description:       Enables payments via NetDebit
 * Version:           0.1.0
 * Author:            Rene Scholl
 * Author URI:        https://netdebit-payment.de/
 * License:           propertary
 * Text Domain        netdebit-woocommerce-gateway
 */

define( 'WC_PREFIX', 'woocommerce_' );
define( 'PLUGIN_ID', 'netdebit' );
define( 'TEXT_DOMAIN', 'netdebit-woocommerce-gateway' );
define( 'PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );

// in case script gets called directly
if ( !defined( 'WPINC' ) ) {die;}

require_once trailingslashit(__DIR__).'autoload.php';

use NetDebit\Plugin\WooCommerce\Cart\VirtualItemsOnlyGuard;
use NetDebit\Plugin\WooCommerce\Cart\OneSubscriptionOnly;
use NetDebit\Plugin\WooCommerce\Initialisation\Container;
use NetDebit\Plugin\WooCommerce\Payment\Gateway;
use NetDebit\Plugin\WooCommerce\Product\SubscriptionProduct;
use NetDebit\Plugin\WooCommerce\Table\BookingTable;
use NetDebit\Plugin\WooCommerce\Table\SubscriptionPaymentsTable;
use NetDebit\Plugin\WooCommerce\Table\SubscriptionsTable;
use NetDebit\Plugin\WooCommerce\WebHook\WC_NetDebit_Booking;
use NetDebit\Plugin\WooCommerce\WebHook\WC_NetDebit_ThankYou;

add_action( 'plugins_loaded', 'netdebit_woocommerce_gateway_init');
function netdebit_woocommerce_gateway_init() {

	// in case woocommerce plugin is not active
	if (!class_exists('WC_Payment_Gateway')) {return;}

    load_plugin_textdomain(
        TEXT_DOMAIN,
        false,
        trailingslashit(basename(dirname( __FILE__ ))) . 'localization'
    );

    $plugin = new Container;
    $plugin['payment_gateway'] = new Gateway();
    $plugin['cart_subscription_quantity_guard'] = new OneSubscriptionOnly(TEXT_DOMAIN);
    $plugin['cart_virtual_items_only_guard'] = new VirtualItemsOnlyGuard(PLUGIN_ID, TEXT_DOMAIN);
    $plugin['product_subscriptions'] = new SubscriptionProduct();
    $plugin['webhook_booking'] = new WC_NetDebit_Booking();
    $plugin['webhook_thankyou'] = new WC_NetDebit_ThankYou();

    $plugin->init();
}

register_activation_hook(__FILE__, 'pluginActivation' );
function pluginActivation()
{
    BookingTable::INSTALL();
    SubscriptionsTable::INSTALL();
    SubscriptionPaymentsTable::INSTALL();
}

register_deactivation_hook( __FILE__, 'pluginDeactivation');
function pluginDeactivation(){
    delete_option( WC_PREFIX . PLUGIN_ID .'_settings' );
}