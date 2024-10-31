<?php
 
spl_autoload_register( 'netdebit_woocommerce_gateway_autoload' );
function netdebit_woocommerce_gateway_autoload($class)
{	
    $prefix = 'NetDebit\\Plugin\\WooCommerce\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
		
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (file_exists($file) && is_readable($file)) {
        require_once $file;
    }
}