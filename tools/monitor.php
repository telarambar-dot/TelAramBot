<?php
// CLI monitor for incoming Rubika requests log (req.txt)

function usage()
{
    echo "Usage: php tools/monitor.php [--file=path] [--lines=N] [--follow] [--chat=CHAT_ID]\n";
    echo "  --file=path   Path to req.txt (default: req.txt in project root)\n";
    echo "  --lines=N     Show last N entries then exit (default: 10)\n";
    echo "  --follow      Follow new entries (like tail -f)\n";
    echo "  --chat=ID     Filter by chat_id (only show entries for this chat)\n";
    echo "  --no-color    Disable ANSI colors in output\n";
    exit(1);
}

$opts = array();
foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $opts['file'] = substr($arg, 7);
    } elseif (strpos($arg, '--lines=') === 0) {
        $opts['lines'] = (int) substr($arg, 8);
    } elseif ($arg === '--follow' || $arg === '-f') {
        $opts['follow'] = true;
    } elseif (strpos($arg, '--chat=') === 0) {
        $opts['chat'] = substr($arg, 7);
    } elseif ($arg === '--no-color') {
        $opts['no-color'] = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        usage();
    }
}

$file = isset($opts['file']) ? $opts['file'] : __DIR__ . '/../req.txt';
$lines = isset($opts['lines']) ? max(0, (int)$opts['lines']) : 10;
$follow = !empty($opts['follow']);
$chatFilter = isset($opts['chat']) ? $opts['chat'] : null;
$useColor = !isset($opts['no-color']);

if (!is_file($file)) {
    fwrite(STDERR, "Log file not found: {$file}\n");
    exit(1);
}

function readLastLines($filePath, $numLines)
{
    if ($numLines <= 0) {
        return array();
    }

    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return array();
    }

    if ($numLines >= count($lines)) {
        return $lines;
    }

    return array_slice($lines, -$numLines);
}

function colorize($text, $colorCode)
{
    return "\033[{$colorCode}m{$text}\033[0m";
}

function processLine($line, $chatFilter = null, $useColor = true)
{
    $line = trim($line);
    if ($line === '') return;

    $obj = json_decode($line, true);
    if (!is_array($obj)) {
        echo "RAW: {$line}\n";
        return;
    }

    $timestamp = isset($obj['timestamp']) ? $obj['timestamp'] : null;
    $request = isset($obj['request']) ? $obj['request'] : $obj;

    // Try to extract common fields
    $type = $request['type'] ?? ($request['update']['type'] ?? ($request['inline_message']['type'] ?? ''));
    $chatId = $request['chat_id'] ?? ($request['update']['chat_id'] ?? ($request['inline_message']['chat_id'] ?? null));
    $text = '';
    if (isset($request['new_message']['text'])) {
        $text = $request['new_message']['text'];
    } elseif (isset($request['inline_message']['text'])) {
        $text = $request['inline_message']['text'];
    } elseif (isset($request['text'])) {
        $text = $request['text'];
    }

    if ($chatFilter !== null && (string)$chatFilter !== (string)$chatId) {
        return;
    }

    $stamp = $timestamp ? $timestamp : date('c');
    $stampOut = $useColor ? colorize($stamp, '32') : $stamp; // green
    $chatOut = $useColor ? colorize($chatId, '36') : $chatId; // cyan
    $typeOut = $useColor ? colorize($type, '33') : $type; // yellow
    echo "[{$stampOut}] chat_id={$chatOut} type={$typeOut}\n";
    if ($text !== '') {
        $textOut = $useColor ? colorize($text, '35') : $text; // magenta
        echo "  text: {$textOut}\n";
    }
    $rawOut = $useColor ? colorize(json_encode($request, JSON_UNESCAPED_UNICODE), '90') : json_encode($request, JSON_UNESCAPED_UNICODE); // bright black
    echo "  raw: {$rawOut}\n";
    echo "---\n";
}

// Print last lines
$initial = readLastLines($file, $lines);
foreach ($initial as $l) {
    processLine($l, $chatFilter, $useColor);
}

if ($follow) {
    $fh = fopen($file, 'r');
    if (!$fh) {
        fwrite(STDERR, "Failed to open file for follow: {$file}\n");
        exit(1);
    }
    // Seek to end
    fseek($fh, 0, SEEK_END);
    while (!feof($fh)) {
        $line = fgets($fh);
        if ($line !== false) {
            processLine($line, $chatFilter, $useColor);
        } else {
            clearstatcache(false, $file);
            usleep(200000); // 200ms
        }
    }
    fclose($fh);
}

exit(0);
