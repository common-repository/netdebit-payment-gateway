<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 15.01.2019
 * Time: 08:04
 */

namespace NetDebit\Plugin\WooCommerce\Cart;

use NetDebit\Plugin\WooCommerce\Initialisation\NeedsInitialisation;
use NetDebit\Plugin\WooCommerce\Product\SubscriptionProduct;
use WC_Product;

class OneSubscriptionOnly implements NeedsInitialisation
{
    private $textDomain;

    public function __construct($textDomain)
    {
        $this->textDomain = $textDomain;
    }

    public function init(){
        add_filter('woocommerce_add_to_cart_validation', array($this,'checkToBeAddedProduct'),10,2);
        add_filter('woocommerce_add_to_cart_fragments' , array($this, 'appendNoticeFragmentToAjaxResponse'),10,1);
    }

    public function appendNoticeFragmentToAjaxResponse($fragments)
    {
        if (wc_notice_count() > 0) {
            $fragments['div.woocommerce-notices-wrapper'] =
                '<div class="woocommerce-notices-wrapper">' .
                    wc_print_notices(true) .
                '</div>'
            ;
        }
        return $fragments;
    }

    public function checkToBeAddedProduct($passed, $productId)
    {
        $product = wc_get_product($productId);
        if ($product && $product->is_type(SubscriptionProduct::TYPE)) {
            if (WC()->cart->get_cart_contents_count() > 0) {
                $this->generateBuyAloneError($product);
                return false;
            }
        } else {
            foreach (WC()->cart->get_cart() as $cartItem) {
                $p = wc_get_product($cartItem['product_id']);
                if ($p->is_type(SubscriptionProduct::TYPE)){
                    $this->generateBuyAloneError($p);
                    return false;
                }
            }
        }
        return $passed;
    }

    private function generateBuyAloneError(WC_Product $p)
    {
        wc_add_notice(
            sprintf(
                '<a href="%s" class="button wc-forward">%s</a> %s', wc_get_cart_url(),
                __( 'View cart', 'woocommerce' ),
                sprintf( __( 'You can not buy %s together with other products', $this->textDomain ),
                    $p->get_name()
                )
            ),
            'error'
        );
    }
}