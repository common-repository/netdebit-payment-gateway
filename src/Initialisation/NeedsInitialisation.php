<?php
/**
 * Created by PhpStorm.
 * User: rs
 * Date: 08.01.2019
 * Time: 12:57
 */

namespace NetDebit\Plugin\WooCommerce\Initialisation;

interface NeedsInitialisation
{
    public function init();
}