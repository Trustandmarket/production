<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

$_SERVER['MANGOPAY_BASE_URL'] = 'https://api.mangopay.com';
$_SERVER['MANGOPAY_URL'] = 'https://api.mangopay.com/v2.01/';
$_SERVER['MANGOPAY_CLIENT'] = 'trustandmarket2017';
$_SERVER['MANGOPAY_PASSWORD'] = 'T7R455NfX2MukJSELrhxWDGMm0e88trjscGxLUKesGC1V2PfqW';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
