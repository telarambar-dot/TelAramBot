<?php

// Simple integration-style test for webhook handling and model hydration

require_once __DIR__ . '/bootstrap.php';

use RubikaBot\Bot;

echo "Running webhook tests...\n";

$bot = new Bot('TEST_TOKEN');

$bot->dispatcher()->onNewMessage(function($update) {
    echo "DISPATCHED_NEW_MESSAGE\n";
    echo "chat_id: " . ($update->chat_id ?? 'NULL') . "\n";
    if (isset($update->new_message)) {
        echo "message_id: " . ($update->new_message->message_id ?? 'NULL') . "\n";
        echo "text: " . ($update->new_message->text ?? 'NULL') . "\n";
    }
});

// Test 1: wrapped 'update'
echo "Test 1: wrapped update\n";
$payload = [
    'update' => [
        'type' => 'NewMessage',
        'chat_id' => '12345',
        'new_message' => [
            'message_id' => 'm1',
            'text' => 'hello world',
        ],
    ],
];
$bot->handleWebhook($payload);

// Test 2: inline_message normalization
echo "Test 2: inline_message\n";
$payload2 = [
    'inline_message' => [
        'sender_id' => 'u1',
        'text' => 'inline text',
        'message_id' => 'im1',
        'chat_id' => '54321',
    ],
];
$bot->handleWebhook($payload2);

echo "Tests completed.\n";

return 0;
