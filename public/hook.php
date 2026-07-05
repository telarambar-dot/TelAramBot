<?php
// Webhook endpoint and registration helper for Rubika SDK

require_once __DIR__ . '/../src/bootstrap.php';

// Ensure enum definitions are loaded before Dispatcher uses them
require_once __DIR__ . '/../src/Enums/Enums.php';

use RubikaBot\Bot;

    // Token can come from environment or ?token= query
    $token = getenv('RUBIKA_BOT_TOKEN') ?: (isset($_GET['token']) ? $_GET['token'] : null);
    if (!$token) {
        http_response_code(400);
        echo "ERROR: No token provided. Set RUBIKA_BOT_TOKEN env var or pass ?token=...\n";
        exit(1);
    }

    $bot = new Bot($token);

    // Register simple handlers to "manage" incoming messages
    $bot->dispatcher()->onNewMessage(function($update) use ($bot) {
        $chatId = $update->chat_id ?? ($update->new_message->chat_id ?? null);
        $text = isset($update->new_message) ? ($update->new_message->text ?? '') : '';
        $senderFirstName = isset($update->new_message) ? ($update->new_message->first_name ?? null) : null;
        $senderLastName = isset($update->new_message) ? ($update->new_message->last_name ?? null) : null;

        // Save to a per-message log
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $entry = array('time' => time(), 'chat_id' => $chatId, 'text' => $text);
        @file_put_contents($logDir . '/messages.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

        if ($text === '/start' && $chatId) {
            $name = trim(($senderFirstName ? $senderFirstName : '') . ' ' . ($senderLastName ? $senderLastName : ''));
            if (!$name) {
                try {
                    $chatInfo = $bot->getChat(array('chat_id' => $chatId));
                    if (isset($chatInfo['data']['first_name'])) {
                        $name = $chatInfo['data']['first_name'];
                        if (!empty($chatInfo['data']['last_name'])) {
                            $name .= ' ' . $chatInfo['data']['last_name'];
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore getChat failures
                }
            }
            if (!$name) {
                $name = 'دوست گرامی';
            }

            try {
                $bot->sendMessage($chatId, "سلام {$name}! خوش آمدیدی به ربات ما.");
            } catch (\Throwable $e) {
                // ignore send failures
            }
        }

        // Optional auto-reply if ?auto_reply=1 is set
        if (isset($_GET['auto_reply']) && $_GET['auto_reply'] == '1' && $chatId) {
            try {
                $bot->sendMessage($chatId, 'پیام شما دریافت شد.');
            } catch (\Throwable $e) {
                // ignore send failures
            }
        }
    });

    // Helper: set webhook when ?set=1 or ?url=... provided
    if ((isset($_GET['set']) && $_GET['set'] == '1') || isset($_GET['url'])) {
        $url = isset($_GET['url']) ? $_GET['url'] : null;
        if (!$url) {
            // Try to auto-detect public URL of this script
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? null);
            $path = $_SERVER['REQUEST_URI'] ?? '/hook.php';
            if ($host) {
                $url = $scheme . '://' . $host . $path;
            }
        }

        if ($url) {
            // Allow overriding endpoint type via ?type= param (defaults to ReceiveUpdate)
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

    // Handle incoming webhook POSTs
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo "Invalid JSON payload\n";
            exit(1);
        }

        try {
            $bot->handleWebhook($data);
            // Respond 200 OK to Rubika
            header('Content-Type: application/json');
            echo json_encode(array('ok' => true));
        } catch (\Throwable $e) {
            http_response_code(500);
            echo "Handler failed: " . $e->getMessage();
        }

        exit;
    }

    // Non-POST informational page: render simple HTML form for convenience
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
    <?php
