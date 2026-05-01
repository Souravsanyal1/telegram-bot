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

// Set up commands menu in Telegram
$commands = [
    ['command' => 'start', 'description' => '🚀 Start the bot'],
    ['command' => 'help', 'description' => '📖 Show all commands'],
    ['command' => 'stats', 'description' => '📊 Platform statistics'],
    ['command' => 'user', 'description' => '👤 Lookup user by ID'],
    ['command' => 'wallet', 'description' => '💼 Lookup user by wallet'],
    ['command' => 'levels', 'description' => '📊 User level status'],
    ['command' => 'income', 'description' => '💰 User income report'],
    ['command' => 'subscribe', 'description' => '🔔 Enable live alerts'],
    ['command' => 'unsubscribe', 'description' => '🔕 Disable live alerts'],
    ['command' => 'prices', 'description' => '💲 Level prices table'],
    ['command' => 'track', 'description' => '🎯 Track specific user ID'],
    ['command' => 'untrack', 'description' => '❌ Stop tracking a user ID'],
    ['command' => 'mytracks', 'description' => '📋 Show tracked IDs'],
];
telegramRequest('setMyCommands', ['commands' => json_encode($commands)]);
echo "[✓] Bot commands menu updated\n\n";

echo "[*] Starting main loop (Ctrl+C to stop)...\n\n";

$offset = 0;
$lastEventCheck = 0;

// Main polling loop
while (true) {
    try {
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
