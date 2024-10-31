<?php
/**
* Plugin Name: netdebit-pgw
* Plugin URI: http://www.netdebit-payment.de
* Description: NetDebit payment gateway for woocommerce
* Version: 1.0
* Author: René Scholl
* Text-Domain: woocommerce-netdebit
**/

define( 'WC_PREFIX', 'woocommerce_' );
define( 'PLUGIN_ID', 'netdebit' );

add_action('plugins_loaded', 'wc_netdebit_pgw_init', 0);
function wc_netdebit_pgw_init(){

    if(!class_exists('WC_Payment_Gateway')) return;

    if( !class_exists( 'WC_NetDebit_Payment' ) ) {

        require_once (
            trailingslashit(plugin_dir_path( __FILE__ ) ) .
            trailingslashit( 'includes' ) .
            'class_wc_netdebit_payment.php'
        );

        load_plugin_textdomain(
            'woocommerce-netdebit',
            false,
            trailingslashit( basename( dirname( __FILE__ ) ) ) . trailingslashit( 'localization' )
        );

        register_deactivation_hook( __FILE__, 'delete_netdebit_gw_options' );
    }

    function delete_netdebit_gw_options(){

        delete_option( WC_PREFIX . PLUGIN_ID .'_pid' );
        delete_option( WC_PREFIX . PLUGIN_ID . '_sid' );
        delete_option( WC_PREFIX . PLUGIN_ID . '_con' );
        delete_option( WC_PREFIX . PLUGIN_ID . '_gw_pw' );

    }

    /**
     * Appends our plugin to WooCommerce's paymentgateway collection
     **/
    function wc_append_netdebit_pgw($wc_paymentgateways )
    {
        $wc_paymentgateways[] = 'WC_NetDebit_Payment';
        return $wc_paymentgateways;
    }
    add_filter( 'woocommerce_payment_gateways', 'wc_append_netdebit_pgw' );

}