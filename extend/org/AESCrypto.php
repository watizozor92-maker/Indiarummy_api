<?php
namespace org;
/**
 * AES加密解密类
 * 模式：CBC
 * 填充：PKCS5Padding
 * 编码：Base64
 * 字符集：UTF-8
 */
class AESCrypto 
{
    private $key;
    private $iv;
    
    /**
     * 构造函数
     * @param string $key 加密密钥
     * @param string $iv 偏移量
     */
    public function __construct($key, $iv) 
    {
        // 标准化密钥长度
        $this->key = $this->normalizeKey($key);
        $this->iv = $this->normalizeIV($iv);
    }
    
    /**
     * 标准化密钥长度
     */
    private function normalizeKey($key)
    {
        // AES-128: 16字节, AES-256: 32字节
        // 根据你的密钥长度自动选择算法
        $keyLength = strlen($key);
        
        if ($keyLength <= 16) {
            // 使用AES-128，密钥填充到16字节
            return str_pad($key, 16, "\0");
        } else {
            // 使用AES-256，密钥填充到32字节
            return str_pad($key, 32, "\0");
        }
    }
    
    /**
     * 标准化IV长度
     */
    private function normalizeIV($iv)
    {
        // IV必须是16字节
        if (strlen($iv) > 16) {
            return substr($iv, 0, 16);
        } elseif (strlen($iv) < 16) {
            return str_pad($iv, 16, "\0");
        }
        return $iv;
    }
    
    /**
     * PKCS5填充  自己写的不灵, 用openssl才行
     */
    private function pkcs5Pad($data, $blockSize) 
    {
        $pad = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($pad), $pad);
    }
    
    /**
     * PKCS5去填充  自己写的不灵, 用openssl才行
     */
    private function pkcs5Unpad($data) 
    {
        $pad = ord($data[strlen($data) - 1]);
        if ($pad > strlen($data)) {
            return false;
        }
        if (strspn($data, chr($pad), strlen($data) - $pad) != $pad) {
            return false;
        }
        return substr($data, 0, -1 * $pad);
    }
    
    /**
     * 获取加密算法（根据密钥长度自动选择）
     */
    private function getCipherMethod()
    {
        $keyLength = strlen($this->key);
        if ($keyLength <= 16) {
            return 'AES-128-CBC';
        } else {
            return 'AES-256-CBC';
        }
    }
    
      /**
     * 使用OpenSSL自动处理填充的加密方法, 默认用pkcs5Padding 兼容性更好
     */
    public function encrypt($data) 
    {
        // 确保数据是UTF-8编码
        if (!mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8');
        }
        
        $method = $this->getCipherMethod();
        
        // 使用OpenSSL自动处理PKCS7填充
        $encrypted = openssl_encrypt(
            $data,
            $method,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );
        
        if ($encrypted === false) {
            throw new Exception('加密失败: ' . openssl_error_string());
        }
        
        return base64_encode($encrypted);
    }

    /**
     * AES加密
     */
    // public function encrypt($data) 
    // {
    //     // 确保数据是UTF-8编码
    //     if (!mb_check_encoding($data, 'UTF-8')) {
    //         $data = mb_convert_encoding($data, 'UTF-8');
    //     }
        
    //     $method = $this->getCipherMethod();
    //     $blockSize = 16;
        
    //     // PKCS5填充
    //     $paddedData = $this->pkcs5Pad($data, $blockSize);
        
    //     // AES加密
    //     $encrypted = openssl_encrypt(
    //         $paddedData,
    //         $method,
    //         $this->key,
    //         OPENSSL_RAW_DATA,
    //         $this->iv
    //     );
        
    //     if ($encrypted === false) {
    //         throw new Exception('加密失败: ' . openssl_error_string());
    //     }
        
    //     // Base64编码
    //     return base64_encode($encrypted);
    // }


     /**
     * 使用OpenSSL自动处理填充的解密方法
     */
    public function decrypt($encryptedData) 
    {
        $method = $this->getCipherMethod();
        
        // Base64解码
        $encrypted = base64_decode($encryptedData);
        
        if ($encrypted === false) {
            throw new Exception('Base64解码失败');
        }
        
        // 使用OpenSSL自动处理PKCS7去填充
        $decrypted = openssl_decrypt(
            $encrypted,
            $method,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );
        
        if ($decrypted === false) {
            throw new Exception('解密失败: ' . openssl_error_string());
        }
        
        return $decrypted;
    }
    
    /**
     * AES解密
     */
    // public function decrypt($encryptedData) 
    // {
    //     $method = $this->getCipherMethod();
        
    //     // Base64解码
    //     $encrypted = base64_decode($encryptedData);
        
    //     // AES解密
    //     $decrypted = openssl_decrypt(
    //         $encrypted,
    //         $method,
    //         $this->key,
    //         OPENSSL_RAW_DATA,
    //         $this->iv
    //     );
        
    //     if ($decrypted === false) {
    //         throw new Exception('解密失败: ' . openssl_error_string());
    //     }
        
    //     // PKCS5去填充
    //     $unpaddedData = $this->pkcs5Unpad($decrypted);
        
    //     if ($unpaddedData === false) {
    //         throw new Exception('去填充失败: 数据可能已损坏');
    //     }
        
    //     return $unpaddedData;
    // }
    
    /**
     * 获取密钥信息（用于调试）
     */
    public function getKeyInfo() 
    {
        return [
            'key' => $this->key,
            'key_length' => strlen($this->key),
            'key_hex' => bin2hex($this->key),
            'iv' => $this->iv,
            'iv_length' => strlen($this->iv),
            'iv_hex' => bin2hex($this->iv),
            'iv_base64' => base64_encode($this->iv),
            'cipher_method' => $this->getCipherMethod()
        ];
    }

    /**
     * 加密
     * @param string $input 要加密的明文
     * @param string $key 加密密钥
     * @return string 返回Base64编码的密文
     */
    public static function encrypt_ecb($input, $key) 
    {
        $data = openssl_encrypt($input, 'AES-256-ECB', ($key), OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }

    /**
     * 解密
     * @param string $sStr 要解密的Base64编码密文
     * @param string $key 解密密钥（需与加密密钥相同）
     * @return string 解密后的明文
     */
    public static function decrypt_ecb($str, $key) {
        $decrypted = openssl_decrypt(base64_decode($str), 'AES-256-ECB', ($key), OPENSSL_RAW_DATA);
        return $decrypted;
    }
}
