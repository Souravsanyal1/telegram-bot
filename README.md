# 🚀 Tronex Telegram Bot

BSC Smart Contract Event Monitor for the **TronexMain_V13_Production** contract.

## ✨ Features

| Feature | Description |
|---------|-------------|
| 🆕 **Registration Alerts** | Real-time notification when new users register |
| 📊 **Slot Activation** | Shows which slot number is activated with level details |
| ⬆️ **Level Updates** | Notifies when any level is bought/upgraded |
| 💰 **Income Tracking** | Shows income earned per level with ROI % |
| 👥 **Team Tracking** | Shows which levels have new registrations |
| 💵 **Bonus Alerts** | Direct & generation bonus payment notifications |
| ♻️ **Matrix Recycles** | Matrix recycle event notifications |
| 🎁 **Admin Gifts** | Admin gifted slot notifications |

## 📋 Bot Commands

| Command | Description |
|---------|-------------|
| `/start` | Welcome message & quick actions |
| `/help` | Show all available commands |
| `/stats` | Global platform statistics |
| `/user [ID]` | Look up user by ID |
| `/wallet [address]` | Look up user by wallet address |
| `/levels [ID]` | Show user's level activation status |
| `/income [ID]` | Show user's income breakdown per level |
| `/subscribe` | Enable live blockchain event alerts |
| `/unsubscribe` | Disable live alerts |
| `/prices` | Show level prices table |

## 🛠️ Requirements

- **PHP 8.0+** with extensions: `curl`, `bcmath`, `openssl`, `mbstring`
- Internet connection (for BSC RPC & Telegram API)

## 🚀 Quick Start

### Option 1: Auto Install (Windows)
Double-click `install_and_run.bat` — it will install PHP if needed and start the bot.

### Option 2: Manual Setup

1. **Install PHP** (if not installed):
   - Download from https://windows.php.net/download/
   - Get **VS16 x64 Thread Safe** ZIP
   - Extract to `C:\php\`
   - Add `C:\php` to your system PATH
   - Rename `php.ini-production` to `php.ini`
   - Enable extensions in `php.ini`: `curl`, `bcmath`, `openssl`, `mbstring`

2. **Run the bot**:
   ```bash
   php bot.php
   ```

## 📂 File Structure

```
Tronex bot/
├── bot.php              # Main entry point — run this
├── config.php           # Configuration (token, contract, etc.)
├── helpers.php          # BSC RPC, Telegram API, formatting utilities
├── commands.php         # Bot command handlers
├── events.php           # Blockchain event monitor & notifications
├── install_and_run.bat  # Auto-installer for Windows
├── README.md            # This file
└── data/                # Auto-created data storage
    ├── last_block.txt   # Last processed block number
    └── subscribers.json # List of subscribed chat IDs
```

## ⚙️ Configuration

Edit `config.php` to change:
- `BOT_TOKEN` — Your Telegram bot token
- `CONTRACT_ADDRESS` — Smart contract address
- `BSC_RPC_URL` — BSC RPC endpoint
- `EVENT_CHECK_INTERVAL` — How often to check for new events (seconds)

## 📊 Smart Contract Details

- **Contract**: TronexMain_V13_Production
- **Network**: BSC (Binance Smart Chain)
- **Address**: `0x80417eE23bf55Da06588bD4d7f33D4a651156D91`
- **Levels**: 10 (6 USDT → 2,560 USDT)

## 🔔 How It Works

1. The bot polls Telegram for user commands
2. Every 5 seconds, it checks the BSC blockchain for new events
3. When events are detected (registrations, level purchases, etc.), it formats and sends notifications to all subscribers
4. Users can query specific user data on-demand via commands
