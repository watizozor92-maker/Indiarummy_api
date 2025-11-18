<?php
namespace org;

class AESCrypto 
{
    /**
     * 加密
     * @param string $input 要加密的明文
     * @param string $key 加密密钥
     * @return string 返回Base64编码的密文
     */
    public static function encrypt($input, $key) {
        $data = openssl_encrypt($input, 'AES-256-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }

    /**
     * 解密
     * @param string $sStr 要解密的Base64编码密文
     * @param string $key 解密密钥（需与加密密钥相同）
     * @return string 解密后的明文
     */
    public static function decrypt($str, $key) {
        $decrypted = openssl_decrypt(base64_decode($str), 'AES-256-ECB', $key, OPENSSL_RAW_DATA);
        return $decrypted;
    }
}