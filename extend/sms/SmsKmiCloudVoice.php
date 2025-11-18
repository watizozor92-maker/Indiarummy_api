<?php

namespace sms;
//kmicloud 语音验证码
use think\facade\Log;

class SmsKmiCloudVoice
{
    public function work($config, $phone, $code) {

        $reqdata = array(
            "accessKey" => $config['accesskey'],
            "secretKey" => $config['secretkey'],
            "callee" => $phone,
            'verifyCode' => $code,
        );
        Log::channel('sms')->record(json_encode($reqdata));
        Log::channel('sms')->record($config['url']);
        $back = curlPostJosn(trim($config['url']), $reqdata);
        // var_dump($back);
        Log::channel('sms')->record($back);

        $back = json_decode($back, true);
        $back['voice'] = 1;
        if($back['code'] == '200') {
            $back['smscode'] = $code;
        }
        return $back;
    }
}