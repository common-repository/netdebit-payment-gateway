<?php
class WC_NetDebit_Payment extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this ->id = PLUGIN_ID;
        $this ->medthod_title = 's Payment';
        $this ->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = 'NetDebit Payment';
        $this->description = __( 'NetDebit online payment: Credit Card, SEPA Direct Debit, Klarna SOFORT, Wire Transfer' , 'woocommerce-netdebit') ;
        $this->icon = plugins_url(
            trailingslashit( 'assets' ) . 'nd_logo.png',
            ( realpath( trailingslashit( __DIR__ ) . trailingslashit( '.' ) ) )
        );

        $this->enabled = $this->settings['enabled'];
        $this->pid = $this->settings['pid'];
        $this->con = $this->settings['con'];
        $this->sid = $this->settings['sid'];
        $this->gw_pw = $this->settings['gw_pw'];

        $this -> setup_hooks();
    }

    public function admin_options()
    {
        echo '<h3>' . __('NetDebit online payment:  customer is king! ', 'woocommerce-netdebit') . '</h3>';
        echo '<p>' . __('About 730,000 customers value our payment system, our variety of offers and the supporting service.You can become a king as well and use NetDebit as your prefered payment service provider.', 'woocommerce-netdebit') . '</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        echo '</table>';

    }

    function init_form_fields()
    {

        $this->form_fields = array(

            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce-netdebit' ),
                'type' => 'checkbox',
                'label' => __( 'Enable Netdebit payment module.', 'woocommerce-netdebit' ),
                'default' => 'no',
                'value' => ( $this -> enabled ? 'yes' : 'no' ) ),

            'pid' => array(
                'title' => __( 'Partner ID:', 'woocommerce-netdebit' ),
                'type' => 'number',
                'description' => __( 'Your NetDebit\'s unique partner ID.', 'woocommerce-netdebit' ) ),

            'con' => array(
                'title' => __( 'Content ID:', 'woocommerce-netdebit' ),
                'type' => 'number',
                'description' => __( 'Your NetDebit\'s unique content ID.', 'woocommerce-netdebit' ) ),

            'sid' => array(
                'title' => __( 'Webmaster ID:', 'woocommerce-netdebit' ),
                'type' => 'number',
                'description' => __( 'Your NetDebit\'s unique webmaster ID.', 'woocommerce-netdebit' ) ),

            'gw_pw' => array(
                'title' => __( 'Gateway Password:', 'woocommerce-netdebit' ),
                'type' => 'password',
                'name' => 'nd_gwpw',
                'description' => __( 'Your personal access password to the NetDebit payment gateway.', 'woocommerce-netdebit' ) )

        );

    }

    function validate_nd_options(){

        $criticalErrorPresent = false;

        if( strcmp( $_REQUEST[ 'section' ], 'netdebit-pgw' ) == 0 ) {

            if ( !self::isNonEmptyNumber( $_REQUEST[ WC_PREFIX . $this->id . '_pid' ] ) ) {
                $_REQUEST[ WC_PREFIX . $this->id . '_pid' ] = '';
                $this::add_error(  __( 'Partner ID (PID) required. Only digits allowed', 'woocommerce-netdebit' ) );
                $criticalErrorPresent = true;
            }

            if( !self::isNonEmptyNumber( $_REQUEST[ WC_PREFIX . $this->id . '_con' ] ) ){
                $_REQUEST[ WC_PREFIX . $this->id . '_con' ] = '';
                $this::add_error( __( 'Content ID (CON) required. Only digits allowed', 'woocommerce-netdebit' ) );
                $criticalErrorPresent = true;
            }

            if( $_REQUEST[ WC_PREFIX . $this->id . '_con' ] < 0 ||  $_REQUEST[ WC_PREFIX . $this->id . '_con' ]  > 999999999 ){
                $_REQUEST[ WC_PREFIX . $this->id . '_con' ] = '';
                $this::add_error( __( 'Content ID (CON) must be in range between 0 and 999999999', 'woocommerce-netdebit' ) );
                $criticalErrorPresent = true;
            }

            if( self::isNonEmptyNumber( $_REQUEST[ WC_PREFIX . $this->id . '_sid' ] ) ){
                if( $_REQUEST[ WC_PREFIX . $this->id . '_sid' ] < 773000000
                    ||  $_REQUEST[ WC_PREFIX . $this->id . '_sid' ]  > 773099999 ){

                    $_REQUEST[ WC_PREFIX . $this->id . '_sid' ] ='';
                    $this::add_error( __( 'Webmaster ID (SID) must be in range between 773000000 and 773099999', 'woocommerce-netdebit' ) );
                    $criticalErrorPresent = true;
                }

            }

            if( strlen( $_REQUEST[ WC_PREFIX . $this->id . '_gw_pw' ] ) == 0  ){
                $this::add_error( __( 'Gateway Password required.', 'woocommerce-netdebit' ) );
                $criticalErrorPresent = true;
            }
        }
        if( $criticalErrorPresent ){
            $this -> display_errors();
            exit();
        }

    }

    function process_payment( $order_id )
    {
        global $woocommerce;

        $order = new WC_Order( $order_id );

        $order->update_status(
            'pending',
            __('Awaiting booking notification by NetDebit', 'woocommerce-netdebit')
        );

        // reserve goods for customer
        $order->reduce_order_stock();

        $woocommerce->cart->empty_cart();

        // redirect to NetDebit's payment form
        return array(
            'result' => 'success',
            'redirect' => $this->build_netdebit_payment_form_query( $order )
        );
    }

    private function build_netdebit_payment_form_query( $order ){

        // float format: no thousands_seps, 2 decimals, and decimalpoint
        $order_total = number_format( $order -> calculate_totals(), 2, '.', '' );

        // no checks needed,uninitialised members are left out later on
        $nd_payment_form_args = array(
            'PID' =>  $this -> pid,
            'CON' =>  $this -> con,
            'SID' => $this -> sid,
            'VAR1' => $order -> id,
            'VAR2' => $order -> id, // needed to display the appropiate thank you page
            'TIM' => '0',
            'BET' => $order_total,
            'LZS' => '9',
            'LZW' => '1',
            'VAL' => md5( '' . $order_total . $this -> gw_pw  )
        );

        return 'https://www.netdebit-payment.de/pay/?' .
            http_build_query( $nd_payment_form_args, null, "&", PHP_QUERY_RFC3986 );
    }

    private function setup_hooks(){

        add_action(
            'woocommerce_update_options_payment_gateways_' .
            $this->id, array( $this, 'process_admin_options' )
        );

        add_filter( 'woocommerce_settings_save_checkout', array( $this, 'validate_nd_options' ) );

        require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'class_wc_netdebit_booking.php' );
        add_action( 'woocommerce_api_wc_netdebit_booking', array( 'WC_NetDebit_Booking', 'process') );

        require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'class_wc_netdebit_thankyou.php' );
        add_action( 'woocommerce_api_wc_netdebit_thankyou', array( 'WC_NetDebit_ThankYou', 'doIt' ) );
    }

    private static function isNonEmptyNumber( $val ){

        return isset( $val ) && is_numeric( $val );
    }
}