<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 10.01.2019
 * Time: 10:36
 */

namespace NetDebit\Plugin\WooCommerce\Payment;

use NetDebit\Plugin\WooCommerce\Initialisation\NeedsInitialisation;
use NetDebit\Plugin\WooCommerce\Product\SubscriptionProduct;
use WC_Order;
use WC_Payment_Gateway;

class Gateway extends WC_Payment_Gateway implements NeedsInitialisation
{
    const PAYMENT_RECURRING = 1;
    const PAYMENT_ONETIME = 0;

    const TERM_TYPE_AMOUNT = 9;

    private $pid;
    private $con;
    private $sid;
    private $gw_pw;
    private $pay_form_style;
    private $hide_billing;

    private $textDomain;

    public function __construct()
    {
        $this->id = PLUGIN_ID;
        $this->textDomain = TEXT_DOMAIN;
        $this->method_title = 'NetDebit';
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'NetDebit Payment';
        $this->description = __( 'NetDebit online payment: Credit Card, SEPA Direct Debit, Klarna SOFORT, Wire Transfer' , $this->textDomain) ;
        $this->icon = PLUGIN_DIR_URL . '/assets/nd_logo.png';
        $this->enabled = $this->settings['enabled'];
        $this->pid = $this->settings['pid'];
        $this->con = $this->settings['con'];
        $this->sid = $this->settings['sid'];
        $this->gw_pw = $this->settings['gw_pw'];
        $this->pay_form_style = $this->settings['pay_form_style'];
        $this->hide_billing = $this->settings['hide_billing'];
    }

    public function init()
    {
        add_filter( 'woocommerce_payment_gateways', array($this,'appendToGatewayList') );

        add_action(
            'woocommerce_update_options_payment_gateways_' .
            $this->id, array( $this, 'process_admin_options')
        );

        add_filter('woocommerce_settings_save_checkout', array($this, 'validate_nd_options'));

        add_filter('woocommerce_checkout_fields' , array($this, 'hide_checkout_billing_address_input'));
    }

    function hide_checkout_billing_address_input( $fields ) {

        if ($this->hide_billing === 'yes') {

            unset($fields['billing']['billing_first_name']);
            unset($fields['billing']['billing_last_name']);
            unset($fields['billing']['billing_company']);
            unset($fields['billing']['billing_address_1']);
            unset($fields['billing']['billing_address_2']);
            unset($fields['billing']['billing_city']);
            unset($fields['billing']['billing_postcode']);
            unset($fields['billing']['billing_country']);
            unset($fields['billing']['billing_state']);
            unset($fields['billing']['billing_phone']);
            unset($fields['order']['order_comments']);
            unset($fields['billing']['billing_address_2']);
            unset($fields['billing']['billing_postcode']);
            unset($fields['billing']['billing_company']);
            unset($fields['billing']['billing_last_name']);
            unset($fields['billing']['billing_email']);
            unset($fields['billing']['billing_city']);
        }

        return $fields;
    }

    public function appendToGatewayList($gates)
    {
        $gates[] = get_class($this);
        return $gates;
    }

    public function admin_options()
    {
        echo '<h3>' . __('NetDebit online payment:  customer is king! ', $this->textDomain) . '</h3>';
        echo '<p>' . __('About 730,000 customers value our payment system, our variety of offers and the supporting service.You can become a king as well and use NetDebit as your prefered payment service provider.', $this->textDomain) . '</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        echo '</table>';
    }

    public function init_form_fields()
    {
        $this->form_fields = array(

            'enabled' => array(
                'title' => __('Enable/Disable', $this->textDomain),
                'type' => 'checkbox',
                'label' => __('Enable Netdebit payment module.', $this->textDomain),
                'default' => 'no',
                'value' => ($this->enabled ? 'yes' : 'no' )),

            'pid' => array(
                'title' => __('Partner ID:', $this->textDomain),
                'type' => 'number',
                'description' => __('Your NetDebit\'s unique partner ID.', $this->textDomain)),

            'con' => array(
                'title' => __('Content ID:', $this->textDomain),
                'type' => 'number',
                'description' => __('Your NetDebit\'s unique content ID.', $this->textDomain)),

            'sid' => array(
                'title' => __('Webmaster ID:', $this->textDomain),
                'type' => 'number',
                'description' => __('Your NetDebit\'s unique webmaster ID.', $this->textDomain)),

            'gw_pw' => array(
                'title' => __('Gateway Password:', $this->textDomain),
                'type' => 'password',
                'name' => 'nd_gwpw',
                'description' => __('Your personal access password to the NetDebit payment gateway.', $this->textDomain)),

            'pay_form_style' => array(
                'title' => __('Payment Form Style:', $this->textDomain),
                'description' => __('Choose whether you want the NetDebit payment form styled as a single page or wizard like.', $this->textDomain),
                'type' => 'select',
                'default' => 'v2',
                'options' => array(
                    'v1' => 'Wizard',
                    'v2' => 'Single Page'
                )
            ),
            'hide_billing' => array(
                'title' => __('Billing Address Information', $this->textDomain),
                'type' => 'checkbox',
                'label' => __('Hide billing address input fields.', $this->textDomain),
                'default' => 'yes',
                'value' => ($this->enabled ? 'yes' : 'no' )),

        );
    }

