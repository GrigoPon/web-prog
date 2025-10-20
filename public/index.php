<?php
// public/index.php
error_log('['.date('c').'] Start request');

use App\Kernel;
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

error_log('['.date('c').'] Autoload done');

return function (array $context) {
    error_log('['.date('c').'] Creating kernel');
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    error_log('['.date('c').'] Kernel ready');
    return $kernel;
};
