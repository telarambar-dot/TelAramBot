<?php
// Live test for Rubika API: calls getMe and updateWebhook.
// Usage:
//   set RUBIKA_BOT_TOKEN env var and run: php tests/run_live_test.php https://yourdomain.example/endpoint
// Or pass token as first arg and webhook URL as second arg.

require_once __DIR__ . '/bootstrap.php';

use RubikaBot\Bot;

$token = getenv('RUBIKA_BOT_TOKEN') ?: (isset($argv[1]) ? $argv[1] : null);
$webhookUrl = getenv('RUBIKA_WEBHOOK_URL') ?: (isset($argv[2]) ? $argv[2] : null);
$testChatId = getenv('RUBIKA_TEST_CHAT_ID') ?: (isset($argv[3]) ? $argv[3] : null);

if (!$token) {
    echo "ERROR: No token provided. Set RUBIKA_BOT_TOKEN env var or pass as first arg.\n";
    exit(1);
}

echo "Using token from environment or arg.\n";

$bot = new Bot($token);

try {
    echo "Calling getMe()...\n";
    $me = $bot->getMe();
    print_r($me);
} catch (\Throwable $e) {
    echo "getMe failed: " . $e->getMessage() . "\n";
}

if ($webhookUrl) {
    try {
        echo "Setting webhook to: {$webhookUrl}\n";
        $res = $bot->updateWebhook($webhookUrl, 'ReceiveUpdate');
        print_r($res);
    } catch (\Throwable $e) {
        echo "updateWebhook failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "No webhook URL provided; skipping updateWebhook.\n";
}

if ($testChatId) {
    try {
        echo "Sending test message to chat_id={$testChatId}...\n";
        $res = $bot->sendMessage($testChatId, 'Test message from run_live_test');
        print_r($res);
    } catch (\Throwable $e) {
        echo "sendMessage failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "No test chat id provided; skipping sendMessage.\n";
}

echo "Done.\n";
