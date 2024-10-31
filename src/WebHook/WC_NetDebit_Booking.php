<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 10.01.2019
 * Time: 11:34
 */

namespace NetDebit\Plugin\WooCommerce\WebHook;

use Exception;
use NetDebit\Plugin\WooCommerce\Initialisation\NeedsInitialisation;
use NetDebit\Plugin\WooCommerce\Table\BookingTable;
use NetDebit\Plugin\WooCommerce\Table\SubscriptionPaymentsTable;
use NetDebit\Plugin\WooCommerce\Table\SubscriptionsTable;
use WC_Customer;
use WC_Order;

class WC_NetDebit_Booking implements NeedsInitialisation
{
    const WC_SETTINGS_PREFIX = 'woocommerce_';
    const WC_SETTINGS_POSTFIX = '_settings';

    const NEW_BOOKING = 0;
    const CREDIT_NOTE = 1;
    const PAYMENT_CHARGEBACK = 9;
    const SUBSCRIPTION_END = 7;

    const ONE_TIME_PAYMENT = 0;

    private $textDomain;
    private $settings;

    public function __construct()
    {
        $this->textDomain = TEXT_DOMAIN;
        $this->settings = get_option(self::WC_SETTINGS_PREFIX . PLUGIN_ID . self::WC_SETTINGS_POSTFIX);
    }

    public function init()
    {
        add_action( 'woocommerce_api_wc_netdebit_booking', array( $this, 'process') );
    }

    public function process(){

        if ($_SERVER[ 'REQUEST_METHOD' ] !== 'POST' ){ die( 'OK=90'); }

        try{

            $this->validate_nd_gw_response_params();

            switch( $_POST[ 'Status' ] ){

                case self::NEW_BOOKING:

                    if ($_POST['AboState'] == self::ONE_TIME_PAYMENT) {
                        $this->handlePayment();
                    } else {
                        $this->handleSubscription();
                    }
                    break;

                case self::CREDIT_NOTE:

                    $this->handleCredit( );
                    break;

                case self::PAYMENT_CHARGEBACK:

                    $this->handleChargeback();
                    break;

                case self::SUBSCRIPTION_END:

                    $this->handleSubscriptionEnd();
                    break;

                default:
                    throw new Exception('Untreated booking status:' . $_POST[ 'Status' ]);
            }

            die( 'OK=100' );

        }catch(Exception $e){
            error_log( 'exception thrown! msg: ' . $e->getMessage());
            echo $e->getMessage();
            die( 'OK=90' );
        }
    }

    /**
     * @throws Exception
     */
    private function validate_nd_gw_response_params(){

        if(!isset($_POST[ 'GatePass' ])){
            throw new Exception( __('NetDebit\'s Gateway password not set', $this->textDomain));
        }

        if( $this->settings['gw_pw'] !== $_POST[ 'GatePass' ] ){
            throw new Exception( __('NetDebit\'s Gateway password does not match', $this->textDomain));
        }

        if( !isset( $_POST[ 'BookingNumber' ] ) ){
            throw new Exception( __('NetDebit\'s Gateway response does not contain the bookingnumber', $this->textDomain));
        }

        if( !isset( $_POST[ 'Status' ] ) ){
            throw new Exception( __('NetDebit\'s Gateway response does not contain the booking status', $this->textDomain));
        }

        if( $_POST[ 'Status' ] == self::NEW_BOOKING ){
            if( !isset( $_POST[ 'AboState' ] ) ){
                throw new Exception( __('NetDebit\'s Gateway response does not contain the AboState', $this->textDomain));
            }
            if( !isset( $_POST[ 'VAR1' ] ) ){
                throw new Exception( __('NetDebit\'s Gateway response does not contain the VAR1', $this->textDomain));
            }
        }
    }

    private function handlePayment(){

        if (BookingTable::CONTAINS($_POST['BookingNumber'])) {return;}

        $order = new WC_Order($_POST[ 'VAR1' ]);
        $this->recordUserBooking($order);
        $order->payment_complete();
    }

