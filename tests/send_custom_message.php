<?php
require_once __DIR__ . '/bootstrap.php';

$token = getenv('RUBIKA_BOT_TOKEN');
$chat = getenv('RUBIKA_TEST_CHAT_ID');
$text = isset($argv[1]) ? $argv[1] : (getenv('RUBIKA_TEST_MESSAGE') ?: 'Hello from test');

if (!$token || !$chat) {
    echo "Set RUBIKA_BOT_TOKEN and RUBIKA_TEST_CHAT_ID in env or .env\n"; exit(1);
}

$bot = new \RubikaBot\Bot($token);
echo "Sending custom message to {$chat}: {$text}\n";
$res = $bot->sendMessage(array('chat_id' => $chat, 'text' => $text));
print_r($res);
