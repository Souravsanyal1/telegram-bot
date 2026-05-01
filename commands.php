<?php
/**
 * Tronex Telegram Bot - Command Handler
 * =======================================
 * Handles all bot commands and user interactions.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Handle incoming Telegram update
 */
function handleUpdate($update) {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    }
}

/**
 * Handle text messages
 */
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = trim($message['text'] ?? '');
    $firstName = $message['from']['first_name'] ?? 'User';

    if (empty($text)) return;

    // Handle commands
    if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text);
        $command = strtolower($parts[0]);
        $args = array_slice($parts, 1);

        switch ($command) {
            case '/start':
                handleStartCommand($chatId, $firstName);
                break;
            case '/help':
                handleHelpCommand($chatId);
                break;
            case '/stats':
                handleStatsCommand($chatId);
                break;
            case '/user':
                handleUserCommand($chatId, $args);
                break;
            case '/wallet':
                handleWalletCommand($chatId, $args);
                break;
            case '/levels':
                handleLevelsCommand($chatId, $args);
                break;
            case '/income':
                handleIncomeCommand($chatId, $args);
                break;
            case '/subscribe':
                handleSubscribeCommand($chatId);
                break;
            case '/unsubscribe':
                handleUnsubscribeCommand($chatId);
                break;
            case '/prices':
                handlePricesCommand($chatId);
                break;
            case '/track':
                handleTrackCommand($chatId, $args);
                break;
            case '/untrack':
                handleUntrackCommand($chatId, $args);
                break;
            case '/mytracks':
                handleMyTracksCommand($chatId);
                break;
            default:
                sendMessage($chatId, "❓ Unknown command. Use /help to see available commands.");
                break;
        }
    }
}

/**
 * Handle callback query (inline button presses)
 */
function handleCallbackQuery($query) {
    $chatId = $query['message']['chat']['id'];
    $messageId = $query['message']['message_id'];
    $callbackId = $query['id'];
    $data = $query['data'];

    $parts = explode(':', $data);
    $action = $parts[0];

    switch ($action) {
        case 'user':
            $userId = (int)($parts[1] ?? 0);
            if ($userId > 0) {
                answerCallback($callbackId, "Loading user #$userId...");
                $text = buildUserInfoMessage($userId);
                editMessage($chatId, $messageId, $text);
            }
            break;

        case 'levels':
            $userId = (int)($parts[1] ?? 0);
            if ($userId > 0) {
                answerCallback($callbackId, "Loading levels...");
                $text = buildLevelsMessage($userId);
                editMessage($chatId, $messageId, $text);
            }
            break;

        case 'income':
            $userId = (int)($parts[1] ?? 0);
            if ($userId > 0) {
                answerCallback($callbackId, "Loading income...");
                $text = buildIncomeMessage($userId);
                editMessage($chatId, $messageId, $text);
            }
            break;

        case 'stats':
            answerCallback($callbackId, "Refreshing...");
            $text = buildStatsMessage();
            editMessage($chatId, $messageId, $text);
            break;

        case 'subscribe':
            addSubscriber($chatId);
            answerCallback($callbackId, "✅ Subscribed to live alerts!", true);
            editMessage($chatId, $messageId,
                "✅ <b>Subscribed!</b>\n\n"
                . "You will now receive live notifications for:\n"
                . "🆕 New registrations\n"
                . "📊 Level activations\n"
                . "💰 Income & bonuses\n"
                . "♻️ Matrix recycles\n\n"
                . "Use /unsubscribe to stop.");
            break;

        case 'unsubscribe':
            removeSubscriber($chatId);
            answerCallback($callbackId, "❌ Unsubscribed.", true);
            editMessage($chatId, $messageId,
                "❌ <b>Unsubscribed</b>\n\n"
                . "You will no longer receive live event notifications.\n"
                . "Use /subscribe to re-enable.");
            break;

        default:
            answerCallback($callbackId, "Unknown action");
            break;
    }
}

// ─────────────────────────────────────────────────────
// COMMAND HANDLERS
// ─────────────────────────────────────────────────────

