<?php
/**
 * Tronex Bot - Command Handlers
 * Processes all user interactions: /start, ID tracking, callbacks
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/blockchain.php';
require_once __DIR__ . '/database.php';

class CommandHandler {
    
    private $bc;
    
    public function __construct() {
        $this->bc = new Blockchain();
    }
    
    /**
     * Process an incoming update from Telegram
     */
    public function handleUpdate($update) {
        // Handle callback queries (button presses)
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }
        
        // Handle regular messages
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }
    }
    
    /**
     * Handle incoming text messages
     */
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        
        // Auto-subscribe user to notifications
        Database::addSubscriber($chatId);
        
        // /start command
        if ($text === '/start') {
            $this->showStartMenu($chatId);
            return;
        }
        
        // If user sends a number, treat it as a Sponsor ID to track
        if (is_numeric($text) && intval($text) > 0) {
            $this->trackUserId($chatId, intval($text));
            return;
        }
        
        // Unknown input - show help
        $this->showStartMenu($chatId);
    }
    
    /**
     * Show the /start welcome menu
     */
    private function showStartMenu($chatId) {
        $text = "🚀 *TRONEX TRACKER BOT*\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "📊 *User ID Tracker*\n\n";
        $text .= "Send a Sponsor ID:\n";
        $text .= "Format: `1`\n\n";
        $text .= "You can track multiple IDs\n\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Global Stats', 'callback_data' => 'global_stats']
                ],
                [
                    ['text' => '🔍 Track ID 1 (Admin)', 'callback_data' => 'track_1']
                ]
            ]
        ];
        
        TelegramAPI::sendMessage($chatId, $text, $keyboard);
    }
    
    /**
     * Track a user ID - fetch all data from blockchain
     */
    private function trackUserId($chatId, $userId, $messageId = null) {
        // Send loading message
        if ($messageId) {
            TelegramAPI::editMessage($chatId, $messageId, "⏳ *Loading data from blockchain...*\nPlease wait...");
        } else {
            $loadingMsg = TelegramAPI::sendMessage($chatId, "⏳ *Loading data from blockchain...*\nPlease wait...");
            $messageId = $loadingMsg['message_id'] ?? null;
        }
        
        // Fetch user info from blockchain
        $userInfo = $this->bc->getUserInfo($userId);
        
        if ($userInfo === null) {
            $errorText = "❌ *User Not Found*\n\n";
            $errorText .= "No user found with ID `$userId` on the Tronex contract.\n\n";
            $errorText .= "━━━━━━━━━━━━━━━━━━━━━━━━";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                    ]
                ]
            ];
            
            if ($messageId) {
                TelegramAPI::editMessage($chatId, $messageId, $errorText, $keyboard);
            } else {
                TelegramAPI::sendMessage($chatId, $errorText, $keyboard);
            }
            return;
        }
        
        // Fetch levels state
        $levelsState = $this->bc->getUserAllLevelsState($userId);
        
        // Build the response message
        $msg = $this->buildUserReport($userId, $userInfo, $levelsState);
        
        // Build keyboard with actions
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'track_' . $userId],
                ],
                [
                    ['text' => '👤 Track Sponsor (ID: ' . $userInfo['referrerId'] . ')', 'callback_data' => 'track_' . $userInfo['referrerId']],
                ],
                [
                    ['text' => '📊 Global Stats', 'callback_data' => 'global_stats'],
                ],
                [
                    ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu'],
                ]
            ]
        ];
        
        if ($messageId) {
            TelegramAPI::editMessage($chatId, $messageId, $msg, $keyboard);
        } else {
            TelegramAPI::sendMessage($chatId, $msg, $keyboard);
        }
    }
    
    /**
     * Build formatted user report
     */
    private function buildUserReport($userId, $userInfo, $levelsState) {
        $wallet = $userInfo['wallet'];
        $shortWallet = substr($wallet, 0, 6) . '...' . substr($wallet, -4);
        
        $totalEarnings = $this->bc->formatUSDT($userInfo['totalEarnings']);
        $activeLevels = $userInfo['activeLevelsCount'];
        $directRefs = $userInfo['directReferralsCount'];
        $referrerId = $userInfo['referrerId'];
        $isPromo = $userInfo['isPromo'] ? '🎁 Promo' : '✅ Real';
        $hasRealPurchase = $userInfo['hasMadeRealPurchase'] ? '✅ Yes' : '❌ No';
        
        $msg  = "🔍 *USER ID: $userId*\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Registration Info
        $msg .= "📋 *Registration Info:*\n";
        $msg .= "├ 💳 Wallet: `$shortWallet`\n";
        $msg .= "├ 👤 Sponsor ID: `$referrerId`\n";
        $msg .= "├ 🏷 Account Type: $isPromo\n";
        $msg .= "└ 💰 Real Purchase: $hasRealPurchase\n\n";
        
        // Active Slots
        $msg .= "🎰 *Slot Activation:*\n";
        $msg .= "├ Active Slots: *$activeLevels / 10*\n";
        
        if ($levelsState !== null) {
            $slotIcons = '';
            for ($i = 1; $i <= 10; $i++) {
                if (isset($levelsState['unlocked'][$i]) && $levelsState['unlocked'][$i]) {
                    if (isset($levelsState['real'][$i]) && $levelsState['real'][$i]) {
                        $slotIcons .= '🟢'; // Real active
                    } else {
                        $slotIcons .= '🟡'; // Virtual/Promo
                    }
                } else {
                    $slotIcons .= '🔴'; // Inactive
                }
            }
            $msg .= "└ Slots: $slotIcons\n\n";
        } else {
            $msg .= "\n";
        }
        
        // Level Update Details
        $msg .= "📊 *Level Details:*\n";
        $prices = LEVEL_PRICES;
        
        if ($levelsState !== null) {
            $totalLevelIncome = '0';
            
            for ($i = 1; $i <= 10; $i++) {
                $price = $prices[$i];
                $isUnlocked = isset($levelsState['unlocked'][$i]) && $levelsState['unlocked'][$i];
                $isReal = isset($levelsState['real'][$i]) && $levelsState['real'][$i];
                $isRefunded = isset($levelsState['refunded'][$i]) && $levelsState['refunded'][$i];
                $earning = isset($levelsState['earnings'][$i]) ? $this->bc->formatUSDT($levelsState['earnings'][$i]) : '0.00';
                
                if ($isUnlocked) {
                    $status = $isReal ? '🟢' : '🟡';
                    $refStatus = $isRefunded ? ' 🔒Refunded' : '';
                    $msg .= "├ $status L$i (\${$price}) - Income: \${$earning}$refStatus\n";
                    
                    if (function_exists('bcadd')) {
                        $totalLevelIncome = bcadd($totalLevelIncome, $levelsState['earnings'][$i] ?? '0');
                    }
                } else {
                    $msg .= "├ 🔴 L$i (\${$price}) - *Locked*\n";
                }
            }
            $msg .= "\n";
        }
        
        // Income Summary
        $msg .= "💰 *Income Summary:*\n";
        $msg .= "├ Total Earnings: *\${$totalEarnings} USDT*\n";
        $msg .= "├ Direct Referrals: *$directRefs*\n";
        
        // Income level summary
        if ($activeLevels > 0) {
            $maxLevelPrice = $prices[$activeLevels] ?? 0;
            $msg .= "└ Income up to Level: *$activeLevels* (\${$maxLevelPrice})\n\n";
        } else {
            $msg .= "└ Income up to Level: *None*\n\n";
        }
        
        // Referral under levels
        $msg .= "👥 *Registrations under Sponsor:*\n";
        $msg .= "└ Direct Registrations: *$directRefs members*\n\n";
        
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🟢 Real | 🟡 Virtual/Promo | 🔴 Locked";
        
        return $msg;
    }
    
    /**
     * Handle callback queries (inline button presses)
     */
    private function handleCallback($callback) {
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data = $callback['data'];
        $callbackId = $callback['id'];
        
        // Acknowledge the callback
        TelegramAPI::answerCallback($callbackId);
        
        // Route based on callback data
        if ($data === 'back_to_menu') {
            $this->showStartMenuEdit($chatId, $messageId);
            return;
        }
        
        if ($data === 'global_stats') {
            $this->showGlobalStats($chatId, $messageId);
            return;
        }
        
        if (strpos($data, 'track_') === 0) {
            $userId = intval(substr($data, 6));
            if ($userId > 0) {
                $this->trackUserId($chatId, $userId, $messageId);
            }
            return;
        }
    }
    
    /**
     * Show start menu by editing an existing message
     */
    private function showStartMenuEdit($chatId, $messageId) {
        $text = "🚀 *TRONEX TRACKER BOT*\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "📊 *User ID Tracker*\n\n";
        $text .= "Send a Sponsor ID:\n";
        $text .= "Format: `1`\n\n";
        $text .= "You can track multiple IDs\n\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Global Stats', 'callback_data' => 'global_stats']
                ],
                [
                    ['text' => '🔍 Track ID 1 (Admin)', 'callback_data' => 'track_1']
                ]
            ]
        ];
        
        TelegramAPI::editMessage($chatId, $messageId, $text, $keyboard);
    }
    
    /**
     * Show global contract statistics
     */
    private function showGlobalStats($chatId, $messageId = null) {
        $stats = $this->bc->getGlobalStats();
        
        if ($stats === null) {
            $text = "❌ *Error fetching global stats*\n\nPlease try again later.";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']]
                ]
            ];
            
            if ($messageId) {
                TelegramAPI::editMessage($chatId, $messageId, $text, $keyboard);
            } else {
                TelegramAPI::sendMessage($chatId, $text, $keyboard);
            }
            return;
        }
        
        $totalUsers = $stats['totalUsers'];
        $realUsers = $stats['realUsers'];
        $isPaused = $stats['isPaused'] ? '🔴 Paused' : '🟢 Active';
        $adminAddr = substr($stats['adminAddress'], 0, 6) . '...' . substr($stats['adminAddress'], -4);
        
        $text  = "📊 *TRONEX GLOBAL STATS*\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $text .= "👥 Total Registered: *$totalUsers*\n";
        $text .= "💎 Real Users (Paid): *$realUsers*\n";
        $text .= "🎁 Promo Accounts: *" . ($totalUsers - $realUsers) . "*\n";
        $text .= "📡 Contract Status: $isPaused\n";
        $text .= "👑 Admin: `$adminAddr`\n\n";
        $text .= "🔗 Contract:\n`" . CONTRACT_ADDRESS . "`\n\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━━━━━";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'global_stats']
                ],
                [
                    ['text' => '🔙 Back to Menu', 'callback_data' => 'back_to_menu']
                ]
            ]
        ];
        
        if ($messageId) {
            TelegramAPI::editMessage($chatId, $messageId, $text, $keyboard);
        } else {
            TelegramAPI::sendMessage($chatId, $text, $keyboard);
        }
    }
}
