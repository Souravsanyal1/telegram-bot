<?php
/**
 * ╔══════════════════════════════════════════════════╗
 * ║        TRONEX TELEGRAM BOT v1.0                 ║
 * ║   BSC Smart Contract Event Monitor              ║
 * ║                                                 ║
 * ║   Features:                                     ║
 * ║   • New Registration Alerts                     ║
 * ║   • Slot/Level Activation Messages              ║
 * ║   • Level Update Notifications                  ║
 * ║   • Income Tracking per Level                   ║
 * ║   • Team Registration Tracking                  ║
 * ║   • Real-time Blockchain Event Monitoring       ║
 * ╚══════════════════════════════════════════════════╝
 *
 * Usage: php bot.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/commands.php';
require_once __DIR__ . '/events.php';

// ─────────────────────────────────────────────────────
// MAIN BOT LOOP
// ─────────────────────────────────────────────────────

echo "╔══════════════════════════════════════════════════╗\n";
echo "║        TRONEX TELEGRAM BOT v1.0                 ║\n";
echo "║   BSC Smart Contract Event Monitor              ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

// Verify bot token by getting bot info
echo "[*] Verifying bot token...\n";
$botInfo = telegramRequest('getMe', []);
if (!$botInfo || !$botInfo['ok']) {
    echo "[!] FATAL: Invalid bot token! Check config.php\n";
    exit(1);
}
$botName = $botInfo['result']['username'] ?? 'Unknown';
echo "[✓] Bot connected: @$botName\n";

// Verify BSC RPC connection
echo "[*] Testing BSC RPC connection...\n";
$blockNumber = getBlockNumber();
if ($blockNumber == 0) {
    echo "[!] WARNING: Could not connect to BSC RPC!\n";
} else {
    echo "[✓] BSC RPC connected. Current block: $blockNumber\n";
}

// Load current state
$lastBlock = getLastBlock();
$subscribers = getSubscribers();
echo "[✓] Last processed block: " . ($lastBlock ?: 'None (will start fresh)') . "\n";
echo "[✓] Active subscribers: " . count($subscribers) . "\n";

// Remove command menu from Telegram
telegramRequest('deleteMyCommands', []);
echo "[✓] Command menu removed\n\n";

echo "[*] Starting main loop (Ctrl+C to stop)...\n\n";

// ── Start HTTP health check server for Render ──
$healthServer = null;
$port = HEALTH_PORT;
$healthServer = @stream_socket_server("tcp://0.0.0.0:$port", $errno, $errstr);
if ($healthServer) {
    stream_set_blocking($healthServer, false);
    echo "[✓] Health check server listening on port $port\n";
} else {
    echo "[!] Could not start health server on port $port: $errstr\n";
}

$offset = 0;
$lastEventCheck = 0;

// Main polling loop
while (true) {
    try {
        // ── 0. Handle health check requests (non-blocking) ──
        if ($healthServer) {
            $client = @stream_socket_accept($healthServer, 0);
            if ($client) {
                $stats = getGlobalStats();
                $totalUsers = $stats ? $stats['totalUsers'] : '?';
                $body = json_encode([
                    'status' => 'ok',
                    'bot' => '@' . ($botName ?? 'tronex_bot'),
                    'totalUsers' => $totalUsers,
                    'lastBlock' => getLastBlock(),
                    'subscribers' => count(getSubscribers()),
                    'uptime' => time(),
                ]);
                $response = "HTTP/1.1 200 OK\r\n"
                          . "Content-Type: application/json\r\n"
                          . "Content-Length: " . strlen($body) . "\r\n"
                          . "Connection: close\r\n\r\n"
                          . $body;
                @fwrite($client, $response);
                @fclose($client);
            }
        }

        // ── 1. Process Telegram messages (long-poll with short timeout) ──
        $updates = getUpdates($offset, 2);

        foreach ($updates as $update) {
            $updateId = $update['update_id'];
            $offset = $updateId + 1;

            try {
                handleUpdate($update);
            } catch (Exception $e) {
                logError("Error handling update: " . $e->getMessage());
            }
        }

        // ── 2. Check blockchain events periodically ──
        $now = time();
        if ($now - $lastEventCheck >= EVENT_CHECK_INTERVAL) {
            $lastEventCheck = $now;
            try {
                processEvents();
            } catch (Exception $e) {
                logError("Error processing events: " . $e->getMessage());
            }
        }

    } catch (Exception $e) {
        logError("Main loop error: " . $e->getMessage());
        sleep(5);
    }
}
