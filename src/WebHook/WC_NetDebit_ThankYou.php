<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 10.01.2019
 * Time: 11:42
 */

namespace NetDebit\Plugin\WooCommerce\WebHook;


use NetDebit\Plugin\WooCommerce\Initialisation\NeedsInitialisation;
use WC_Order;

class WC_NetDebit_ThankYou implements NeedsInitialisation {

    public function init()
    {
        add_action( 'woocommerce_api_wc_netdebit_thankyou', array( $this, 'doIt' ) );
    }

    public function doIt()
    {
        if( isset($_GET[ 'ndVar2' ]) ){
            $affectedOrder = new WC_Order( $_GET['ndVar2'] );
            wp_redirect( $affectedOrder -> get_checkout_order_received_url() );
            exit;
        }
    }
}