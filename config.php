<?php
/**
 * Tronex Telegram Bot - Configuration
 * ====================================
 * All environment variables and contract constants.
 */

// ─── Telegram Bot ──────────────────────────────────
define('BOT_TOKEN', '8682225634:AAG8b4e5wAqT_20XobD8uVUYEqCxg-ae2wk');
define('TELEGRAM_API', 'https://api.telegram.org/bot' . BOT_TOKEN);

// ─── BSC RPC ───────────────────────────────────────
// Using multiple RPC endpoints for reliability
define('BSC_RPC_URLS', [
    'https://bsc-dataseed.bnbchain.org/',
    'https://bsc-dataseed1.bnbchain.org/',
    'https://bsc-dataseed2.bnbchain.org/',
    'https://bsc-dataseed3.bnbchain.org/',
    'https://bsc-dataseed4.bnbchain.org/',
    'https://bsc-dataseed1.defibit.io/',
    'https://bsc-dataseed1.ninicoin.io/',
]);

// ─── Smart Contract ────────────────────────────────
define('CONTRACT_ADDRESS', '0x80417eE23bf55Da06588bD4d7f33D4a651156D91');

// ─── USDT on BSC ───────────────────────────────────
define('USDT_ADDRESS', '0x55d398326f99059fF775485246999027B3197955');

// ─── Level Prices (in USDT, human-readable) ────────
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

// ─── BscScan Explorer ──────────────────────────────
define('BSCSCAN_URL', 'https://bscscan.com');
define('BSCSCAN_API_URL', 'https://api.bscscan.com/api');
// Get a free API key from bscscan.com for higher rate limits
define('BSCSCAN_API_KEY', '');

// ─── Polling Interval (seconds) ────────────────────
define('POLL_INTERVAL', 2);
define('EVENT_CHECK_INTERVAL', 10);

// ─── Data Storage ──────────────────────────────────
define('DATA_DIR', __DIR__ . '/data');
define('LAST_BLOCK_FILE', DATA_DIR . '/last_block.txt');
define('SUBSCRIBERS_FILE', DATA_DIR . '/subscribers.json');
define('USER_SETTINGS_FILE', DATA_DIR . '/user_settings.json');
define('TRACKED_IDS_FILE', DATA_DIR . '/tracked_ids.json');

// Create data directory if it doesn't exist
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// ─── Event Topic Hashes (keccak256) ────────────────
// Registration(address indexed user, address indexed referrer, uint256 userId, uint256 referrerId)
define('EVENT_REGISTRATION', '0x309bb360e8b69c23937ccc5fb01f9aeeead1c95a99604e175113ff82f2b1723a');

// LevelBought(address indexed user, uint256 level, uint256 amount)
define('EVENT_LEVEL_BOUGHT', '0x8e865c93bf040ad891c6118d3eade534195f32e7564a8209e30323860aec175e');

// VirtualSlotActivated(address indexed user, uint256 level, uint256 amount)
define('EVENT_VIRTUAL_SLOT', '0x33e00f137aeea988035e091a5bd227889f7ea1c704cd04503c066fab3acc5c56');

// DirectBonusPaid(address indexed receiver, address indexed buyer, uint256 amount)
define('EVENT_DIRECT_BONUS', '0xd49ca1e65d3d4924d717338dbd09fe8b97d122da780f18088f1938c1d51c7972');

// GenerationBonusPaid(address indexed receiver, address indexed buyer, uint256 level, uint256 amount)
define('EVENT_GENERATION_BONUS', '0x4f924760fd02bbfddf71b4c2ad9b65d5348747a5db5ba90c982f33e979af606d');

// MissedCommission(uint256 indexed receiverId, uint256 level, uint256 amount)
define('EVENT_MISSED_COMMISSION', '0x250fed96235963ed05bd9c0d9c6b6c8b7e146840224fe62c4efa7d5ed5cce15b');

// MatrixRecycled(uint256 indexed userId, uint256 level, uint256 recycleCount)
define('EVENT_MATRIX_RECYCLED', '0x63a0bd65599ef5cc0b5bc08d8ef0808722d35a036fa390e742940421894fde6f');

// AdminGiftedSlot(uint256 indexed userId, uint256 level)
define('EVENT_ADMIN_GIFTED', '0xb7f0fe1e702685f20f7bb0c1913a80ba71fb1e29180fb4e9e551f00f568273c8');

// DirectReferral(uint256 indexed sponsorId, uint256 indexed newUserId, uint256 timestamp)
define('EVENT_DIRECT_REFERRAL', '0x626e1b8f6db5a5b8915248c7247ad4d15f4b25be16718728bdb9bf6a69a65e22');
