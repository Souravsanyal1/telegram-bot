<?php
/**
 * Tronex Telegram Bot - Helper Functions
 * =======================================
 * BSC RPC calls, Telegram API, formatting utilities.
 */

require_once __DIR__ . '/config.php';

// ─────────────────────────────────────────────────────
// TELEGRAM API HELPERS
// ─────────────────────────────────────────────────────

/**
 * Send a message via Telegram Bot API
 */
function sendMessage($chatId, $text, $parseMode = 'HTML', $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }
    return telegramRequest('sendMessage', $params);
}

/**
 * Edit an existing message
 */
function editMessage($chatId, $messageId, $text, $parseMode = 'HTML', $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    if ($replyMarkup) {
        $params['reply_markup'] = json_encode($replyMarkup);
    }
    return telegramRequest('editMessageText', $params);
}

/**
 * Answer callback query
 */
function answerCallback($callbackId, $text = '', $showAlert = false) {
    return telegramRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $showAlert,
    ]);
}

/**
 * Generic Telegram API request
 */
function telegramRequest($method, $params) {
    $url = TELEGRAM_API . '/' . $method;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        logError("Telegram API Error ($method): $error");
        return null;
    }

    $result = json_decode($response, true);
    if (!$result || !$result['ok']) {
        logError("Telegram API Failed ($method): " . ($response ?: 'empty response'));
    }
    return $result;
}

/**
 * Get updates from Telegram (long polling)
 */
function getUpdates($offset = 0, $timeout = 30) {
    $url = TELEGRAM_API . '/getUpdates';
    $params = [
        'offset' => $offset,
        'timeout' => $timeout,
        'allowed_updates' => json_encode(['message', 'callback_query']),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?' . http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout + 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return ($result && $result['ok']) ? $result['result'] : [];
}

// ─────────────────────────────────────────────────────
// BSC RPC HELPERS
// ─────────────────────────────────────────────────────

/**
 * Make an eth_call to the BSC RPC
 */
function ethCall($data, $to = CONTRACT_ADDRESS) {
    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'eth_call',
        'params' => [
            ['to' => $to, 'data' => $data],
            'latest',
        ],
        'id' => 1,
    ];
    return rpcRequest($payload);
}

/**
 * Get current block number
 */
function getBlockNumber() {
    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'eth_blockNumber',
        'params' => [],
        'id' => 1,
    ];
    $result = rpcRequest($payload);
    return $result ? hexdec($result) : 0;
}

/**
 * Get logs for contract events
 */
function getLogs($fromBlock, $toBlock, $topics = []) {
    $params = [
        'fromBlock' => '0x' . dechex($fromBlock),
        'toBlock' => '0x' . dechex($toBlock),
        'address' => CONTRACT_ADDRESS,
    ];
    if (!empty($topics)) {
        $params['topics'] = $topics;
    }

    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'eth_getLogs',
        'params' => [$params],
        'id' => 1,
    ];
    return rpcRequest($payload);
}

/**
 * Get transaction receipt
 */
function getTransactionReceipt($txHash) {
    $payload = [
        'jsonrpc' => '2.0',
        'method' => 'eth_getTransactionReceipt',
        'params' => [$txHash],
        'id' => 1,
    ];
    return rpcRequest($payload);
}

/**
 * Generic BSC RPC request with round-robin failover
 */
function rpcRequest($payload) {
    static $rpcIndex = 0;
    $urls = BSC_RPC_URLS;
    $totalUrls = count($urls);
    $attempts = min($totalUrls, 3); // try up to 3 different endpoints

    for ($i = 0; $i < $attempts; $i++) {
        $url = $urls[($rpcIndex + $i) % $totalUrls];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!$error && $response) {
            $result = json_decode($response, true);
            if ($result && isset($result['result'])) {
                $rpcIndex = ($rpcIndex + $i + 1) % $totalUrls; // rotate to next
                return $result['result'];
            }
            if ($result && isset($result['error'])) {
                // Rate limited — try next endpoint
                usleep(300000); // 300ms
                continue;
            }
        }
    }

    $rpcIndex = ($rpcIndex + 1) % $totalUrls; // rotate anyway
    return null;
}

