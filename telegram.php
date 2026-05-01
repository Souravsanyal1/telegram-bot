<?php
/**
 * Telegram API Wrapper
 * Handles all communication with the Telegram Bot API
 */

require_once __DIR__ . '/config.php';

class TelegramAPI {
    
    /**
     * Send a message to a chat
     */
    public static function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = 'Markdown') {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $parseMode,
        ];
        
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        
        return self::request('sendMessage', $params);
    }
    
    /**
     * Edit an existing message
     */
    public static function editMessage($chatId, $messageId, $text, $replyMarkup = null, $parseMode = 'Markdown') {
        $params = [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'text'         => $text,
            'parse_mode'   => $parseMode,
        ];
        
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        
        return self::request('editMessageText', $params);
    }
    
    /**
     * Answer a callback query (acknowledge button press)
     */
    public static function answerCallback($callbackId, $text = '', $showAlert = false) {
        return self::request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text'              => $text,
            'show_alert'        => $showAlert,
        ]);
    }
    
    /**
     * Delete a message
     */
    public static function deleteMessage($chatId, $messageId) {
        return self::request('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }
    
    /**
     * Get updates using long polling
     */
    public static function getUpdates($offset = 0, $timeout = 30) {
        return self::request('getUpdates', [
            'offset'  => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);
    }
    
    /**
     * Set bot commands (menu)
     */
    public static function setMyCommands() {
        $commands = [
            ['command' => 'start', 'description' => '🏠 Main Menu - Track User IDs'],
        ];
        
        return self::request('setMyCommands', [
            'commands' => json_encode($commands),
        ]);
    }
    
    /**
     * Make a request to the Telegram API
     */
    private static function request($method, $params = []) {
        $url = TELEGRAM_API . $method;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("Telegram API Error: $error");
            return null;
        }
        
        $decoded = json_decode($response, true);
        
        if (!isset($decoded['ok']) || $decoded['ok'] !== true) {
            error_log("Telegram API Error Response: " . $response);
            return null;
        }
        
        return $decoded['result'];
    }
}
