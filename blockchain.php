<?php
/**
 * Blockchain Interaction Layer
 * Handles all BSC smart contract read operations via JSON-RPC
 */

require_once __DIR__ . '/config.php';

class Blockchain {
    
    private $rpcUrl;
    private $contractAddress;
    private $requestId = 1;
    
    // Function selectors (first 4 bytes of keccak256 hash of function signature)
    // Computed from: https://emn178.github.io/online-tools/keccak_256.html
    const SELECTOR_GET_USER_INFO       = '3563967d'; // getUserInfo(uint256)
    const SELECTOR_GET_ALL_LEVELS      = '8a1c97a4'; // getUserAllLevelsState(uint256)  
    const SELECTOR_GET_GLOBAL_STATS    = '3598d9e6'; // getGlobalStats()
    const SELECTOR_GET_MATRIX_INFO     = 'a87430ba'; // getMatrixInfo(uint256,uint256) - verify if needed
    const SELECTOR_LAST_USER_ID        = '794b15d6'; // lastUserId()
    const SELECTOR_IS_PROMO            = '3c3e3c31'; // isPromoAccount(uint256)
    const SELECTOR_LEVEL_EARNINGS      = '5efd0e78'; // levelEarnings(uint256,uint256)
    const SELECTOR_ADDRESS_TO_ID       = '3aecba3e'; // addressToId(address)
    const SELECTOR_IS_SLOT_REAL        = '49e4a0ca'; // isSlotReal(uint256,uint256)

    public function __construct() {
        $this->rpcUrl = BSC_RPC_URL;
        $this->contractAddress = CONTRACT_ADDRESS;
    }
    
