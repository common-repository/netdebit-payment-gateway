<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 11.01.2019
 * Time: 14:06
 */

namespace NetDebit\Plugin\WooCommerce\Product;

use NetDebit\Plugin\WooCommerce\Initialisation\NeedsInitialisation;
use WC_Product_Simple;


class SubscriptionProduct extends WC_Product_Simple implements NeedsInitialisation
{
    const TYPE = 'netdebit_subscription';
    const OPTION_NAME_TERM_TYPE = '_termtype';
    const OPTION_NAME_TERM_VALUE = '_termvalue';

    public $product_type = self::TYPE;

    public function __construct($product = 0)
    {
        parent::__construct($product);
        $this->set_virtual(true);
        $this->set_tax_status('none');
        $this->set_sold_individually(true);
    }

    final public function get_type()
    {
        return self::TYPE;
    }

    public function init()
    {
        add_filter( 'product_type_selector', array($this,'register') );
        add_filter( 'woocommerce_product_data_tabs', array($this,'hideShippingTab') );
        add_filter('woocommerce_product_class', array($this, 'getProductClass'),10,2);

        add_action(
            'woocommerce_product_options_general_product_data',
            array($this, 'addSubscriptionDetailsToGeneral')
        );

        add_action('woocommerce_process_product_meta_' . self::TYPE , array($this, 'saveSubscriptionInterval'));
        add_action('admin_enqueue_scripts', array($this, 'admin_load_js'));
    }

    public function register($types)
    {
        $types[ self::TYPE ] = __( 'NetDebit Subscription', TEXT_DOMAIN );
        return $types;
    }

    public function getProductClass($classname, $product_type)
    {
        if ($product_type === self::TYPE) {
            $classname = get_class($this);
        }
        return $classname;
    }

    public function addSubscriptionDetailsToGeneral()
    {
        global $post;

        $termType = get_post_meta($post->ID, self::OPTION_NAME_TERM_TYPE, true);
        $termValue = get_post_meta($post->ID, self::OPTION_NAME_TERM_VALUE, true);


        ?>
        <p class="form-field show_if_<?php echo self::TYPE; ?>">
            <label for="subscription_intervall">
                <?php echo _e('Subscriptions Intervall',TEXT_DOMAIN) ?>
            </label>
            <span class="wrap">
                <input id="subscription_intervall" type="number" min="1" placeholder="1" class="input-number" style="width:20%;margin-right: 2%;" size="6" type="number" name="<?php echo self::OPTION_NAME_TERM_VALUE; ?>" value="<?php echo esc_attr(strlen($termValue)>0?$termValue:1); ?>" />
                <select name="<?php echo self::OPTION_NAME_TERM_TYPE; ?>" class="select short" style="width:28%;" >
                    <option value="" <?php echo strlen($termType)===0?'selected="selected"':''; ?> ><?php echo _e('Select a value', TEXT_DOMAIN); ?></option>
                    <option value="3" <?php echo $termType==3?'selected="selected"':''; ?> > <?php echo _e('days', TEXT_DOMAIN); ?></option>
                    <option value="4" <?php echo $termType==4?'selected="selected"':''; ?> > <?php echo _e('weeks', TEXT_DOMAIN); ?></option>
                    <option value="5" <?php echo $termType==5?'selected="selected"':''; ?> > <?php echo _e('months', TEXT_DOMAIN); ?></option>
                    <option value="6" <?php echo $termType==6?'selected="selected"':''; ?> > <?php echo _e('years', TEXT_DOMAIN); ?></option>
                </select>
            </span>
        </p>
        <?php
    }

    public function is_sold_individually()
    {
        return true;
    }

    public function saveSubscriptionInterval($postId)
    {
        $termType = $_POST[self::OPTION_NAME_TERM_TYPE];
        update_post_meta($postId, self::OPTION_NAME_TERM_TYPE, esc_attr( $termType));

        $termValue = $_POST[self::OPTION_NAME_TERM_VALUE];
        update_post_meta($postId, self::OPTION_NAME_TERM_VALUE, esc_attr($termValue));
    }

    public function hideShippingTab($tabs)
    {
        $tabs['shipping']['class'][] = 'hide_if_' . self::TYPE;
        return $tabs;
    }

    public function admin_load_js()
    {
        if ('product' != get_post_type()) {
            return;
        }
        wp_enqueue_script(
            'subscription_product_js',
            PLUGIN_DIR_URL . '/assets/subscription_product.js',
            array('jquery'),
            false,
            true
        );
    }
}