function handleStartCommand($chatId, $firstName) {
    $isSubbed = isSubscribed($chatId);
    $subStatus = $isSubbed ? "✅ You are subscribed to live alerts" : "❌ Not subscribed to live alerts";

    $text = "🚀 <b>Welcome to Tronex Bot, $firstName!</b>\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
          . "I monitor the <b>Tronex Smart Contract</b> on BSC and provide real-time notifications.\n\n"
          . "📋 <b>What I can do:</b>\n\n"
          . "🆕 <b>Registration Alerts</b>\n"
          . "   New user registrations\n\n"
          . "📊 <b>Slot & Level Tracking</b>\n"
          . "   Which slot/level was activated\n\n"
          . "💰 <b>Income Tracking</b>\n"
          . "   Level-wise earnings & income\n\n"
          . "👥 <b>Team Tracking</b>\n"
          . "   Which level has new registrations\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "$subStatus\n\n"
          . "📌 <b>Contract:</b>\n"
          . "<code>" . CONTRACT_ADDRESS . "</code>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 Platform Stats', 'callback_data' => 'stats'],
                ['text' => '💲 Level Prices', 'callback_data' => 'prices'],
            ],
            [
                $isSubbed
                    ? ['text' => '🔕 Unsubscribe', 'callback_data' => 'unsubscribe']
                    : ['text' => '🔔 Subscribe Alerts', 'callback_data' => 'subscribe'],
            ],
        ],
    ];

    sendMessage($chatId, $text, 'HTML', $keyboard);
}

function handleHelpCommand($chatId) {
    $text = "📖 <b>Tronex Bot Commands</b>\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
          . "🔍 <b>Lookup Commands:</b>\n"
          . "/user <code>[ID]</code> — User info by ID\n"
          . "/wallet <code>[address]</code> — User info by wallet\n"
          . "/levels <code>[ID]</code> — User's level status\n"
          . "/income <code>[ID]</code> — User's income details\n\n"
          . "📊 <b>Platform Commands:</b>\n"
          . "/stats — Global platform statistics\n"
          . "/prices — Level prices table\n\n"
          . "🔔 <b>Alert Commands:</b>\n"
          . "/subscribe — Enable live event alerts (all)\n"
          . "/unsubscribe — Disable live alerts\n\n"
          . "🎯 <b>Track Specific IDs:</b>\n"
          . "/track <code>[ID]</code> — Track a specific user ID\n"
          . "/untrack <code>[ID]</code> — Stop tracking a user ID\n"
          . "/mytracks — Show all tracked IDs\n\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
          . "💡 <b>Examples:</b>\n"
          . "<code>/user 5</code>\n"
          . "<code>/track 42</code> — only get alerts for user #42\n"
          . "<code>/wallet 0x1234...abcd</code>\n"
          . "<code>/levels 10</code>\n"
          . "<code>/income 3</code>";

    sendMessage($chatId, $text);
}

function handleStatsCommand($chatId) {
    sendMessage($chatId, "⏳ Loading platform stats...");
    $text = buildStatsMessage();
    sendMessage($chatId, $text);
}

function handleUserCommand($chatId, $args) {
    if (empty($args) || !is_numeric($args[0])) {
        sendMessage($chatId, "⚠️ Usage: <code>/user [ID]</code>\nExample: <code>/user 5</code>");
        return;
    }

    $userId = (int)$args[0];
    sendMessage($chatId, "⏳ Loading user #$userId...");
    $text = buildUserInfoMessage($userId);

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 Levels', 'callback_data' => "levels:$userId"],
                ['text' => '💰 Income', 'callback_data' => "income:$userId"],
            ],
        ],
    ];

    sendMessage($chatId, $text, 'HTML', $keyboard);
}

function handleWalletCommand($chatId, $args) {
    if (empty($args) || strlen($args[0]) < 42) {
        sendMessage($chatId, "⚠️ Usage: <code>/wallet [address]</code>\nExample: <code>/wallet 0x1234...abcd</code>");
        return;
    }

    $address = $args[0];
    sendMessage($chatId, "⏳ Looking up wallet...");
    $user = getUserByAddress($address);

    if (!$user) {
        sendMessage($chatId, "❌ No user found with wallet:\n<code>$address</code>");
        return;
    }

    $text = buildUserInfoMessage($user['id']);

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 Levels', 'callback_data' => "levels:{$user['id']}"],
                ['text' => '💰 Income', 'callback_data' => "income:{$user['id']}"],
            ],
        ],
    ];

    sendMessage($chatId, $text, 'HTML', $keyboard);
}

