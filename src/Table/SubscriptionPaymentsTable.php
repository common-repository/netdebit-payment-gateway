<?php 

namespace NetDebit\Plugin\WooCommerce\Table;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class SubscriptionPaymentsTable
{
    const NAME = 'netdebit_subscription_payments';
    const VERSION = '1.0.0';

    public static function INSTALL()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::NAME;
        $installed_ver = get_option($table_name);

        if ( $installed_ver != self::VERSION ) {
            dbDelta(
            "CREATE TABLE " . $wpdb->prefix . self::NAME . " (
                booking_nr BIGINT NOT NULL,
                subscription_id varchar(36),                                                  
                PRIMARY KEY (booking_nr,subscription_id)                                        
                );"
            );
            update_option(
                $table_name,
                self::VERSION
            );
        }
    }

    public static function INSERT($bookingNr, $subscriptionId)
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::NAME,
            array(
                'booking_nr' => $bookingNr,
                'subscription_id' => $subscriptionId,
            ),
            array('%d', '%s')
        );
    }

    public static function DOES_BOOKING_BELONG_TO_SUBSCRIPTION($bookingNr)
    {
        global $wpdb;
        $res = $wpdb->get_row(
            sprintf(
                "SELECT COUNT(*) as cnt FROM %s where booking_nr = %s ;",
				$wpdb->prefix . self::NAME,
                $bookingNr
            ),
            ARRAY_A
        );
        return $res['cnt'] > 0;
    }

    public static function GET_SUBSCRIPTION_ID($bookingNr)
    {
        global $wpdb;
        $res = $wpdb->get_row(
            sprintf(
                "SELECT subscription_id FROM %s where booking_nr = %s ;",
				$wpdb->prefix . self::NAME,
                $bookingNr
            ),
            ARRAY_A
        );
        return array_key_exists('subscription_id',$res)? $res['subscription_id'] : null;
    }
}
