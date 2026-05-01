<?php
/**
 * Tronex Telegram Bot - Event Monitor
 * =====================================
 * Monitors BSC blockchain events and sends notifications.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Process new blockchain events and notify subscribers
 */
function processEvents() {
    $currentBlock = getBlockNumber();
    $lastBlock = getLastBlock();

    if ($lastBlock == 0) {
        // First run: start from current block (only monitor new events)
        $lastBlock = $currentBlock;
        saveLastBlock($lastBlock);
        logInfo("First run. Starting from current block $lastBlock");
        return;
    }

    if ($currentBlock <= $lastBlock) {
        return;
    }

    // Process in small chunks (10 blocks) to avoid free RPC rate limits
    $fromBlock = $lastBlock + 1;
    $toBlock = min($currentBlock, $fromBlock + 9);

    // Filter for only the events we care about
    $eventTopics = [
        [
            EVENT_REGISTRATION,
            EVENT_LEVEL_BOUGHT,
            EVENT_VIRTUAL_SLOT,
            EVENT_DIRECT_BONUS,
            EVENT_GENERATION_BONUS,
            EVENT_MISSED_COMMISSION,
            EVENT_MATRIX_RECYCLED,
            EVENT_ADMIN_GIFTED,
            EVENT_DIRECT_REFERRAL,
        ]
    ];

    $logs = getLogs($fromBlock, $toBlock, $eventTopics);

    if ($logs === null) {
        // RPC failure — skip these blocks and move on to avoid getting stuck
        logError("Failed to fetch logs from block $fromBlock to $toBlock (skipping)");
        saveLastBlock($toBlock);
        return;
    }

    if (is_array($logs) && count($logs) > 0) {
        logInfo("Processing " . count($logs) . " events from block $fromBlock to $toBlock");

        foreach ($logs as $log) {
            processEventLog($log);
        }
    }

    saveLastBlock($toBlock);
}

/**
 * Process a single event log and send notification
 */
function processEventLog($log) {
    if (!isset($log['topics']) || empty($log['topics'])) return;

    $eventTopic = $log['topics'][0];
    $txHash = $log['transactionHash'] ?? 'Unknown';
    $data = $log['data'] ?? '0x';
    $dataHex = substr($data, 2);

    $message = null;
    $involvedUserIds = []; // Tronex user IDs involved in this event

    switch ($eventTopic) {
        case EVENT_REGISTRATION:
            $message = handleRegistrationEvent($log, $dataHex, $txHash);
            // Extract userId and referrerId
            $involvedUserIds[] = hexdec(substr($dataHex, 0, 64));
            $involvedUserIds[] = hexdec(substr($dataHex, 64, 64));
            break;

        case EVENT_LEVEL_BOUGHT:
            $message = handleLevelBoughtEvent($log, $dataHex, $txHash);
            // Resolve wallet to userId
            $addr = '0x' . substr($log['topics'][1], 26);
            $u = getUserByAddress($addr);
            if ($u) $involvedUserIds[] = $u['id'];
            break;

        case EVENT_VIRTUAL_SLOT:
            $message = handleVirtualSlotEvent($log, $dataHex, $txHash);
            $addr = '0x' . substr($log['topics'][1], 26);
            $u = getUserByAddress($addr);
            if ($u) $involvedUserIds[] = $u['id'];
            break;

        case EVENT_DIRECT_BONUS:
            $message = handleDirectBonusEvent($log, $dataHex, $txHash);
            // Receiver and buyer wallets
            $recv = '0x' . substr($log['topics'][1], 26);
            $buyer = '0x' . substr($log['topics'][2], 26);
            $u1 = getUserByAddress($recv);
            $u2 = getUserByAddress($buyer);
            if ($u1) $involvedUserIds[] = $u1['id'];
            if ($u2) $involvedUserIds[] = $u2['id'];
            break;

        case EVENT_GENERATION_BONUS:
            $message = handleGenerationBonusEvent($log, $dataHex, $txHash);
            $recv = '0x' . substr($log['topics'][1], 26);
            $buyer = '0x' . substr($log['topics'][2], 26);
            $u1 = getUserByAddress($recv);
            $u2 = getUserByAddress($buyer);
            if ($u1) $involvedUserIds[] = $u1['id'];
            if ($u2) $involvedUserIds[] = $u2['id'];
            break;

        case EVENT_MISSED_COMMISSION:
            $message = handleMissedCommissionEvent($log, $dataHex, $txHash);
            $involvedUserIds[] = hexdec($log['topics'][1]);
            break;

        case EVENT_MATRIX_RECYCLED:
            $message = handleMatrixRecycledEvent($log, $dataHex, $txHash);
            $involvedUserIds[] = hexdec($log['topics'][1]);
            break;

        case EVENT_ADMIN_GIFTED:
            $message = handleAdminGiftedEvent($log, $dataHex, $txHash);
            $involvedUserIds[] = hexdec($log['topics'][1]);
            break;

        case EVENT_DIRECT_REFERRAL:
            $message = handleDirectReferralEvent($log, $dataHex, $txHash);
            $involvedUserIds[] = hexdec($log['topics'][1]); // sponsorId
            $involvedUserIds[] = hexdec($log['topics'][2]); // newUserId
            break;
    }

    if ($message) {
        smartBroadcast($message, $involvedUserIds);
    }
}

