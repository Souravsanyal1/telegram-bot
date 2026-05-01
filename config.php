<?php
/**
 * Tronex Bot Configuration
 * All settings for the Telegram bot and BSC smart contract
 */

// ==========================================
// TELEGRAM BOT SETTINGS
// ==========================================
define('BOT_TOKEN', '8682225634:AAG8b4e5wAqT_20XobD8uVUYEqCxg-ae2wk');
define('TELEGRAM_API', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// ==========================================
// BSC SMART CONTRACT SETTINGS
// ==========================================
define('CONTRACT_ADDRESS', '0x80417eE23bf55Da06588bD4d7f33D4a651156D91');
define('BSC_RPC_URL', 'https://bsc-dataseed1.binance.org/');
define('BSC_RPC_URL_BACKUP', 'https://bsc-dataseed2.binance.org/');
define('BSCSCAN_API_URL', 'https://api.bscscan.com/api');
define('BSCSCAN_API_KEY', ''); // Optional: Add your BSCScan API key for higher rate limits

// ==========================================
// MONGODB SETTINGS (For Cloud Persistence)
// ==========================================
define('USE_MONGODB', true); // Set to true to use MongoDB instead of local files
define('MONGODB_URI', 'mongodb+srv://sourav_s:1233548sad45dsafr8a36@cluster0.v5m9kps.mongodb.net/?appName=Cluster0');
define('MONGODB_DB', 'tronex_bot');

// ==========================================
// LEVEL PRICES (in USDT, 18 decimals on-chain)
// ==========================================
define('LEVEL_PRICES', [
    1 => 6,
    2 => 10,
    3 => 20,
    4 => 40,
    5 => 80,
    6 => 160,
    7 => 320,
    8 => 640,
    9 => 1280,
    10 => 2560,
]);

// ==========================================
// EVENT MONITORING SETTINGS
// ==========================================
define('POLL_INTERVAL', 5);          // Seconds between each blockchain poll
define('BLOCK_RANGE', 50);           // Number of blocks to scan per poll
define('DATA_DIR', __DIR__ . '/data/');

// ==========================================
// EVENT TOPIC HASHES (keccak256 of event signatures)
// ==========================================
define('EVENT_REGISTRATION', '0x' . hash('sha3-256', 'Registration(address,address,uint256,uint256)'));
define('EVENT_LEVEL_BOUGHT', '0x' . hash('sha3-256', 'LevelBought(address,uint256,uint256)'));
define('EVENT_DIRECT_REFERRAL', '0x' . hash('sha3-256', 'DirectReferral(uint256,uint256,uint256)'));

// Pre-computed keccak256 hashes for the events we monitor
// Registration(address indexed user, address indexed referrer, uint256 userId, uint256 referrerId)
define('TOPIC_REGISTRATION', '0x3bce859e7247bc7ebd1f7da39c9e07df02095a89e44e498b32ed1d116021204a');
// LevelBought(address indexed user, uint256 level, uint256 amount)
define('TOPIC_LEVEL_BOUGHT', '0x7d28f43e3a6e07a7c4cfe09340b7e0528c8768b1a4fa8tried5a3bd5a15f63b42');
// DirectReferral(uint256 indexed sponsorId, uint256 indexed newUserId, uint256 timestamp)
define('TOPIC_DIRECT_REFERRAL', '0xe1b5df6e07c82c2f925e9171b5f85cb8ccdc8e95e6fee2f5aef2cb3d21b6b517');

// ==========================================
// ABI ENCODINGS FOR VIEW FUNCTIONS
// ==========================================

// Function selectors (first 4 bytes of keccak256 of function signature)
// getUserInfo(uint256) => selector
define('FUNC_GET_USER_INFO', '0x6a627842'); // Will compute properly
// getUserAllLevelsState(uint256)
define('FUNC_GET_ALL_LEVELS', '0xbb5f3d27'); // Will compute properly
// getGlobalStats()
define('FUNC_GET_GLOBAL_STATS', '0xc4f79515');
// getMatrixInfo(uint256, uint256)
define('FUNC_GET_MATRIX_INFO', '0xa87430ba');

// ==========================================
// BOT MESSAGES
// ==========================================
define('MSG_WELCOME', "🚀 *TRONEX TRACKER BOT*\n━━━━━━━━━━━━━━━━━━━━━━━━\n\n📊 Track any Sponsor ID from the Tronex Smart Contract\n\n🔗 Contract: `" . CONTRACT_ADDRESS . "`\n\n👇 *Send a Sponsor ID to track:*\nFormat: `1`\n\nYou can track multiple IDs");

define('MSG_INVALID_ID', "❌ *Invalid ID*\n\nPlease send a valid numeric Sponsor ID.\nFormat: `1`");

define('MSG_USER_NOT_FOUND', "❌ *User Not Found*\n\nNo user found with this ID on the Tronex contract.");

define('MSG_LOADING', "⏳ *Loading data from blockchain...*\nPlease wait...");
