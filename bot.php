<?php
/**
 * Tronex Tracker Bot - Main Entry Point
 * 
 * Usage:
 *   php bot.php          - Runs the bot with long polling (default)
 *   php bot.php --events - Runs only the event monitor
 *   php bot.php --both   - Runs both bot and event monitor
 * 
 * This bot tracks user IDs on the Tronex smart contract deployed on BSC.
 * It shows registration info, slot activations, level updates, and income data.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/blockchain.php';
require_once __DIR__ . '/commands.php';
require_once __DIR__ . '/events.php';

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Parse command line arguments
$mode = 'bot'; // default
if (isset($argv[1])) {
    switch ($argv[1]) {
        case '--events':
            $mode = 'events';
            break;
        case '--both':
            $mode = 'both';
            break;
        default:
            $mode = 'bot';
    }
}

echo "========================================\n";
echo "  🚀 TRONEX TRACKER BOT v1.0\n";
echo "========================================\n";
echo "  Mode: $mode\n";
echo "  Contract: " . CONTRACT_ADDRESS . "\n";
echo "  BSC RPC: " . BSC_RPC_URL . "\n";
echo "========================================\n\n";

// Set up bot commands menu
TelegramAPI::setMyCommands();

// Initialize handlers
$commandHandler = new CommandHandler();
$eventMonitor = new EventMonitor();

// Main polling loop
$offset = 0;
$lastEventPoll = 0;

echo "[Bot] Starting long polling loop...\n";
echo "[Bot] Press Ctrl+C to stop.\n\n";

while (true) {
    try {
        // ==============================
        // TELEGRAM UPDATE POLLING
        // ==============================
        if ($mode === 'bot' || $mode === 'both') {
            $updates = TelegramAPI::getUpdates($offset, 5); // 5 second timeout for responsive event checking
            
            if ($updates !== null && is_array($updates)) {
                foreach ($updates as $update) {
                    $updateId = $update['update_id'];
                    $offset = $updateId + 1;
                    
                    echo "[Bot] Processing update #$updateId\n";
                    
                    try {
                        $commandHandler->handleUpdate($update);
                    } catch (Exception $e) {
                        echo "[Bot] Error processing update: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        // ==============================
        // BLOCKCHAIN EVENT POLLING
        // ==============================
        if ($mode === 'events' || $mode === 'both') {
            $now = time();
            if ($now - $lastEventPoll >= POLL_INTERVAL) {
                try {
                    $eventMonitor->pollEvents();
                } catch (Exception $e) {
                    echo "[Events] Error: " . $e->getMessage() . "\n";
                }
                $lastEventPoll = $now;
            }
        }
        
        // Small sleep if only doing events mode
        if ($mode === 'events') {
            sleep(1);
        }
        
    } catch (Exception $e) {
        echo "[Main] Fatal Error: " . $e->getMessage() . "\n";
        echo "[Main] Restarting in 5 seconds...\n";
        sleep(5);
    }
}
