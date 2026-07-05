<?php
// Central bootstrap: load Composer autoload if available, then require core sources explicitly
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once __DIR__ . '/Models/Model.php';
require_once __DIR__ . '/Models/Models.php';
require_once __DIR__ . '/Enums/Enums.php';
require_once __DIR__ . '/Handlers/Dispatcher.php';
require_once __DIR__ . '/RequestLogger.php';
require_once __DIR__ . '/DebugLogger.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/RubikaClient.php';
require_once __DIR__ . '/Bot.php';

// Simple .env loader (idempotent)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("{$name}={$value}");
    }
}
