<?php

namespace RubikaBot;

function saveIncomingRequest(array $data): void
{
    $logFile = __DIR__ . '/../req.txt';
    $entry = array(
        'timestamp' => date('c'),
        'request' => $data,
    );
    file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
