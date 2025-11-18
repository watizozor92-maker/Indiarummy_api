<?php

namespace sms;

//kmicloud

use think\facade\Log;

class SmsKmiCloud extends SMSFather  {

    public function work($config, $phone, $code) {

        $reqdata = array(
            "accessKey" => $config['accesskey'],
            "secretKey" => $config['secretkey'],
            "from" => 'OTP',
            "to" => $phone,
            "message" => sprintf("Your OTP is %s", $code),
        );
        // var_dump($reqdata);
        // var_dump($config);
        Log::write('SmsKmiCloud param:'. json_encode($reqdata));
        Log::write('SmsKmiCloud url:'. $config['url']);
        $back = curlPostJosn(trim($config['url']), $reqdata);
        Log::write('SmsKmiCloud back:'. $back);
        $back = json_decode($back, true);
        if($back['code'] == '00000') {
            $back['smscode'] = $code;
        }
        return $back;
    }
}


//echo 'req data:'. PHP_EOL;
//var_dump($reqdata);
//
//echo PHP_EOL;
//echo 'API:'.PHP_EOL;
//echo SMS1_API . PHP_EOL;
//
//echo PHP_EOL;
// $back = curlPostJosn(SMS1_API, $reqdata);
//echo 'return:'.PHP_EOL;

// $back = json_decode($back, true);
// $back['smscode'] = $code;
// echo json_encode($back);