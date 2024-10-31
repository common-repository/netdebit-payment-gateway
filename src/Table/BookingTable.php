<?php

namespace NetDebit\Plugin\WooCommerce\Table;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class BookingTable
{
    const NAME = 'netdebit_bookings';
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
                amount DECIMAL(8,2) NOT NULL,            
                status tinyint NOT NULL,
                created_at timestamp NOT NULL,
                updated_at timestamp NOT NULL,
                wp_user_id bigint(20) unsigned NOT NULL,
                order_id bigint(20) unsigned NOT NULL,
                INDEX user_ind (wp_user_id),
                INDEX order_ind (order_id),
                PRIMARY KEY  (booking_nr)                
                );"
            );
            update_option(
                $table_name,
                self::VERSION
            );
        }
    }

    public static function INSERT($bookingNr, $amount, $status, $user_id, $order_id)
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::NAME,
            array(
                'booking_nr' => $bookingNr ,
                'amount' => $amount,
                'status' => $status,
                'created_at' => date('Y-m-d G:i:s'),
                'updated_at' => date('Y-m-d G:i:s'),
                'wp_user_id' => $user_id,
                'order_id' => $order_id
            ),
            array('%d', '%f', '%d', '%s', '%s', '%d', '%d')
        );
    }

    public static function GET_ROW($bookingNr)
    {
        global $wpdb;
        return $wpdb->get_row(
            sprintf("SELECT * FROM ".$wpdb->prefix.self::NAME." WHERE booking_nr = %s", $bookingNr)
            ,ARRAY_A
        );
    }

    public static function CONTAINS($bookingNr)
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

    public static function UPDATE_STATUS($bookingNr, $status)
    {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix.self::NAME,
            array(
                'status' => $status,
                'updated_at' => date('Y-m-d G:i:s'),
            ),
            array('booking_nr' => $bookingNr)
        );
    }
}