// ─────────────────────────────────────────────────────
// CONTRACT READ FUNCTIONS
// ─────────────────────────────────────────────────────

/**
 * Get global stats from contract
 * Returns: totalUsers, realUsers, adminAddress, isPaused
 */
function getGlobalStats() {
    // getGlobalStats() => selector: 0x6b4169c3
    $data = '0x6b4169c3';
    $result = ethCall($data);
    if (!$result || strlen($result) < 258) return null;

    $hex = substr($result, 2); // remove 0x
    return [
        'totalUsers' => hexdec(substr($hex, 0, 64)),
        'realUsers' => hexdec(substr($hex, 64, 64)),
        'adminAddress' => '0x' . substr($hex, 64 + 64 + 24, 40),
        'isPaused' => hexdec(substr($hex, 192, 64)) == 1,
    ];
}

/**
 * Get user info by userId
 * Returns: wallet, referrerId, totalEarnings, directReferralsCount, activeLevelsCount, isPromo, hasMadeRealPurchase
 */
function getUserInfo($userId) {
    // getUserInfo(uint256) => selector: 0xd379dadf
    $data = '0xd379dadf' . str_pad(dechex($userId), 64, '0', STR_PAD_LEFT);
    $result = ethCall($data);
    if (!$result || strlen($result) < 450) return null;

    $hex = substr($result, 2);
    $wallet = '0x' . substr($hex, 24, 40);

    // Check if user exists (wallet != 0x0)
    if ($wallet === '0x0000000000000000000000000000000000000000') return null;

    return [
        'wallet' => $wallet,
        'referrerId' => hexdec(substr($hex, 64, 64)),
        'totalEarnings' => bcdiv(hexToBigInt(substr($hex, 128, 64)), '1000000000000000000', 2),
        'directReferralsCount' => hexdec(substr($hex, 192, 64)),
        'activeLevelsCount' => hexdec(substr($hex, 256, 64)),
        'isPromo' => hexdec(substr($hex, 320, 64)) == 1,
        'hasMadeRealPurchase' => hexdec(substr($hex, 384, 64)) == 1,
    ];
}

/**
 * Get user by wallet address
 */
function getUserByAddress($address) {
    // getUserByAddress(address) => selector: 0x69c212f6
    $address = str_replace('0x', '', strtolower($address));
    $data = '0x69c212f6' . str_pad($address, 64, '0', STR_PAD_LEFT);
    $result = ethCall($data);
    if (!$result || strlen($result) < 450) return null;

    $hex = substr($result, 2);
    $id = hexdec(substr($hex, 0, 64));
    if ($id == 0) return null;

    return [
        'id' => $id,
        'referrerId' => hexdec(substr($hex, 64, 64)),
        'totalEarnings' => bcdiv(hexToBigInt(substr($hex, 128, 64)), '1000000000000000000', 2),
        'directReferralsCount' => hexdec(substr($hex, 192, 64)),
        'activeLevelsCount' => hexdec(substr($hex, 256, 64)),
        'isPromo' => hexdec(substr($hex, 320, 64)) == 1,
        'hasMadeRealPurchase' => hexdec(substr($hex, 384, 64)) == 1,
    ];
}

/**
 * Get user's all levels state
 */
function getUserAllLevelsState($userId) {
    // getUserAllLevelsState(uint256) => selector: 0x049b6355
    $data = '0x049b6355' . str_pad(dechex($userId), 64, '0', STR_PAD_LEFT);
    $result = ethCall($data);
    if (!$result || strlen($result) < 2562) return null;

    $hex = substr($result, 2);
    $levels = [];

    for ($i = 0; $i < 10; $i++) {
        $levels[$i + 1] = [
            'unlocked' => hexdec(substr($hex, $i * 64, 64)) == 1,
            'isReal' => hexdec(substr($hex, (10 + $i) * 64, 64)) == 1,
            'isRefunded' => hexdec(substr($hex, (20 + $i) * 64, 64)) == 1,
            'earnings' => bcdiv(hexToBigInt(substr($hex, (30 + $i) * 64, 64)), '1000000000000000000', 2),
        ];
    }

    return $levels;
}

