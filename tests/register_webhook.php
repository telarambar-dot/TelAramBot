<?php
require_once __DIR__ . '/bootstrap.php';

$token = getenv('RUBIKA_BOT_TOKEN');
if (!$token) {
    fwrite(STDERR, "ERROR: RUBIKA_BOT_TOKEN not set in environment or .env\n");
    exit(1);
}

$url = getenv('RUBIKA_WEBHOOK_URL') ?: 'https://vamban.runflare.run/public/hook.php';
$type = 'ReceiveUpdate';

$bot = new \RubikaBot\Bot($token);
try {
    $res = $bot->updateWebhook($url, $type);
    echo "Webhook registration response:\n";
    print_r($res);
} catch (\Throwable $e) {
    echo "Webhook registration failed: " . $e->getMessage() . "\n";
}