function handleLevelsCommand($chatId, $args) {
    if (empty($args) || !is_numeric($args[0])) {
        sendMessage($chatId, "⚠️ Usage: <code>/levels [ID]</code>\nExample: <code>/levels 5</code>");
        return;
    }

    $userId = (int)$args[0];
    sendMessage($chatId, "⏳ Loading levels for user #$userId...");
    $text = buildLevelsMessage($userId);

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '👤 User Info', 'callback_data' => "user:$userId"],
                ['text' => '💰 Income', 'callback_data' => "income:$userId"],
            ],
        ],
    ];

    sendMessage($chatId, $text, 'HTML', $keyboard);
}

function handleIncomeCommand($chatId, $args) {
    if (empty($args) || !is_numeric($args[0])) {
        sendMessage($chatId, "⚠️ Usage: <code>/income [ID]</code>\nExample: <code>/income 5</code>");
        return;
    }

    $userId = (int)$args[0];
    sendMessage($chatId, "⏳ Loading income for user #$userId...");
    $text = buildIncomeMessage($userId);

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '👤 User Info', 'callback_data' => "user:$userId"],
                ['text' => '📊 Levels', 'callback_data' => "levels:$userId"],
            ],
        ],
    ];

    sendMessage($chatId, $text, 'HTML', $keyboard);
}

function handleSubscribeCommand($chatId) {
    if (isSubscribed($chatId)) {
        sendMessage($chatId, "✅ You are already subscribed to live alerts!\nUse /unsubscribe to stop.");
        return;
    }

    addSubscriber($chatId);
    $text = "✅ <b>Subscribed to Live Alerts!</b>\n\n"
          . "You will now receive notifications for:\n\n"
          . "🆕 New user registrations\n"
          . "📊 Level/Slot activations\n"
          . "💰 Direct & generation bonuses\n"
          . "♻️ Matrix recycles\n"
          . "⚠️ Missed commissions\n"
          . "🎁 Admin gifted slots\n\n"
          . "Use /unsubscribe to stop notifications.";
    sendMessage($chatId, $text);
}

function handleUnsubscribeCommand($chatId) {
    if (!isSubscribed($chatId)) {
        sendMessage($chatId, "❌ You are not subscribed.\nUse /subscribe to start receiving alerts.");
        return;
    }

    removeSubscriber($chatId);
    sendMessage($chatId, "🔕 <b>Unsubscribed from live alerts.</b>\n\nYou can re-subscribe anytime with /subscribe.");
}

function handlePricesCommand($chatId) {
    $text = "💲 <b>Tronex Level Prices</b>\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $totalInvestment = 0;
    foreach (LEVEL_PRICES as $level => $price) {
        $emoji = levelEmoji($level);
        $totalInvestment += $price;
        $text .= "$emoji Level <b>$level</b>  →  <b>$price USDT</b>\n";
    }

    $text .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
           . "💎 <b>Total (All 10 Levels): $totalInvestment USDT</b>";

    sendMessage($chatId, $text);
}

