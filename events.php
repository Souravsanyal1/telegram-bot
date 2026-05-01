<?php
/**
 * Tronex Bot - Event Monitor
 * Monitors BSC blockchain for Registration, LevelBought, and DirectReferral events
 * Sends notifications to subscribed Telegram chats
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class EventMonitor {
    
    private $bc;
    
    // Pre-computed event topic hashes (keccak256 of event signatures)
    // Registration(address indexed user, address indexed referrer, uint256 userId, uint256 referrerId)
    const TOPIC_REGISTRATION = '0x3bce859e7247bc7ebd1f7da39c9e07df02095a89e44e498b32ed1d116021204a';
    
    // LevelBought(address indexed user, uint256 level, uint256 amount)
    const TOPIC_LEVEL_BOUGHT = '0x7d28f43e3a6e07a7c4cfe09340b7e0528c8768b1a4fa86495a3bd5a15f63b42';
    
    // DirectReferral(uint256 indexed sponsorId, uint256 indexed newUserId, uint256 timestamp)
    const TOPIC_DIRECT_REFERRAL = '0xe1b5df6e07c82c2f925e9171b5f85cb8ccdc8e95e6fee2f5aef2cb3d21b6b517';
    
    public function __construct() {
        $this->bc = new Blockchain();
    }
    
    /**
     * Subscribe a chat to event notifications
     */
    public function subscribe($chatId) {
        Database::addSubscriber($chatId);
    }
    
    /**
     * Get list of subscribed chat IDs
     */
    private function getSubscribers() {
        return Database::getSubscribers();
    }
    
    /**
     * Get the last processed block number
     */
    public function getLastBlock() {
        $lastBlock = Database::getLastBlock();
        
        // If last block is 0 or 1, start from the current block
        if ($lastBlock <= 1) {
            $current = $this->bc->getLatestBlock();
            if ($current) {
                $startBlock = max(1, $current - 10);
                Database::saveLastBlock($startBlock);
                return $startBlock;
            }
            return 0;
        }
        return $lastBlock;
    }
    
    /**
     * Save the last processed block number
     */
    public function saveLastBlock($blockNumber) {
        Database::saveLastBlock($blockNumber);
    }
    
    /**
     * Main monitoring loop - scan for events and send notifications
     */
    public function pollEvents() {
        $lastBlock = $this->getLastBlock();
        $latestBlock = $this->bc->getLatestBlock();
        
        if ($latestBlock === null || $latestBlock <= $lastBlock) {
            return;
        }
        
        // Limit range to prevent timeout
        $toBlock = min($latestBlock, $lastBlock + BLOCK_RANGE);
        $fromBlock = $lastBlock + 1;
        
        if ($fromBlock > $toBlock) {
            return;
        }
        
        echo "[Event Monitor] Scanning blocks $fromBlock to $toBlock...\n";
        
        // Fetch all logs from the contract
        $logs = $this->bc->getLogs($fromBlock, $toBlock);
        
        if (!empty($logs)) {
            foreach ($logs as $log) {
                $this->processLog($log);
            }
        }
        
        // Update last processed block
        $this->saveLastBlock($toBlock);
    }
    
    /**
     * Process a single event log and send notification
     */
    private function processLog($log) {
        $topics = $log['topics'] ?? [];
        if (empty($topics)) return;
        
        $eventTopic = $topics[0];
        $data = $log['data'] ?? '0x';
        $txHash = $log['transactionHash'] ?? '';
        $shortTx = substr($txHash, 0, 10) . '...' . substr($txHash, -6);
        
        $subscribers = $this->getSubscribers();
        if (empty($subscribers)) return;
        
        $message = null;
        
        // Match event by topic hash
        switch ($eventTopic) {
            case self::TOPIC_REGISTRATION:
                $message = $this->formatRegistrationEvent($topics, $data, $shortTx, $txHash);
                break;
                
            case self::TOPIC_LEVEL_BOUGHT:
                $message = $this->formatLevelBoughtEvent($topics, $data, $shortTx, $txHash);
                break;
                
            case self::TOPIC_DIRECT_REFERRAL:
                $message = $this->formatDirectReferralEvent($topics, $data, $shortTx, $txHash);
                break;
        }
        
        if ($message !== null) {
            foreach ($subscribers as $chatId) {
                TelegramAPI::sendMessage($chatId, $message);
                usleep(100000); // 100ms delay to avoid rate limits
            }
        }
    }
    
    /**
     * Format Registration event notification
     */
    private function formatRegistrationEvent($topics, $data, $shortTx, $txHash) {
        // topics[1] = indexed user address
        // topics[2] = indexed referrer address
        // data = userId (uint256) + referrerId (uint256)
        
        $dataHex = ltrim($data, '0x');
        $userId = $this->hexToDec(substr($dataHex, 0, 64));
        $referrerId = $this->hexToDec(substr($dataHex, 64, 64));
        
        $userAddr = '0x' . substr($topics[1] ?? '', 26);
        $shortAddr = substr($userAddr, 0, 6) . '...' . substr($userAddr, -4);
        
        $msg  = "🆕 *NEW REGISTRATION*\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "👤 New User ID: *#$userId*\n";
        $msg .= "💳 Wallet: `$shortAddr`\n";
        $msg .= "👥 Sponsor ID: *#$referrerId*\n";
        $msg .= "🔗 [View TX](https://bscscan.com/tx/$txHash)\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━";
        
        return $msg;
    }
    
    /**
     * Format LevelBought event notification
     */
    private function formatLevelBoughtEvent($topics, $data, $shortTx, $txHash) {
        // topics[1] = indexed user address
        // data = level (uint256) + amount (uint256)
        
        $dataHex = ltrim($data, '0x');
        $level = $this->hexToDec(substr($dataHex, 0, 64));
        $amountWei = $this->hexToDec(substr($dataHex, 64, 64));
        $amount = $this->bc->formatUSDT($amountWei);
        
        $userAddr = '0x' . substr($topics[1] ?? '', 26);
        $shortAddr = substr($userAddr, 0, 6) . '...' . substr($userAddr, -4);
        
        $msg  = "🎰 *SLOT ACTIVATED*\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "👤 User: `$shortAddr`\n";
        $msg .= "📊 Level: *$level* activated!\n";
        $msg .= "💰 Amount: *\$$amount USDT*\n";
        $msg .= "🔗 [View TX](https://bscscan.com/tx/$txHash)\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━";
        
        return $msg;
    }
    
    /**
     * Format DirectReferral event notification
     */
    private function formatDirectReferralEvent($topics, $data, $shortTx, $txHash) {
        // topics[1] = indexed sponsorId
        // topics[2] = indexed newUserId
        // data = timestamp (uint256)
        
        $sponsorId = $this->hexToDec(substr($topics[1] ?? '0x0', 2));
        $newUserId = $this->hexToDec(substr($topics[2] ?? '0x0', 2));
        
        $msg  = "👥 *NEW DIRECT REFERRAL*\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $msg .= "👤 Sponsor ID: *#$sponsorId*\n";
        $msg .= "🆕 New User ID: *#$newUserId*\n";
        $msg .= "📝 Registered under Sponsor #$sponsorId\n";
        $msg .= "🔗 [View TX](https://bscscan.com/tx/$txHash)\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━";
        
        return $msg;
    }
    
    /**
     * Hex to decimal conversion
     */
    private function hexToDec($hex) {
        $hex = ltrim($hex, '0');
        if (empty($hex)) return '0';
        
        if (function_exists('gmp_init')) {
            return gmp_strval(gmp_init($hex, 16));
        }
        
        if (function_exists('bcmul')) {
            $dec = '0';
            $len = strlen($hex);
            for ($i = 0; $i < $len; $i++) {
                $dec = bcmul($dec, '16');
                $dec = bcadd($dec, hexdec($hex[$i]));
            }
            return $dec;
        }
        
        if (strlen($hex) <= 15) {
            return strval(hexdec($hex));
        }
        
        return '0';
    }
}
