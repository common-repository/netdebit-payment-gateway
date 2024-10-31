<?php
 class WC_NetDebit_ThankYou{
    public static function doIt()
    {
        if( isset( $_GET[ 'ndVar2' ] ) ){
            $affectedOrder = new WC_Order( $_GET['ndVar2'] );
            wp_redirect( $affectedOrder -> get_checkout_order_received_url() );
            exit;
        }
    }
}