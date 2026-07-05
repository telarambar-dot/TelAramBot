<?php
require_once __DIR__ . '/bootstrap.php';

use RubikaBot\Models\Message;

$m = Message::fromArray(['message_id' => 'm1', 'text' => 'hello']);
var_dump($m);
