<?php

header('Content-Type: text/plain; charset=UTF-8');

echo "PHP Version: " . PHP_VERSION . PHP_EOL;
echo "OPcache extension: " . (extension_loaded('Zend OPcache') ? 'LOADED' : 'NOT LOADED') . PHP_EOL;
echo "OPcache enabled: " . (ini_get('opcache.enable') ? 'YES' : 'NO') . PHP_EOL;

if (function_exists('opcache_get_status')) {

    $status = opcache_get_status(false);

    echo "OPcache running: " . (!empty($status['opcache_enabled']) ? 'YES' : 'NO') . PHP_EOL;

} else {

    echo "opcache_get_status(): NOT AVAILABLE" . PHP_EOL;

}