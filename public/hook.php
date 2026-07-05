<?php
// Webhook endpoint and registration helper for Rubika SDK

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Enums/Enums.php';

use RubikaBot\Bot;

function saveUserMessage(array $payload): void
{
    $logFile = __DIR__ . '/../UserMessageLog.txt';
    $entry = array(
        'timestamp' => date('c'),
        'payload' => $payload,
    );
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function saveInvalidWebhookPayload(string $raw, string $error): void
{
    $logFile = __DIR__ . '/../UserMessageLog.txt';
    $entry = array(
        'timestamp' => date('c'),
        'raw' => $raw,
        'error' => $error,
    );
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function getWebhookToken(): ?string
{
    $envToken = getenv('RUBIKA_BOT_TOKEN');
    if ($envToken !== false && $envToken !== '') {
        return $envToken;
    }

    return isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : null;
}

function getChatName(Bot $bot, string $chatId): ?string
{
    try {
        $chatInfo = $bot->getChat(array('chat_id' => $chatId));
        if (isset($chatInfo['data']['first_name'])) {
            $name = $chatInfo['data']['first_name'];
            if (!empty($chatInfo['data']['last_name'])) {
                $name .= ' ' . $chatInfo['data']['last_name'];
            }
            return trim($name);
        }
    } catch (\Throwable $e) {
        // ignore failures
    }

    return null;
}

function getUserName(array $update, Bot $bot): string
{
    $firstName = $update['new_message']['first_name'] ?? null;
    $lastName = $update['new_message']['last_name'] ?? null;
    $name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

    if ($name !== '') {
        return $name;
    }

    $senderId = $update['new_message']['sender_id'] ?? null;
    if ($senderId) {
        $name = getChatName($bot, $senderId);
        if ($name) {
            return $name;
        }
    }

    $chatId = $update['chat_id'] ?? ($update['new_message']['chat_id'] ?? null);
    if ($chatId) {
        $name = getChatName($bot, $chatId);
        if ($name) {
            return $name;
        }
    }

    return 'دوست گرامی';
}

function logIncomingMessage(array $update): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $chatId = $update['chat_id'] ?? ($update['new_message']['chat_id'] ?? null);
    $text = $update['new_message']['text'] ?? '';
    $entry = array(
        'time' => time(),
        'chat_id' => $chatId,
        'text' => $text,
        'update' => $update,
    );

    @file_put_contents($logDir . '/messages.log', json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function registerHandlers(Bot $bot): void
{
    $bot->dispatcher()->onNewMessage(function($update) use ($bot) {
        $chatId = $update->chat_id ?? ($update->new_message->chat_id ?? null);
        $text = $update->new_message->text ?? '';

        logIncomingMessage((array)$update);

        if ($text === '/start' && $chatId) {
            $name = getUserName((array)$update, $bot);
            try {
                $bot->sendMessage($chatId, "سلام {$name}! خوش آمدیدی به ربات ما.");
            } catch (\Throwable $e) {
                // ignore send failures
            }
        }

        if (isset($_GET['auto_reply']) && $_GET['auto_reply'] === '1' && $chatId) {
            try {
                $bot->sendMessage($chatId, 'پیام شما دریافت شد.');
            } catch (\Throwable $e) {
                // ignore send failures
            }
        }
    });
}

$token = getWebhookToken();
if (!$token) {
    http_response_code(400);
    echo "ERROR: No token provided. Set RUBIKA_BOT_TOKEN env var or pass ?token=...\n";
    exit(1);
}

$bot = new Bot($token);
registerHandlers($bot);

if ((isset($_GET['set']) && $_GET['set'] === '1') || isset($_GET['url'])) {
    $url = isset($_GET['url']) ? $_GET['url'] : null;
    if (!$url) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? null);
        $path = $_SERVER['REQUEST_URI'] ?? '/hook.php';
        if ($host) {
            $url = $scheme . '://' . $host . $path;
        }
    }

    if ($url) {
        $type = isset($_GET['type']) && is_string($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : 'ReceiveUpdate';
        try {
            $res = $bot->updateWebhook($url, $type);
            header('Content-Type: application/json');
            echo json_encode(array('url' => $url, 'type' => $type, 'result' => $res), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo "updateWebhook failed: " . $e->getMessage();
        }
    } else {
        http_response_code(400);
        echo "No URL detected or provided for webhook set. Use ?url=https://yourhost/hook.php\n";
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        saveInvalidWebhookPayload($raw, json_last_error_msg());
        http_response_code(400);
        echo "Invalid JSON payload\n";
        exit(1);
    }

    saveUserMessage($data);

    try {
        $bot->handleWebhook($data);
        header('Content-Type: application/json');
        echo json_encode(array('ok' => true));
    } catch (\Throwable $e) {
        http_response_code(500);
        echo "Handler failed: " . $e->getMessage();
    }

    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$currentUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')) . ($_SERVER['REQUEST_URI'] ?? '/hook.php');
$envToken = getenv('RUBIKA_BOT_TOKEN') ?: '';
$defaultType = 'ReceiveUpdate';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Rubika webhook setup</title></head>
<body>
<h3>Rubika webhook endpoint</h3>
<form method="get">
    <label>Bot token: <input name="token" value="<?php echo htmlspecialchars($envToken, ENT_QUOTES); ?>" /></label><br/>
    <label>Webhook URL: <input name="url" value="<?php echo htmlspecialchars($currentUrl, ENT_QUOTES); ?>" size="60"/></label><br/>
    <label>Endpoint type:
        <select name="type">
            <option value="ReceiveUpdate" <?php echo $defaultType === 'ReceiveUpdate' ? 'selected' : ''; ?>>ReceiveUpdate</option>
            <option value="GetSelectionItem">GetSelectionItem</option>
            <option value="InlineMessage">InlineMessage</option>
        </select>
    </label><br/>
    <label><input type="checkbox" name="auto_reply" value="1" /> Auto-reply to new messages</label><br/>
    <input type="hidden" name="set" value="1" />
    <button type="submit">Set webhook</button>
</form>
<p>Or call with query params: <code>?set=1&amp;token=...&amp;url=...&amp;type=ReceiveUpdate</code></p>
</body>
</html>

