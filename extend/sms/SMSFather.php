<?php
namespace sms;

use sms\SmsKmiCloud;
use sms\SmsChuanxin;
use sms\SmsKmiCloudVoice;

//短信基类
abstract class SMSFather{

    // 定义抽象方法
    abstract public function work($config, $phone, $code);


    static final function createInstance($jobTitle){
        $job = ucfirst($jobTitle);
        $clsName = "sms\\$job";
        return new $clsName;
    }
}