function handleTrackCommand($chatId, $args) {
    if (empty($args) || !is_numeric($args[0])) {
        sendMessage($chatId, "⚠️ Usage: <code>/track [ID]</code>\nExample: <code>/track 42</code>\n\nThis will send you alerts ONLY for that specific user ID.");
        return;
    }

    $userId = (int)$args[0];

    // Verify user exists
    $user = getUserInfo($userId);
    if (!$user) {
        sendMessage($chatId, "❌ User #$userId not found on the contract.");
        return;
    }

    if (addTrackedId($chatId, $userId)) {
        // Auto-subscribe if not already
        addSubscriber($chatId);

        $tracked = getTrackedIds($chatId);
        $count = count($tracked);

        $text = "🎯 <b>Now Tracking User #$userId!</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
              . "👤 Wallet: " . addressLink($user['wallet']) . "\n"
              . "📊 Active Levels: <b>{$user['activeLevelsCount']} / 10</b>\n"
              . "💰 Earnings: <b>{$user['totalEarnings']} USDT</b>\n\n"
              . "You will receive alerts when:\n"
              . "🆕 User #$userId registers someone\n"
              . "📊 User #$userId buys a level\n"
              . "💰 User #$userId receives bonus\n"
              . "♻️ User #$userId matrix recycles\n\n"
              . "📌 Total tracked IDs: <b>$count</b>\n"
              . "Use /mytracks to see all.";
        sendMessage($chatId, $text);
    } else {
        sendMessage($chatId, "ℹ️ You are already tracking user <b>#$userId</b>.\nUse /mytracks to see all tracked IDs.");
    }
}

function handleUntrackCommand($chatId, $args) {
    if (empty($args) || !is_numeric($args[0])) {
        sendMessage($chatId, "⚠️ Usage: <code>/untrack [ID]</code>\nExample: <code>/untrack 42</code>");
        return;
    }

    $userId = (int)$args[0];

    if (removeTrackedId($chatId, $userId)) {
        $remaining = getTrackedIds($chatId);
        $count = count($remaining);
        $text = "❌ <b>Stopped tracking User #$userId</b>\n\n";
        if ($count > 0) {
            $text .= "📌 Remaining tracked IDs: <b>$count</b>\nUse /mytracks to see all.";
        } else {
            $text .= "📌 No tracked IDs remaining.\nYou will now receive ALL event alerts (if subscribed).";
        }
        sendMessage($chatId, $text);
    } else {
        sendMessage($chatId, "ℹ️ You are not tracking user <b>#$userId</b>.\nUse /mytracks to see your tracked IDs.");
    }
}

function handleMyTracksCommand($chatId) {
    $tracked = getTrackedIds($chatId);

    if (empty($tracked)) {
        $text = "📋 <b>Your Tracked IDs</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
              . "ℹ️ You are not tracking any specific IDs.\n"
              . "You will receive ALL event alerts (if subscribed).\n\n"
              . "💡 Use <code>/track [ID]</code> to track a specific user.\n"
              . "Example: <code>/track 42</code>";
        sendMessage($chatId, $text);
        return;
    }

    $text = "🎯 <b>Your Tracked IDs</b> (" . count($tracked) . ")\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    foreach ($tracked as $userId) {
        $user = getUserInfo($userId);
        if ($user) {
            $text .= "🆔 <b>#$userId</b> — " . shortAddress($user['wallet'])
                   . " | Lv.{$user['activeLevelsCount']} | {$user['totalEarnings']}$\n";
        } else {
            $text .= "🆔 <b>#$userId</b> — <i>info unavailable</i>\n";
        }
    }

    $text .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
           . "➕ <code>/track [ID]</code> — add more\n"
           . "➖ <code>/untrack [ID]</code> — remove";

    sendMessage($chatId, $text);
}

// ─────────────────────────────────────────────────────
// MESSAGE BUILDERS
// ─────────────────────────────────────────────────────

function buildStatsMessage() {
    $stats = getGlobalStats();
    if (!$stats) {
        return "❌ Failed to fetch platform stats. Please try again.";
    }

    $lastUserId = getLastUserId();
    $pauseStatus = $stats['isPaused'] ? "🔴 Paused" : "🟢 Active";

    return "📊 <b>Tronex Platform Statistics</b>\n"
         . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
         . "👥 Total Users: <b>{$stats['totalUsers']}</b>\n"
         . "✅ Real Users: <b>{$stats['realUsers']}</b>\n"
         . "🆔 Last User ID: <b>$lastUserId</b>\n"
         . "📊 Status: <b>$pauseStatus</b>\n"
         . "👑 Admin: " . addressLink($stats['adminAddress']) . "\n\n"
         . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
         . "📌 Contract: " . addressLink(CONTRACT_ADDRESS) . "\n"
         . "🕐 Updated: " . date('Y-m-d H:i:s') . " UTC";
}

