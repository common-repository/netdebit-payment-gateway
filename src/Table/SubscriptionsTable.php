<?php 

namespace NetDebit\Plugin\WooCommerce\Table;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class SubscriptionsTable
{
    const NAME = 'netdebit_subscriptions';
    const VERSION = '1.0.0';

    public static function INSTALL()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::NAME;
        $installed_ver = get_option($table_name);

        if ( $installed_ver != self::VERSION ) {
            dbDelta(
            "CREATE TABLE " . $wpdb->prefix . self::NAME . " (
                id varchar(36) NOT NULL,
                status tinyint NOT NULL,            
                wp_user_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                created_at timestamp NOT NULL,
                updated_at timestamp NOT NULL,                
                PRIMARY KEY  (id),
                INDEX user_ind (wp_user_id),
                INDEX product_ind (product_id)                    
                );"
            );
            update_option(
                $table_name,
                self::VERSION
            );
        }
    }

    public static function INSERT($id, $status, $user_id, $product_id)
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . self::NAME,
            array(
                'id' => $id,
                'status' => $status,
                'wp_user_id' => $user_id,
                'product_id' => $product_id,
                'created_at' => date('Y-m-d G:i:s'),
                'updated_at' => date('Y-m-d G:i:s')
            ),
            array('%s', '%d', '%d', '%d', '%s', '%s')
        );
    }

    public static function GET_BY_ID($id)
    {
        global $wpdb;
        return $wpdb->get_row(
            sprintf("SELECT * FROM " .$wpdb->prefix.self::NAME." WHERE id = '%s';", $id),
            ARRAY_A
        );
    }

    public static function UPDATE_STATUS($id, $status)
    {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix.self::NAME,
            array(
                'status' => $status,
                'updated_at' => date('Y-m-d G:i:s')
            ),
            array('id' => $id)
        );
    }
}
