<?php
class WC_NetDebit_Booking{

    const NEW_BOOKING = 0;
    const PAYMENT_ENTRANCE = 1;
    const PAYMENT_DECLINE= 9;

    public static function process(){

        if ( $_SERVER[ 'REQUEST_METHOD' ] !== 'POST' ) die( 'OK=90' ) ;
        try{

            SELF::validate_nd_gw_response_params( );

            switch( $_POST[ 'Status' ] ){

                case SELF::NEW_BOOKING:
                    SELF::handlePayment( );
                    break;

                case SELF::PAYMENT_ENTRANCE:
                    SELF::handlePayment( );
                    break;

                case SELF::PAYMENT_DECLINE:
                    SELF::handleCancellation( );
                    break;

                default:
                    throw new Exception( 'Untreated booking status:' . $_POST[ 'Status' ] );
            }

            die( 'OK=100' );

        }catch( Exception $e ){
            error_log( 'exception thrown! msg: ' . $e -> getMessage() );
            die( 'OK=90' );
        }
    }

    private static function validate_nd_gw_response_params(  ){

        if( !isset( $_POST[ 'BookingNumber' ] ) ){
            throw new Exception( __( 'NetDebit\'s Gateway response does not contain the bookingnumber', 'woocommerce-netdebit' ) );
        }

        if( !isset( $_POST[ 'Status' ] ) ){
            throw new Exception( __( 'NetDebit\'s Gateway response does not contain the booking status', 'woocommerce-netdebit' ) );
        }

        if( $_POST[ 'Status' ] != SELF::PAYMENT_DECLINE ){
            if( !isset( $_POST[ 'VAR1' ] ) ){
                throw new Exception( __( 'NetDebit\'s Gateway response does not contain the VAR1', 'woocommerce-netdebit' ) );
            }
        }
    }

    private static function get_order_by_nd_bookingnumber( $booking_nr ){
        $q = array(
            'post_type'    => 'shop_order',
            'post_status'  => array_keys( wc_get_order_statuses() ),
            'meta_key'     => '_nd_booking_number',
            'meta_value'   => $booking_nr
        );
        $order_post = get_posts( $q );

        if( !isset( $order_post ) ){
            throw new Exception(
                printf( __( 'Could not find order with bookingbumber: %s ', 'woocommerce-netdebit' ), $booking_nr )
            );
        }

        return new WC_Order( $order_post[0] );
    }

    private static function handlePayment( ){

        $order = new WC_Order( $_POST[ 'VAR1' ] );
        if( 'cancceled' !== $order->get_status() ){

            $order -> payment_complete();
            add_post_meta( $order -> id, '_nd_booking_number', $_POST['BookingNumber'] );
        }

    }

    private static function handleCancellation( ){

        $order = SELF::get_order_by_nd_bookingnumber( $_POST[ 'BookingNumber' ] );
        $order -> cancel_order();
    }
}