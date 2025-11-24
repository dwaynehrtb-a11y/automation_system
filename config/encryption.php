<?php
/**
 * AES-256-GCM Encryption Helper
 * Handles encryption/decryption of sensitive student data and grades
 */

// Load .env file if not already loaded
if (!function_exists('loadEnv')) {
    function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile) && !getenv('APP_ENCRYPTION_KEY')) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                putenv("$key=$value");
            }
        }
    }
    loadEnv();
}

class Encryption {
    private static $algorithm = 'AES-256-GCM';
    private static $key = null;
    private static $tagLength = 16; // 128-bit authentication tag
    
    /**
     * Initialize encryption key from environment
     */
    public static function init() {
        if (self::$key === null) {
            $appKey = getenv('APP_ENCRYPTION_KEY') ?: getenv('APP_KEY');
            
            if (empty($appKey)) {
                throw new Exception('APP_ENCRYPTION_KEY not set in environment');
            }
            
            // Derive a 256-bit key from APP_KEY using PBKDF2
            self::$key = hash_pbkdf2('sha256', $appKey, 'automation_system_salt', 10000, 32, true);
        }
        return self::$key;
    }
    
    /**
     * Get encryption key
     */
    public static function getKey() {
        if (self::$key === null) {
            self::init();
        }
        return self::$key;
    }
    
    /**
     * Encrypt sensitive data
     * 
     * @param string $plaintext Data to encrypt
     * @return string Base64 encoded IV:ciphertext:tag
     */
    public static function encrypt($plaintext) {
        if (empty($plaintext)) {
            return null;
        }
        
        self::init();
        
        // Generate random 96-bit IV for GCM
        $iv = openssl_random_pseudo_bytes(12);
        
        // Encrypt with GCM mode
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::$algorithm,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '', // Additional authenticated data
            self::$tagLength
        );
        
        if ($ciphertext === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }
        
        // Return IV:ciphertext:tag as base64 for storage
        $encrypted = base64_encode($iv . $ciphertext . $tag);
        
        return $encrypted;
    }
    
    /**
     * Decrypt sensitive data
     * 
     * @param string $encrypted Base64 encoded IV:ciphertext:tag
     * @return string Decrypted plaintext
     */
    public static function decrypt($encrypted) {
        if (empty($encrypted)) {
            return null;
        }
        
        self::init();
        
        try {
            // Decode from base64
            $data = base64_decode($encrypted, true);
            
            if ($data === false) {
                throw new Exception('Invalid base64 data');
            }
            
            // Extract components: IV (12 bytes) + ciphertext + tag (16 bytes)
            $ivLength = 12;
            $tagLength = self::$tagLength;
            
            if (strlen($data) < $ivLength + $tagLength) {
                throw new Exception('Invalid encrypted data length');
            }
            
            $iv = substr($data, 0, $ivLength);
            $tag = substr($data, -$tagLength);
            $ciphertext = substr($data, $ivLength, strlen($data) - $ivLength - $tagLength);
            
            // Decrypt with GCM mode
            $plaintext = openssl_decrypt(
                $ciphertext,
                self::$algorithm,
                self::$key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '' // Additional authenticated data must match encryption
            );
            
            if ($plaintext === false) {
                throw new Exception('Decryption failed - data may be corrupted or tampered: ' . openssl_error_string());
            }
            
            return $plaintext;
            
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Encrypt a field value with error handling
     * 
     * @param mixed $value Value to encrypt
     * @return string Encrypted value or null if empty
     */
    public static function encryptField($value) {
        if (is_null($value) || $value === '') {
            return null;
        }
        
        try {
            return self::encrypt((string)$value);
        } catch (Exception $e) {
            error_log('Field encryption error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Decrypt a field value with error handling
     * 
     * @param string $encrypted Encrypted value
     * @return string Decrypted value or null if empty
     */
    public static function decryptField($encrypted) {
        if (is_null($encrypted) || $encrypted === '') {
            return null;
        }
        
        try {
            return self::decrypt($encrypted);
        } catch (Exception $e) {
            error_log('Field decryption error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate a new encryption key (for setup)
     * Returns a random 32-byte key encoded as base64
     */
    public static function generateKey() {
        $key = openssl_random_pseudo_bytes(32);
        return base64_encode($key);
    }
    
    /**
     * Hash value for searching encrypted fields
     * (Not reversible, used for comparisons when plain searching is needed)
     * 
     * @param string $value Value to hash
     * @return string SHA256 hash
     */
    public static function hashField($value) {
        if (empty($value)) {
            return null;
        }
        return hash('sha256', $value);
    }
}

// Auto-initialize on include
Encryption::init();

/**
 * Backwards-compatible helper to decrypt field values used in older code
 * Returns original value on failure.
 */
if (!function_exists('decryptData')) {
    function decryptData($value) {
        try {
            // Use decryptField which returns null for empty values
            $decrypted = Encryption::decryptField($value);
            if ($decrypted === null) return $value;
            return $decrypted;
        } catch (Exception $e) {
            error_log('decryptData() failed: ' . $e->getMessage());
            return $value;
        }
    }
}
?>
