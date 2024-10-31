<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 16.01.2019
 * Time: 09:58
 */

namespace NetDebit\Plugin\WooCommerce\Cart;


use NetDebit\Plugin\WooCommerce\Initialisation\NeedsInitialisation;

class VirtualItemsOnlyGuard implements NeedsInitialisation
{
    private $pluginId;
    private $textDomain;

    public function __construct($pluginId, $textDomain)
    {
        $this->pluginId = $pluginId;
        $this->textDomain = $textDomain;
    }

    public function init()
    {
        add_action('woocommerce_after_checkout_validation', array($this,'assureVirtualItemsOnly'),10,2);
    }

    public function assureVirtualItemsOnly($data, $errors)
    {
        if ($data['payment_method'] === $this->pluginId) {

            $nonVirtualItems = array();
            foreach (WC()->cart->get_cart() as $item) {
                $p = wc_get_product($item['product_id']);
                if (!$p->is_virtual()) {
                    $nonVirtualItems[] = $p->get_name();
                }
            }

            if (count($nonVirtualItems) > 0) {
                $errors->add(
                    'payment',
                    sprintf(
                        __('Only virtiual (digital) goods can be billed by NetDebit.'.
                            ' Please remove %s from the cart to pay with NetDebit ' ,
                            $this->textDomain
                        ),
                        join(',', $nonVirtualItems)
                    )
                );
            }
        }
    }
}