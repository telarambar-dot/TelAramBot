<?php
// Try different sendMessage parameter shapes to find a valid format for Rubika API
require_once __DIR__ . '/bootstrap.php';

use RubikaBot\Bot;

$token = getenv('RUBIKA_BOT_TOKEN');
$testChatId = getenv('RUBIKA_TEST_CHAT_ID');

if (!$token || !$testChatId) {
    echo "Missing RUBIKA_BOT_TOKEN or RUBIKA_TEST_CHAT_ID in env\n";
    exit(1);
}

$bot = new Bot($token);

$variants = array(
    array('desc' => 'string chat_id + text arg', 'call' => function() use ($bot, $testChatId) { return $bot->sendMessage($testChatId, 'Variant 1'); }),
    array('desc' => 'assoc chat_id + text', 'call' => function() use ($bot, $testChatId) { return $bot->sendMessage(array('chat_id' => $testChatId, 'text' => 'Variant 2')); }),
    array('desc' => 'chat_id as array containing id', 'call' => function() use ($bot, $testChatId) { return $bot->sendMessage(array('chat_id' => array($testChatId), 'text' => 'Variant 3')); }),
    array('desc' => 'chat_id as assoc nested', 'call' => function() use ($bot, $testChatId) { return $bot->sendMessage(array('chat_id' => array('chat_id' => $testChatId), 'text' => 'Variant 4')); }),
);

foreach ($variants as $v) {
    echo "\n=== {$v['desc']} ===\n";
    try {
        $res = $v['call']();
        print_r($res);
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
