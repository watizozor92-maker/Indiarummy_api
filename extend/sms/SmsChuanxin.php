<?php
namespace sms;
//传信云

use think\facade\Log;

class SmsChuanxin extends SMSFather  {

    public function work($config, $phone, $code) {
        $timestamp = msectime();
        $reqdata = array(
            "appkey" => $config['accesskey'],
            "appsecret" => $config['secretkey'],
            "appcode" => $config['code'],
            "phone" => $phone,
            "msg" => sprintf("Your OTP is %s", $code),
            'timestamp' => $timestamp,
        );
        $str = $config['accesskey'] . $config['secretkey'] . $timestamp;
        $reqdata['sign'] = strtolower(md5($str));
        Log::channel('sms')->record(json_encode($reqdata));
        Log::channel('sms')->record($config['url']);
        $back = curlGet($config['url'] .'?'.http_build_query($reqdata));
        Log::channel('sms')->record($back);
        $back = json_decode($back, true);
        if($back['code'] == '00000') {
            $back['smscode'] = $code;
        }
        return $back;
    }
}

/**

$phone = isset($_REQUEST['phone']) ? trim($_REQUEST['phone']) : '';
if(empty($phone)) {
$ret = ['code'=>10020, 'msg' => '手机号不能为空'];
echo json_encode($ret);
exit;
}

$code = rand(1000, 9999);
$timestamp = msectime();
$reqdata = array(
"appkey" => SMS2_APP_KEY,
"appsecret" => SMS2_APP_SECRET,
"appcode" => SMS2_APP_CODE,
"phone" => $phone,
"msg" => sprintf("Your OTP is %s", $code),
'timestamp' => $timestamp,
);
$str = SMS2_APP_KEY . SMS2_APP_SECRET . $timestamp;
//
//echo '签名前的串:'.PHP_EOL;
//echo 'md5str:' . $str . PHP_EOL;
$reqdata['sign'] = strtolower(md5($str));

//echo PHP_EOL;
//echo 'req data:' . PHP_EOL;
//var_dump($reqdata);
//
//echo PHP_EOL;
//echo 'api:' .PHP_EOL;
////echo json_encode($reqdata) . PHP_EOL;
//
//echo SMS2_API .PHP_EOL;

$back = curlGet(SMS2_API.'?'.http_build_query($reqdata));

$back = json_decode($back, true);
if($back['code'] == '00000') {
$back['smscode'] = $code;
}

echo json_encode($back);
 */