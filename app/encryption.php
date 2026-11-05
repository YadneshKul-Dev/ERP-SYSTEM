<?php
// ============================================
// AES-256 Encryption Class
// Military-grade security for data at rest
// ============================================

class Encryption
{
    private static $instance = null;
    private $cipher = 'aes-256-cbc';
    private $key;
    private $hmacKey;

    private function __construct()
    {
        $this->key = hash_hkdf('sha256', DB_ENCRYPTION_KEY, 32, 'erp-encryption-key');
        $this->hmacKey = hash_hkdf('sha256', DB_ENCRYPTION_KEY, 32, 'erp-hmac-key');
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function encrypt($plaintext)
    {
        if ($plaintext === null || $plaintext === '') {
            return '';
        }
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $this->hmacKey, true);
        return base64_encode($hmac . $iv . $ciphertext);
    }

    public function decrypt($ciphertext)
    {
        if ($ciphertext === null || $ciphertext === '') {
            return '';
        }
        $data = base64_decode($ciphertext);
        if ($data === false) {
            return '';
        }
        $hmac = substr($data, 0, 32);
        $iv = substr($data, 32, openssl_cipher_iv_length($this->cipher));
        $ciphertext = substr($data, 32 + openssl_cipher_iv_length($this->cipher));
        $expectedHmac = hash_hmac('sha256', $iv . $ciphertext, $this->hmacKey, true);
        if (!hash_equals($hmac, $expectedHmac)) {
            return '';
        }
        return openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    public function encryptArray($array)
    {
        if (!is_array($array)) {
            return $this->encrypt($array);
        }
        return $this->encrypt(json_encode($array));
    }

    public function decryptArray($data)
    {
        $decrypted = $this->decrypt($data);
        if ($decrypted === '') {
            return [];
        }
        $result = json_decode($decrypted, true);
        return is_array($result) ? $result : [];
    }

    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public function generateSecureToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    public function generateEmployeeCode($prefix = 'EMP')
    {
        $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $num = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . '-' . $rand . $num;
    }
}