function buildUserInfoMessage($userId) {
    $user = getUserInfo($userId);
    if (!$user) {
        return "❌ User #$userId not found.";
    }

    $promoTag = $user['isPromo'] ? " 🏷️ <i>Promo</i>" : "";
    $realTag = $user['hasMadeRealPurchase'] ? "✅ Yes" : "❌ No";

    return "👤 <b>User #$userId Info</b>$promoTag\n"
         . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
         . "💼 Wallet: " . addressLink($user['wallet']) . "\n"
         . "🔗 Referrer ID: <code>#{$user['referrerId']}</code>\n"
         . "👥 Direct Referrals: <b>{$user['directReferralsCount']}</b>\n"
         . "📊 Active Levels: <b>{$user['activeLevelsCount']} / 10</b>\n"
         . "💰 Total Earnings: <b>{$user['totalEarnings']} USDT</b>\n"
         . "🏷️ Real Purchase: $realTag\n\n"
         . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
         . "🕐 Checked: " . date('H:i:s') . " UTC";
}

function buildLevelsMessage($userId) {
    $user = getUserInfo($userId);
    if (!$user) {
        return "❌ User #$userId not found.";
    }

    $levels = getUserAllLevelsState($userId);
    if (!$levels) {
        return "❌ Failed to fetch levels for user #$userId.";
    }

    $text = "📊 <b>Levels Status — User #$userId</b>\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    $maxActiveLevel = 0;
    $totalLevelEarnings = '0';

    foreach ($levels as $level => $info) {
        $emoji = levelEmoji($level);
        $price = LEVEL_PRICES[$level];

        if ($info['unlocked']) {
            $maxActiveLevel = $level;
            $status = $info['isReal'] ? "✅ REAL" : "🎁 PROMO";
            if ($info['isRefunded']) {
                $status .= " 🔄 Refunded";
            }
        } else {
            $status = "🔒 Locked";
        }

        $earnings = $info['earnings'];
        $totalLevelEarnings = bcadd($totalLevelEarnings, $earnings, 2);

        $text .= "$emoji Lv.<b>$level</b> ($price$) → $status";
        if ($info['unlocked'] && $earnings > 0) {
            $text .= " | 💰 $earnings$";
        }
        $text .= "\n";
    }

    $text .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
           . "📊 Max Level: <b>$maxActiveLevel / 10</b>\n"
           . "💰 Total Level Earnings: <b>$totalLevelEarnings USDT</b>";

    return $text;
}

function buildIncomeMessage($userId) {
    $user = getUserInfo($userId);
    if (!$user) {
        return "❌ User #$userId not found.";
    }

    $levels = getUserAllLevelsState($userId);
    if (!$levels) {
        return "❌ Failed to fetch income for user #$userId.";
    }

    $text = "💰 <b>Income Report — User #$userId</b>\n"
          . "━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
          . "💼 <b>Total Earnings: {$user['totalEarnings']} USDT</b>\n\n"
          . "📊 <b>Level-wise Breakdown:</b>\n\n";

    $hasIncome = false;
    $maxIncomeLevel = 0;

    foreach ($levels as $level => $info) {
        $emoji = levelEmoji($level);
        $price = LEVEL_PRICES[$level];
        $earnings = $info['earnings'];

        if ($earnings > 0) {
            $hasIncome = true;
            $maxIncomeLevel = $level;
            $roi = ($price > 0) ? number_format(($earnings / $price) * 100, 1) : '0';
            $text .= "$emoji Lv.<b>$level</b>: <b>$earnings USDT</b> (ROI: {$roi}%)\n";
        }
    }

    if (!$hasIncome) {
        $text .= "ℹ️ <i>No income recorded yet</i>\n";
    }

    $text .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if ($maxIncomeLevel > 0) {
        $text .= "📈 Income up to Level: <b>$maxIncomeLevel</b>\n";
    }

    // Show which levels have new registrations (direct referrals)
    $text .= "👥 Direct Referrals: <b>{$user['directReferralsCount']}</b>\n";
    $text .= "📊 Active Levels: <b>{$user['activeLevelsCount']} / 10</b>\n";
    $text .= "🕐 Checked: " . date('H:i:s') . " UTC";

    return $text;
}
