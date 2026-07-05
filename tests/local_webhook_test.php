<?php

// Local webhook test for public/hook.php

$url = 'http://localhost:8001/hook.php?token=TEST_TOKEN';
$payload = array(
    'update' => array(
        'type' => 'NewMessage',
        'chat_id' => 'LOCAL_TEST',
        'new_message' => array(
            'message_id' => 'test123',
            'text' => 'سلام از تست خودکار لوکال',
        ),
    ),
);

$body = json_encode($payload, JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

fwrite(STDOUT, "POST URL: {$url}\n");
fwrite(STDOUT, "HTTP CODE: {$code}\n");
if ($err) {
    fwrite(STDOUT, "CURL ERROR: {$err}\n");
}
fwrite(STDOUT, "RESPONSE: {$response}\n");

if (is_file(__DIR__ . '/../req.txt')) {
    $lines = file(__DIR__ . '/../req.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $count = count($lines);
    fwrite(STDOUT, "REQ.TXT LINES: {$count}\n");
    $tail = array_slice($lines, -5);
    foreach ($tail as $line) {
        fwrite(STDOUT, $line . "\n");
    }
} else {
    fwrite(STDOUT, "req.txt not found\n");
}
