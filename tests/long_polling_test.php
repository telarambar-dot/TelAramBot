<?php
/**
 * Simple long-polling test script for Rubika bot.
 * - Reads RUBIKA_BOT_TOKEN from env or first arg
 * - Calls getUpdates in a loop and appends received messages to tests/received_messages.txt
 * Usage:
 *   php tests/long_polling_test.php
 * (set RUBIKA_BOT_TOKEN in .env or environment)
 */

require_once __DIR__ . '/bootstrap.php';

use RubikaBot\Bot;

// Load .env if present (so token can be read from it)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((strlen($value) >= 2) && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$token = getenv('RUBIKA_BOT_TOKEN') ?: (isset($argv[1]) ? $argv[1] : null);
if (!$token) {
    fwrite(STDERR, "ERROR: set RUBIKA_BOT_TOKEN env var or pass token as first arg\n");
    exit(1);
}

$outFile = __DIR__ . '/received_messages.txt';
echo "Long polling started, logging to: {$outFile}\n";

$bot = new Bot($token);
$startId = 0;

while (true) {
    $params = array();
    if ($startId) $params['start_id'] = $startId;

    try {
        $res = $bot->getUpdates($params);
    } catch (\Throwable $e) {
        fwrite(STDERR, "getUpdates error: " . $e->getMessage() . "\n");
        sleep(3);
        continue;
    }

    // Try common response shapes
    $updates = array();
    if (isset($res['data']) && isset($res['data']['updates']) && is_array($res['data']['updates'])) {
        $updates = $res['data']['updates'];
    } elseif (isset($res['updates']) && is_array($res['updates'])) {
        $updates = $res['updates'];
    }

    // Persist start_id if returned
    if (isset($res['data']) && isset($res['data']['start_id'])) {
        $startId = $res['data']['start_id'];
    }

    foreach ($updates as $u) {
        // Normalize to Update model if possible
        $updateArr = isset($u['update']) ? $u['update'] : $u;

        $chatId = $updateArr['chat_id'] ?? ($updateArr['new_message']['sender_id'] ?? '');
        $type = $updateArr['type'] ?? '';
        $msg = '';
        if (isset($updateArr['new_message']) && isset($updateArr['new_message']['text'])) {
            $msg = $updateArr['new_message']['text'];
        } elseif (isset($updateArr['inline_message']) && isset($updateArr['inline_message']['text'])) {
            $msg = $updateArr['inline_message']['text'];
        }

        $time = date('Y-m-d H:i:s');
        $entry = "[{$time}] type={$type} chat_id={$chatId} message=" . str_replace("\n", ' ', $msg) . PHP_EOL;
        file_put_contents($outFile, $entry, FILE_APPEND | LOCK_EX);
        echo $entry;
    }

    // If no updates, sleep shortly
    sleep(2);
}
