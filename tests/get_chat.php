<?php
require_once __DIR__ . '/bootstrap.php';
use RubikaBot\Bot;

$token = getenv('RUBIKA_BOT_TOKEN');
$testChatId = getenv('RUBIKA_TEST_CHAT_ID');
if (!$token || !$testChatId) {
    echo "Missing env vars\n"; exit(1);
}

$bot = new Bot($token);
try {
    echo "Calling getChat for {$testChatId}...\n";
    $res = $bot->getChat(array('chat_id' => $testChatId));
    print_r($res);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