/**
 * Get the lastUserId from contract
 */
function getLastUserId() {
    // lastUserId() => selector: 0x14c44e09
    $data = '0x14c44e09';
    $result = ethCall($data);
    return $result ? hexdec(substr($result, 2)) : 0;
}

// ─────────────────────────────────────────────────────
// DATA PERSISTENCE
// ─────────────────────────────────────────────────────

/**
 * Get last processed block
 */
function getLastBlock() {
    if (file_exists(LAST_BLOCK_FILE)) {
        return (int)trim(file_get_contents(LAST_BLOCK_FILE));
    }
    return 0;
}

/**
 * Save last processed block
 */
function saveLastBlock($blockNumber) {
    file_put_contents(LAST_BLOCK_FILE, $blockNumber);
}

/**
 * Get all subscribers
 */
function getSubscribers() {
    if (file_exists(SUBSCRIBERS_FILE)) {
        $data = json_decode(file_get_contents(SUBSCRIBERS_FILE), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

/**
 * Save subscribers
 */
function saveSubscribers($subscribers) {
    file_put_contents(SUBSCRIBERS_FILE, json_encode($subscribers, JSON_PRETTY_PRINT));
}

/**
 * Add a subscriber
 */
function addSubscriber($chatId) {
    $subscribers = getSubscribers();
    if (!in_array($chatId, $subscribers)) {
        $subscribers[] = $chatId;
        saveSubscribers($subscribers);
        return true;
    }
    return false;
}

/**
 * Remove a subscriber
 */
function removeSubscriber($chatId) {
    $subscribers = getSubscribers();
    $key = array_search($chatId, $subscribers);
    if ($key !== false) {
        unset($subscribers[$key]);
        $subscribers = array_values($subscribers);
        saveSubscribers($subscribers);
        return true;
    }
    return false;
}

/**
 * Check if chat is subscribed
 */
function isSubscribed($chatId) {
    $subscribers = getSubscribers();
    return in_array($chatId, $subscribers);
}

// ─────────────────────────────────────────────────────
// FORMATTING HELPERS
// ─────────────────────────────────────────────────────

/**
 * Convert hex string to big integer string (for large numbers)
 */
function hexToBigInt($hex) {
    $hex = ltrim($hex, '0');
    if (empty($hex)) return '0';

    $dec = '0';
    $len = strlen($hex);
    for ($i = 0; $i < $len; $i++) {
        $dec = bcmul($dec, '16');
        $dec = bcadd($dec, (string)hexdec($hex[$i]));
    }
    return $dec;
}

/**
 * Format USDT amount from wei
 */
function formatUSDT($weiHex) {
    $wei = hexToBigInt($weiHex);
    return bcdiv($wei, '1000000000000000000', 2);
}

/**
 * Format address for display (short)
 */
function shortAddress($address) {
    return substr($address, 0, 6) . '...' . substr($address, -4);
}

/**
 * Format address as clickable BSCScan link
 */
function addressLink($address) {
    $short = shortAddress($address);
    return "<a href='" . BSCSCAN_URL . "/address/$address'>$short</a>";
}

/**
 * Format tx hash as clickable BSCScan link
 */
function txLink($txHash) {
    $short = substr($txHash, 0, 10) . '...';
    return "<a href='" . BSCSCAN_URL . "/tx/$txHash'>$short</a>";
}

/**
 * Get level emoji based on level number
 */
function levelEmoji($level) {
    $emojis = [
        1 => '🟢', 2 => '🔵', 3 => '🟣', 4 => '🟡', 5 => '🟠',
        6 => '🔴', 7 => '💎', 8 => '👑', 9 => '🌟', 10 => '🏆',
    ];
    return $emojis[$level] ?? '⬜';
}

/**
 * Log error to console
 */
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] ERROR: $message\n";
}

/**
 * Log info to console
 */
function logInfo($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] INFO: $message\n";
}