    /**
     * Make a JSON-RPC eth_call to the BSC node
     */
    private function ethCall($data) {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'eth_call',
            'params'  => [
                [
                    'to'   => $this->contractAddress,
                    'data' => $data
                ],
                'latest'
            ],
            'id' => $this->requestId++
        ]);
        
        $result = $this->httpPost($this->rpcUrl, $payload);
        
        if ($result === false) {
            // Try backup RPC
            $result = $this->httpPost(BSC_RPC_URL_BACKUP, $payload);
        }
        
        if ($result === false) {
            return null;
        }
        
        $decoded = json_decode($result, true);
        
        if (isset($decoded['error'])) {
            error_log("RPC Error: " . json_encode($decoded['error']));
            return null;
        }
        
        return $decoded['result'] ?? null;
    }
    
    /**
     * Get the latest block number
     */
    public function getLatestBlock() {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'eth_blockNumber',
            'params'  => [],
            'id' => $this->requestId++
        ]);
        
        $result = $this->httpPost($this->rpcUrl, $payload);
        if ($result === false) return null;
        
        $decoded = json_decode($result, true);
        if (isset($decoded['result'])) {
            return hexdec($decoded['result']);
        }
        return null;
    }
    
    /**
     * Get event logs from the contract
     */
    public function getLogs($fromBlock, $toBlock, $topics = []) {
        $params = [
            'address'   => $this->contractAddress,
            'fromBlock' => '0x' . dechex($fromBlock),
            'toBlock'   => '0x' . dechex($toBlock),
        ];
        
        if (!empty($topics)) {
            $params['topics'] = $topics;
        }
        
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'method'  => 'eth_getLogs',
            'params'  => [$params],
            'id' => $this->requestId++
        ]);
        
        $result = $this->httpPost($this->rpcUrl, $payload);
        if ($result === false) return [];
        
        $decoded = json_decode($result, true);
        return $decoded['result'] ?? [];
    }
    
    /**
     * Encode a uint256 parameter (pad to 32 bytes)
     */
    private function encodeUint256($value) {
        return str_pad(dechex(intval($value)), 64, '0', STR_PAD_LEFT);
    }
    
    /**
     * Decode a uint256 from hex response
     */
    private function decodeUint256($hex, $offset = 0) {
        // Remove 0x prefix if present
        $hex = ltrim($hex, '0x');
        $start = $offset * 64;
        $chunk = substr($hex, $start, 64);
        if (empty($chunk)) return 0;
        // Handle very large numbers - use bcmath if available
        return $this->hexToDec($chunk);
    }
    
    /**
     * Convert hex to decimal handling large numbers
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
        
        // Fallback for smaller numbers
        if (strlen($hex) <= 15) {
            return strval(hexdec($hex));
        }
        
        return '0';
    }
    
    /**
     * Decode an address from hex (last 20 bytes of a 32-byte word)
     */
    private function decodeAddress($hex, $offset = 0) {
        $hex = ltrim($hex, '0x');
        $start = $offset * 64;
        $chunk = substr($hex, $start, 64);
        return '0x' . substr($chunk, 24, 40);
    }
    
    /**
     * Decode a boolean from hex
     */
    private function decodeBool($hex, $offset = 0) {
        return $this->decodeUint256($hex, $offset) != '0';
    }
    
    /**
     * Convert USDT wei (18 decimals) to human-readable amount
     */
    public function formatUSDT($weiValue) {
        if ($weiValue === '0' || $weiValue === 0) return '0.00';
        
        $weiStr = strval($weiValue);
        
        if (function_exists('bcdiv')) {
            $result = bcdiv($weiStr, '1000000000000000000', 2);
            return $result;
        }
        
        // Fallback: pad and insert decimal
        $weiStr = str_pad($weiStr, 19, '0', STR_PAD_LEFT);
        $intPart = substr($weiStr, 0, -18);
        $decPart = substr($weiStr, -18, 2);
        if (empty($intPart)) $intPart = '0';
        return $intPart . '.' . $decPart;
    }

    /**
     * Get global stats: totalUsers, realUsers, adminAddress, isPaused
     */
    public function getGlobalStats() {
        $data = '0x' . self::SELECTOR_GET_GLOBAL_STATS;
        $result = $this->ethCall($data);
        
        if ($result === null || $result === '0x') return null;
        
        $hex = ltrim($result, '0x');
        
        return [
            'totalUsers'   => $this->decodeUint256($hex, 0),
            'realUsers'    => $this->decodeUint256($hex, 1),
            'adminAddress' => $this->decodeAddress($hex, 2),
            'isPaused'     => $this->decodeBool($hex, 3),
        ];
    }
    
    /**
     * Get user info by ID
     * Returns: wallet, referrerId, totalEarnings, directReferralsCount, activeLevelsCount, isPromo, hasMadeRealPurchase
     */
    public function getUserInfo($userId) {
        $data = '0x' . self::SELECTOR_GET_USER_INFO . $this->encodeUint256($userId);
        $result = $this->ethCall($data);
        
        if ($result === null || $result === '0x') return null;
        
        $hex = ltrim($result, '0x');
        
        $wallet = $this->decodeAddress($hex, 0);
        
        // Check if user exists (wallet is zero address)
        if ($wallet === '0x0000000000000000000000000000000000000000') {
            return null;
        }
        
        return [
            'wallet'              => $wallet,
            'referrerId'          => $this->decodeUint256($hex, 1),
            'totalEarnings'       => $this->decodeUint256($hex, 2),
            'directReferralsCount'=> $this->decodeUint256($hex, 3),
            'activeLevelsCount'   => $this->decodeUint256($hex, 4),
            'isPromo'             => $this->decodeBool($hex, 5),
            'hasMadeRealPurchase' => $this->decodeBool($hex, 6),
        ];
    }
    
    /**
     * Get all levels state for a user
     * Returns: unlocked[10], realStatus[10], refundedStatus[10], earningsPerLevel[10]
     */
    public function getUserAllLevelsState($userId) {
        $data = '0x' . self::SELECTOR_GET_ALL_LEVELS . $this->encodeUint256($userId);
        $result = $this->ethCall($data);
        
        if ($result === null || $result === '0x') return null;
        
        $hex = ltrim($result, '0x');
        
        // The return is 4 fixed-size arrays of 10 bools/uint256s
        // Each array is: offset pointer (32 bytes) then 10 x 32-byte values
        // Actually for fixed arrays, they are returned inline
        // bool[10] unlocked = 10 slots starting at offset 0
        // bool[10] realStatus = 10 slots starting at offset 10
        // bool[10] refundedStatus = 10 slots starting at offset 20
        // uint256[10] earningsPerLevel = 10 slots starting at offset 30
        
        $unlocked = [];
        $realStatus = [];
        $refundedStatus = [];
        $earnings = [];
        
        for ($i = 0; $i < 10; $i++) {
            $unlocked[$i+1] = $this->decodeBool($hex, $i);
            $realStatus[$i+1] = $this->decodeBool($hex, 10 + $i);
            $refundedStatus[$i+1] = $this->decodeBool($hex, 20 + $i);
            $earnings[$i+1] = $this->decodeUint256($hex, 30 + $i);
        }
        
        return [
            'unlocked'  => $unlocked,
            'real'      => $realStatus,
            'refunded'  => $refundedStatus,
            'earnings'  => $earnings,
        ];
    }
    
    /**
     * Get the last user ID (total registered users)
     */
    public function getLastUserId() {
        $data = '0x' . self::SELECTOR_LAST_USER_ID;
        $result = $this->ethCall($data);
        
        if ($result === null) return 0;
        
        $hex = ltrim($result, '0x');
        return $this->decodeUint256($hex, 0);
    }
    
    /**
     * Get matrix info for a user at a specific level
     */
    public function getMatrixInfo($userId, $level) {
        $data = '0x' . self::SELECTOR_GET_MATRIX_INFO 
              . $this->encodeUint256($userId)
              . $this->encodeUint256($level);
        $result = $this->ethCall($data);
        
        if ($result === null || $result === '0x') return null;
        
        // Matrix info has dynamic arrays, more complex decoding
        // For now, return raw result
        return $result;
    }
    
    /**
     * HTTP POST request using cURL
     */
    private function httpPost($url, $payload) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("cURL Error: $error");
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode");
            return false;
        }
        
        return $response;
    }
}