// ─────────────────────────────────────────────────────
// EVENT HANDLERS
// ─────────────────────────────────────────────────────

/**
 * Registration(address indexed user, address indexed referrer, uint256 userId, uint256 referrerId)
 */
function handleRegistrationEvent($log, $dataHex, $txHash) {
    $userAddr = '0x' . substr($log['topics'][1], 26);
    $referrerAddr = '0x' . substr($log['topics'][2], 26);
    $userId = hexdec(substr($dataHex, 0, 64));
    $referrerId = hexdec(substr($dataHex, 64, 64));

    // Get total users count
    $stats = getGlobalStats();
    $totalUsers = $stats ? $stats['totalUsers'] : $userId;

    return "🆕 <b>NEW REGISTRATION!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "👤 User: " . addressLink($userAddr) . "\n"
         . "🆔 User ID: <code>#$userId</code>\n"
         . "👥 Referrer: " . addressLink($referrerAddr) . "\n"
         . "🔗 Referrer ID: <code>#$referrerId</code>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "📊 Total Users: <b>$totalUsers</b>\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * LevelBought(address indexed user, uint256 level, uint256 amount)
 */
function handleLevelBoughtEvent($log, $dataHex, $txHash) {
    $userAddr = '0x' . substr($log['topics'][1], 26);
    $level = hexdec(substr($dataHex, 0, 64));
    $amount = formatUSDT(substr($dataHex, 64, 64));
    $emoji = levelEmoji($level);
    $price = LEVEL_PRICES[$level] ?? $amount;

    // Try to get user info
    $userInfo = getUserByAddress($userAddr);
    $userId = $userInfo ? $userInfo['id'] : '?';
    $activeLevels = $userInfo ? $userInfo['activeLevelsCount'] : $level;

    return "$emoji <b>LEVEL $level ACTIVATED!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "👤 User: " . addressLink($userAddr) . "\n"
         . "🆔 User ID: <code>#$userId</code>\n"
         . "$emoji Level: <b>$level</b> ($price USDT)\n"
         . "📊 Active Levels: <b>$activeLevels / 10</b>\n"
         . "💰 Amount: <b>$amount USDT</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * VirtualSlotActivated(address indexed user, uint256 level, uint256 amount)
 */
function handleVirtualSlotEvent($log, $dataHex, $txHash) {
    $userAddr = '0x' . substr($log['topics'][1], 26);
    $level = hexdec(substr($dataHex, 0, 64));
    $amount = formatUSDT(substr($dataHex, 64, 64));
    $emoji = levelEmoji($level);
    $price = LEVEL_PRICES[$level] ?? $amount;

    return "⚡ <b>SLOT ACTIVATED (Real Upgrade)!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "👤 User: " . addressLink($userAddr) . "\n"
         . "$emoji Level: <b>$level</b> ($price USDT)\n"
         . "💰 Amount: <b>$amount USDT</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * DirectBonusPaid(address indexed receiver, address indexed buyer, uint256 amount)
 */
function handleDirectBonusEvent($log, $dataHex, $txHash) {
    $receiverAddr = '0x' . substr($log['topics'][1], 26);
    $buyerAddr = '0x' . substr($log['topics'][2], 26);
    $amount = formatUSDT(substr($dataHex, 0, 64));

    return "💵 <b>DIRECT BONUS PAID!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🎁 Receiver: " . addressLink($receiverAddr) . "\n"
         . "🛒 From: " . addressLink($buyerAddr) . "\n"
         . "💰 Amount: <b>$amount USDT</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * GenerationBonusPaid(address indexed receiver, address indexed buyer, uint256 level, uint256 amount)
 */
function handleGenerationBonusEvent($log, $dataHex, $txHash) {
    $receiverAddr = '0x' . substr($log['topics'][1], 26);
    $buyerAddr = '0x' . substr($log['topics'][2], 26);
    $genLevel = hexdec(substr($dataHex, 0, 64));
    $amount = formatUSDT(substr($dataHex, 64, 64));

    return "💎 <b>GENERATION BONUS PAID!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🎁 Receiver: " . addressLink($receiverAddr) . "\n"
         . "🛒 From: " . addressLink($buyerAddr) . "\n"
         . "📊 Generation: <b>$genLevel</b>\n"
         . "💰 Amount: <b>$amount USDT</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * MissedCommission(uint256 indexed receiverId, uint256 level, uint256 amount)
 */
function handleMissedCommissionEvent($log, $dataHex, $txHash) {
    $receiverId = hexdec($log['topics'][1]);
    $level = hexdec(substr($dataHex, 0, 64));
    $amount = formatUSDT(substr($dataHex, 64, 64));
    $emoji = levelEmoji($level);

    return "⚠️ <b>MISSED COMMISSION!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🆔 User ID: <code>#$receiverId</code>\n"
         . "$emoji Level: <b>$level</b>\n"
         . "💸 Missed: <b>$amount USDT</b>\n"
         . "ℹ️ <i>User doesn't own this level</i>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * MatrixRecycled(uint256 indexed userId, uint256 level, uint256 recycleCount)
 */
function handleMatrixRecycledEvent($log, $dataHex, $txHash) {
    $userId = hexdec($log['topics'][1]);
    $level = hexdec(substr($dataHex, 0, 64));
    $recycleCount = hexdec(substr($dataHex, 64, 64));
    $emoji = levelEmoji($level);

    return "♻️ <b>MATRIX RECYCLED!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🆔 User ID: <code>#$userId</code>\n"
         . "$emoji Level: <b>$level</b>\n"
         . "🔄 Recycle #: <b>$recycleCount</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * AdminGiftedSlot(uint256 indexed userId, uint256 level)
 */
function handleAdminGiftedEvent($log, $dataHex, $txHash) {
    $userId = hexdec($log['topics'][1]);
    $level = hexdec(substr($dataHex, 0, 64));
    $emoji = levelEmoji($level);

    return "🎁 <b>ADMIN GIFTED SLOT!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🆔 User ID: <code>#$userId</code>\n"
         . "$emoji Level: <b>$level</b> (Promo)\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

/**
 * DirectReferral(uint256 indexed sponsorId, uint256 indexed newUserId, uint256 timestamp)
 */
function handleDirectReferralEvent($log, $dataHex, $txHash) {
    $sponsorId = hexdec($log['topics'][1]);
    $newUserId = hexdec($log['topics'][2]);

    // Get sponsor info to show team count
    $sponsorInfo = getUserInfo($sponsorId);
    $teamCount = $sponsorInfo ? $sponsorInfo['directReferralsCount'] : '?';

    return "👥 <b>NEW TEAM MEMBER!</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Sponsor ID: <code>#$sponsorId</code>\n"
         . "🆕 New User ID: <code>#$newUserId</code>\n"
         . "👥 Sponsor's Team: <b>$teamCount members</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🔗 Tx: " . txLink($txHash);
}

// ─────────────────────────────────────────────────────
// SMART BROADCAST
// ─────────────────────────────────────────────────────

/**
 * Smart broadcast: sends message only to chats that are tracking the involved user IDs.
 * If a chat has NO tracked IDs, they receive ALL events (global subscriber).
 * If a chat HAS tracked IDs, they ONLY receive events involving those specific IDs.
 *
 * @param string $message The formatted message to send
 * @param array $involvedUserIds Tronex user IDs involved in this event
 */
function smartBroadcast($message, $involvedUserIds = []) {
    $subscribers = getSubscribers();

    foreach ($subscribers as $chatId) {
        $trackedIds = getTrackedIds($chatId);

        if (empty($trackedIds)) {
            // No specific tracking — send ALL events
            sendMessage($chatId, $message);
        } else {
            // Has tracked IDs — only send if event involves a tracked ID
            foreach ($involvedUserIds as $uid) {
                if (in_array((int)$uid, $trackedIds)) {
                    sendMessage($chatId, $message);
                    break; // send once per chat even if multiple IDs match
                }
            }
        }

        usleep(100000); // 100ms delay between messages to avoid rate limits
    }
}
