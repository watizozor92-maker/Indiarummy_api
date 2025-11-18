<?php
namespace org;

/**
 * RSA加解密超长处理
 */
class CryptRsa
{
    // use InstanceTrait;

    const  RSA_ENCRYPT_BLOCK_SIZE = 117;
    const  RSA_DECRYPT_BLOCK_SIZE = 128;

    /**
     * 转成标格式（有时你会发现别人给你的秘钥不是标准格式，可以用此方法将其转成标注格式）
     * @Add 乐杨俊 leyangjun
     * @param $key
     * @return string
     */
    public function setSPhptandardFormat($key)
    {
        //$pem = chunk_split($key, 64, "\n");
        //$pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        //$publicKey = openssl_pkey_get_public($pem);
        $pem = "-----BEGIN PUBLIC KEY-----\r\n" . chunk_split($key) . "-----END PUBLIC KEY-----";
        return $pem;
    }


    /**
     * 超长加密（由于秘钥有长度限制比如1024，2048约长表示接受的加密数据越多，否则会有超长加密不成功的问题）
     * 117表示长度，加密的时候可以从这开始分割加密（解密的时候从128开始解密即可）
     * @Add 乐杨俊 leyangjun
     * @param String $content 待加密内容
     * @param String $path_rsa_public_key RSA公钥路径(绝对)
     */
    public function superLongPublicKeyEncrypt($content, $rsaPublicKey, $choicePath = true, $withBase64 = false)
    {
        if ($choicePath) {
            $pubKeyId = $this->setSPhptandardFormat($rsaPublicKey);
            //$pubKeyId = openssl_pkey_get_public($rsaPublicKey);//绝对路径读取
            if (!$pubKeyId) {
                die('$pu_key 格式不正确');
            }
        } else {
            $pubKeyId = $rsaPublicKey;//公钥
        }

        $result = '';
        $data = str_split($content, self::RSA_ENCRYPT_BLOCK_SIZE);
        foreach ($data as $block) {
            openssl_public_encrypt($block, $dataEncrypt, $pubKeyId, OPENSSL_PKCS1_PADDING);
            $result .= $dataEncrypt;
        }

        if ($withBase64) {
            return base64_encode($result);
        } else {
            return $result;
        }
    }


    /**
     * 超长私钥解密（128开始截取解密）
     * @param $content 待解密串
     * @param $rsaPrivateKey 私钥|绝对路径
     * @param bool|true $choicePath
     * @param bool|false $withBase64
     * @return bool|string
     */
    public static function superLongPrivateKeyEncrypt($content, $rsaPrivateKey, $choicePath = true, $withBase64 = false)
    {
        if ($choicePath) {
            $priKeyId = openssl_pkey_get_private($rsaPrivateKey);//绝对路径
        } else {
            $priKeyId = $rsaPrivateKey;//私钥
        }

        if ($withBase64) {
            $content = base64_decode($content);
        }

        $result = '';
        $data = str_split($content, self::RSA_DECRYPT_BLOCK_SIZE);
        foreach ($data as $block) {
            openssl_private_decrypt($block, $dataDecrypt, $priKeyId, OPENSSL_PKCS1_PADDING);
            $result .= $dataDecrypt;
        }

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }
}

// $app =  '{"pay_amount":"11","pay_applydate":"2019-12-26 12:22:36","pay_bankcode":"921","pay_callbackurl":"http:\/\/47.56.84.141\/Pay_Charges_callback.html","pay_memberid":10005,"pay_notifyurl":"http:\/\/47.56.84.141\/Pay_Charges_notify.html","pay_orderid":"C20191226122236498329","pay_md5sign":"49412A3F2B94C5D5E44C01F128235BDC","pay_attach":"11","pay_productname":"\u6536\u6b3e","ddlx":1}';
// $app = "abcde123";



// $a = new CryptRsa();

// // $arr = $a->superLongPublicKeyEncrypt($app,$pubKey,true,true);

// // var_dump($arr);
// echo "<br>";
//  $aaa = "KSn4pmIHbrRj5OkRw2K6W8Qd6GP//XOyfBLV1EnOhzewHA2oqVJIV8v/B5AnlWc+32OkATCLsfFwc5xMcBnZhOsV5StCTWUOW04FRrfd4ySWozm1PUXS3R5rloDvZN8K2IbxvJ3nbkREiulfMI61zKcYEPUdRq/kSGiHjy++CbA=";
// $arr1 = $a->superLongPrivateKeyEncrypt($aaa,$PRIVATE_KEY,true,true);
// var_dump($arr1);die;
// $json = json_decode($arr1,true);

// var_dump($json);