    public function validate_nd_options(){

        $criticalErrorPresent = false;

        if(strcmp( $_REQUEST[ 'section' ], 'netdebit-pgw') == 0 ) {

            if (!$this->isNonEmptyNumber( $_REQUEST[ WC_PREFIX . $this->id . '_pid' ])) {
                $_REQUEST[ WC_PREFIX . $this->id . '_pid' ] = '';
                $this::add_error(  __( 'Partner ID (PID) required. Only digits allowed', $this->textDomain ) );
                $criticalErrorPresent = true;
            }

            if(!$this->isNonEmptyNumber( $_REQUEST[ WC_PREFIX . $this->id . '_con' ])){
                $_REQUEST[ WC_PREFIX . $this->id . '_con' ] = '';
                $this::add_error( __( 'Content ID (CON) required. Only digits allowed', $this->textDomain ) );
                $criticalErrorPresent = true;
            }

            if($_REQUEST[ WC_PREFIX . $this->id . '_con' ] < 0 ||  $_REQUEST[WC_PREFIX . $this->id . '_con']  > 999999999 ){
                $_REQUEST[ WC_PREFIX . $this->id . '_con' ] = '';
                $this::add_error( __( 'Content ID (CON) must be in range between 0 and 999999999', $this->textDomain ) );
                $criticalErrorPresent = true;
            }

            if( $this->isNonEmptyNumber( $_REQUEST[ WC_PREFIX . $this->id . '_sid' ] ) ){
                if( $_REQUEST[ WC_PREFIX . $this->id . '_sid' ] < 773000000
                    ||  $_REQUEST[ WC_PREFIX . $this->id . '_sid' ]  > 773099999 ){

                    $_REQUEST[ WC_PREFIX . $this->id . '_sid' ] ='';
                    $this::add_error( __( 'Webmaster ID (SID) must be in range between 773000000 and 773099999', $this->textDomain ) );
                    $criticalErrorPresent = true;
                }
            }

            if( strlen( $_REQUEST[ WC_PREFIX . $this->id . '_gw_pw' ] ) == 0  ){
                $this::add_error( __( 'Gateway Password required.', $this->textDomain ) );
                $criticalErrorPresent = true;
            }
        }

        if( $criticalErrorPresent ){
            $this->display_errors();
            exit();
        }

    }

    public function process_payment( $order_id )
    {
        global $woocommerce;

        $order = new WC_Order( $order_id );

        $order->update_status(
            'pending',
            __('Awaiting booking notification by NetDebit', $this->textDomain)
        );

        // reserve goods for customer
        wc_reduce_stock_levels($order_id);

        $woocommerce->cart->empty_cart();

        // redirect to NetDebit's payment form
        return array(
            'result' => 'success',
            'redirect' => $this->build_netdebit_payment_form_query( $order )
        );
    }

    private function build_netdebit_payment_form_query(WC_Order $order ){

        // float format: no thousands_seps, 2 decimals, and decimalpoint
        $order_total = number_format( $order->calculate_totals(), 2, '.', '' );




        $tim = self::PAYMENT_ONETIME;
        $lzs = self::TERM_TYPE_AMOUNT;
        $var1 = $order->get_id();
        $var2 = $order->get_id();
        $lzw = 1;

        if ($order->get_item_count() == 1) {

            $orderItem = array_pop($order->get_items());
            $productId = $orderItem->get_data()['product_id'];
            $product = wc_get_product($productId);

            if ($product->is_type(SubscriptionProduct::TYPE)) {

                $tim = self::PAYMENT_RECURRING;
                $lzs = get_post_meta($productId, SubscriptionProduct::OPTION_NAME_TERM_TYPE, true);
                $lzw = get_post_meta($productId, SubscriptionProduct::OPTION_NAME_TERM_VALUE, true);
                $var1 = base64_encode(wp_generate_uuid4());
            }
        }

        // no checks needed,uninitialised members are left out later on
        $nd_payment_form_args = array(
            'PID' =>  $this->pid,
            'CON' =>  $this->con,
            'SID' => $this->sid,
            'VAR1' => $var1,
            'VAR2' => $var2, // needed to display the appropiate thank you page
            'TIM' => $tim,
            'BET' => $order_total,
            'LZS' => $lzs,
            'LZW' => $lzw,
            'VAL' => md5( '' . $order_total . $this->gw_pw)
        );

        $baseUrl = 'https://www.netdebit-payment.de/buy';
        $baseUrl = $this->pay_form_style === 'v2' ? $baseUrl.'/v2/init?' : $baseUrl.'/init?';

        return $baseUrl .
            http_build_query( $nd_payment_form_args, null, "&", PHP_QUERY_RFC3986 );
    }

    private function isNonEmptyNumber($val){
        return isset($val) && is_numeric($val);
    }
}