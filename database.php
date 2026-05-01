<?php
/**
 * Database Management Utility
 * Supports both Local File storage and MongoDB Cloud storage
 */

require_once __DIR__ . '/config.php';

class Database {
    
    private static $mongoClient = null;
    
    /**
     * Get a MongoDB Manager instance
     */
    private static function getMongoClient() {
        if (!USE_MONGODB) return null;
        if (self::$mongoClient === null) {
            try {
                self::$mongoClient = new MongoDB\Driver\Manager(MONGODB_URI);
            } catch (Exception $e) {
                error_log("MongoDB Connection Error: " . $e->getMessage());
                return null;
            }
        }
        return self::$mongoClient;
    }
    
    /**
     * Save the last processed block
     */
    public static function saveLastBlock($blockNumber) {
        if (USE_MONGODB) {
            $manager = self::getMongoClient();
            if ($manager) {
                try {
                    $bulk = new MongoDB\Driver\BulkWrite;
                    $bulk->update(['key' => 'last_block'], ['$set' => ['value' => (int)$blockNumber]], ['upsert' => true]);
                    $manager->executeBulkWrite(MONGODB_DB . '.settings', $bulk);
                    return;
                } catch (Exception $e) {
                    error_log("MongoDB saveLastBlock Error: " . $e->getMessage());
                }
            }
        }
        
        // Fallback to local file
        try {
            if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
            file_put_contents(DATA_DIR . 'last_block.txt', (string)$blockNumber);
        } catch (Exception $e) {
            error_log("File Save Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get the last processed block
     */
    public static function getLastBlock() {
        if (USE_MONGODB) {
            $manager = self::getMongoClient();
            if ($manager) {
                try {
                    $query = new MongoDB\Driver\Query(['key' => 'last_block']);
                    $cursor = $manager->executeQuery(MONGODB_DB . '.settings', $query);
                    foreach ($cursor as $document) {
                        return $document->value;
                    }
                } catch (Exception $e) {
                    error_log("MongoDB getLastBlock Error: " . $e->getMessage());
                }
            }
        }
        
        // Fallback to local file
        $file = DATA_DIR . 'last_block.txt';
        if (file_exists($file)) {
            return (int)trim(file_get_contents($file));
        }
        return 0;
    }
    
    /**
     * Add a subscriber for notifications
     */
    public static function addSubscriber($chatId) {
        if (USE_MONGODB) {
            $manager = self::getMongoClient();
            if ($manager) {
                try {
                    $bulk = new MongoDB\Driver\BulkWrite;
                    $bulk->update(['chat_id' => (string)$chatId], ['$set' => ['chat_id' => (string)$chatId]], ['upsert' => true]);
                    $manager->executeBulkWrite(MONGODB_DB . '.subscribers', $bulk);
                    return;
                } catch (Exception $e) {
                    error_log("MongoDB addSubscriber Error: " . $e->getMessage());
                }
            }
        }
        
        // Fallback to local file
        $subscribers = self::getSubscribers();
        if (!in_array($chatId, $subscribers)) {
            $subscribers[] = $chatId;
            self::saveSubscribersFile($subscribers);
        }
    }
    
    /**
     * Get all subscribers
     */
    public static function getSubscribers() {
        if (USE_MONGODB) {
            $manager = self::getMongoClient();
            if ($manager) {
                try {
                    $query = new MongoDB\Driver\Query([]);
                    $cursor = $manager->executeQuery(MONGODB_DB . '.subscribers', $query);
                    $ids = [];
                    foreach ($cursor as $document) {
                        $ids[] = $document->chat_id;
                    }
                    return $ids;
                } catch (Exception $e) {
                    error_log("MongoDB getSubscribers Error: " . $e->getMessage());
                }
            }
        }
        
        // Fallback to local file
        $file = DATA_DIR . 'subscribers.json';
        if (file_exists($file)) {
            $data = file_get_contents($file);
            return json_decode($data, true) ?: [];
        }
        return [];
    }
    
    /**
     * Internal helper to save subscribers file
     */
    private static function saveSubscribersFile($subscribers) {
        if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
        file_put_contents(DATA_DIR . 'subscribers.json', json_encode(array_values($subscribers)));
    }
}
