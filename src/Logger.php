<?php

namespace RubikaBot;

class Logger
{
    private string $logFile;

    public function __construct(string $logFile = null)
    {
        $this->logFile = $logFile ?? dirname(__DIR__) . '/req.txt';
    }

    public function saveIncomingRequest(array $data): void
    {
        $entry = array(
            'timestamp' => date('c'),
            'request' => $data,
        );

        $payload = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $written = @file_put_contents($this->logFile, $payload, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $tempFile = sys_get_temp_dir() . '/rubika_req.txt';
            @file_put_contents($tempFile, $payload, FILE_APPEND | LOCK_EX);
        }
    }
}
