<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (isset($_SERVER['REQUEST_URI'])) {
    $prefix = '/kpkitchenadminpanel';
    $len = strlen($prefix);
    if (stripos($_SERVER['REQUEST_URI'], $prefix) === 0) {
        $_SERVER['REQUEST_URI'] = '/KPKitchenAdminPanel' . substr($_SERVER['REQUEST_URI'], $len);
    }
}

if (isset($_SERVER['SCRIPT_NAME'])) {
    if (stripos($_SERVER['REQUEST_URI'], '/public/') === false) {
        $_SERVER['SCRIPT_NAME'] = str_ireplace('/public/index.php', '/index.php', $_SERVER['SCRIPT_NAME']);
        $_SERVER['SCRIPT_NAME'] = str_ireplace('/public/', '/', $_SERVER['SCRIPT_NAME']);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
