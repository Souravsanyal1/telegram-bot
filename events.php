<?php
/**
 * Tronex Bot - Event Monitor
 * Monitors BSC blockchain for Registration, LevelBought, and DirectReferral events
 * Sends notifications to subscribed Telegram chats
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/blockchain.php';

class EventMonitor {
    
    private $bc;
    private $dataDir;
    private $subscribersFile;
    private $lastBlockFile;
    
    // Pre-computed event topic hashes (keccak256 of event signatures)
    // Registration(address indexed user, address indexed referrer, uint256 userId, uint256 referrerId)
    const TOPIC_REGISTRATION = '0x0a01e6d225e67af04f8719519291e2735a68d64eb1e27a0c1eb0e006e1854f5c';
    
    // LevelBought(address indexed user, uint256 level, uint256 amount)
    const TOPIC_LEVEL_BOUGHT = '0x3cda433c60267e26e1aba4e0db0c218e9a0a84b2b7d362f3e42d7e081a0b26c8';
    
    // DirectReferral(uint256 indexed sponsorId, uint256 indexed newUserId, uint256 timestamp)
    const TOPIC_DIRECT_REFERRAL = '0xe1b5df6e07c82c2f925e9171b5f85cb8ccdc8e95e6fee2f5aef2cb3d21b6b517';
    
    // VirtualSlotActivated(address indexed user, uint256 level, uint256 amount)
    const TOPIC_VIRTUAL_SLOT = '0x8dbbe3a3c7e71d2e9f3b3e8e8e0b5f1c5e3a3c3e5d5f7a9b1c3d5e7f9a1b3c5d';
    
    public function __construct() {
        $this->bc = new Blockchain();
        $this->dataDir = DATA_DIR;
        $this->subscribersFile = $this->dataDir . 'subscribers.json';
        $this->lastBlockFile = $this->dataDir . 'last_block.txt';
        
        // Create data directory if not exists
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    /**
     * Subscribe a chat to event notifications
     */
    public function subscribe($chatId) {
        $subscribers = $this->getSubscribers();
        if (!in_array($chatId, $subscribers)) {
            $subscribers[] = $chatId;
            $this->saveSubscribers($subscribers);
        }
    }
    
    /**
     * Unsubscribe a chat from event notifications
     */
    public function unsubscribe($chatId) {
        $subscribers = $this->getSubscribers();
        $subscribers = array_filter($subscribers, function($id) use ($chatId) {
            return $id != $chatId;
        });
        $this->saveSubscribers(array_values($subscribers));
    }
    
    /**
     * Get list of subscribed chat IDs
     */
    private function getSubscribers() {
        if (!file_exists($this->subscribersFile)) {
            return [];
        }
        $data = file_get_contents($this->subscribersFile);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save subscribers list
     */
    private function saveSubscribers($subscribers) {
        file_put_contents($this->subscribersFile, json_encode($subscribers, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get the last processed block number
     */
    public function getLastBlock() {
        if (!file_exists($this->lastBlockFile)) {
            // Start from a recent block (get current - 100)
            $current = $this->bc->getLatestBlock();
            if ($current) {
                $startBlock = max(1, $current - 100);
                $this->saveLastBlock($startBlock);
                return $startBlock;
            }
            return 0;
        }
        return intval(trim(file_get_contents($this->lastBlockFile)));
    }
    
    /**
     * Save the last processed block number
     */
    public function saveLastBlock($blockNumber) {
        file_put_contents($this->lastBlockFile, strval($blockNumber));
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