    /**
     * @throws \WC_Data_Exception
     */
    private function handleSubscription()
    {
        if (BookingTable::CONTAINS($_POST['BookingNumber'])) {return;}

        // renewal
        if (!isset($_POST['VAR2']) || strlen($_POST['VAR2']) === 0){

            $subscriptionId = base64_decode($_POST['VAR1']);
            $subscriptionData = SubscriptionsTable::GET_BY_ID($subscriptionId);

            $order = $this->generateOrder($subscriptionData);
            $this->recordUserBooking($order);
            $this->associateBookingWithSubscription($subscriptionId);

            // first payment
        } else {

            $order = new WC_Order($_POST['VAR2']);
            $this->recordUserBooking($order);
            $this->recordSubscription($order);
            $this->associateBookingWithSubscription(base64_decode($_POST['VAR1']));
        }

        $order->payment_complete();
    }

    /**
     * @throws Exception
     */
    private function handleChargeback()
    {
        $bookingData = BookingTable::GET_ROW($_POST['BookingNumber']);
        $this->updateBookingStatus($bookingData);

        if ($order = wc_get_order($bookingData['order_id'])) {
            $order->update_status(
                'on-hold',
                __('Customer caused a chargeback. Collection procedure running.', $this->textDomain)
            );
        } else {
            throw new Exception('Could not find order : ' .$bookingData['order_id']. '. Was it deleted?');
        }
    }

    /**
     * @throws Exception
     */
    private function handleCredit()
    {
        $bookingData = BookingTable::GET_ROW($_POST['BookingNumber']);
        $this->updateBookingStatus($bookingData);

        if ($order = wc_get_order($bookingData['order_id'])) {
            $order->payment_complete();
        } else {
            throw new Exception('Could not find order : ' .$bookingData['order_id']. '. Was it deleted?');
        }
    }

    /**
     * @throws Exception
     */
    private function handleSubscriptionEnd()
    {
        $bookingData = BookingTable::GET_ROW($_POST['BookingNumber']);
        $this->updateBookingStatus($bookingData);
    }

    /**
     * @param $bookingData
     * @throws Exception
     */
    private function updateBookingStatus($bookingData)
    {
        if ($bookingData !== null && count($bookingData) > 0) {

            BookingTable::UPDATE_STATUS($_POST['BookingNumber'], $_POST['Status']);

            if (SubscriptionPaymentsTable::DOES_BOOKING_BELONG_TO_SUBSCRIPTION($_POST['BookingNumber'])) {

                $subscriptionId = SubscriptionPaymentsTable::GET_SUBSCRIPTION_ID($_POST['BookingNumber']);

                SubscriptionsTable::UPDATE_STATUS($subscriptionId, $_POST['Status'] );
            }
        } else {
            throw new Exception('Could not find record for booking number: ' . $_POST['BookingNumber']);
        }
    }

    private function recordUserBooking(WC_Order $o)
    {
        BookingTable::INSERT(
            $_POST[ 'BookingNumber' ],
            $_POST[ 'Amount' ],
            $_POST[ 'Status' ],
            $o->get_user_id(),
            $o->get_id()
        );
    }

    private function recordSubscription(WC_Order $o)
    {
        $subscriptionId = base64_decode($_POST['VAR1']);

        $orderItem = array_pop($o->get_items());
        $productId = $orderItem->get_data()['product_id'];

        SubscriptionsTable::INSERT(
            $subscriptionId,
            1,
            $o->get_user_id(),
            $productId
        );
    }

    private function associateBookingWithSubscription($subscriptionId)
    {
        SubscriptionPaymentsTable::INSERT($_POST['BookingNumber'], $subscriptionId);
    }

    /***
     * @param $subscriptionData
     * @return WC_Order|\WP_Error
     * @throws \WC_Data_Exception
     */
    private function generateOrder($subscriptionData)
    {
        $order = wc_create_order();
        $order->add_product(wc_get_product($subscriptionData['product_id']));

        if ($this->settings['hide_billing'] === 'yes') {

            $order->set_customer_id($subscriptionData['wp_user_id']);
        } else {

            $customer = new WC_Customer($subscriptionData['wp_user_id']);
            $order->set_address($customer->get_billing(),'billing');
        }

        $order->calculate_totals();
        return $order;
    }
}