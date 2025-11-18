<?php

namespace app\controller;

use app\BaseController;
use app\model\DLogSms;
use app\model\DUser;
use app\model\SConfigSms;
use app\model\DUserBind;
use sms\SMSFather;

class Sms extends BaseController
{
    //app 登录时候发送短信
    public function dosend() {
        $phone = $this->request->get('phone','','trim');
        if(empty($phone)) {
            return $this->backErr('Mobile number is empty!', 10020);
        }
        $smsModel = new SConfigSms();
        $res = $smsModel->getDataList();
        if(!$res) {
            return $this->backErr('No SMS channel!', 10021);
        }
        $key = 'code:'.$phone;
        $redis = $this->getRedisObj();
        $cache = $redis->get($key);
        if(!empty($cache)) {
            return $this->backErr('SMS sent too frequently!', 10022);
        }
        $code = rand(1000, 9999);
        $phone1 = '+91'.$phone;
        $smslogModel = new DLogSms();
        foreach($res as $v) {
            $p = SMSFather::createInstance($v['classname']);
            if(strtolower($v['classname']) == 'smskmicloudvoice' || strtolower($v['classname']) == 'smskmicloud') {
                $phone1 = '0091'.$phone;
            }
            $ret = $p->work($v, $phone1, $code);
            $status = 1; //成功
            if(isset($ret['code']) && $ret['code'] != '00000' && strtolower($v['classname']) == 'smschuanxin') {
                $status = 0; //接口返回失败
            }
            if(isset($ret['code']) && $ret['code'] != 200 && strtolower($v['classname']) == 'smskmicloudvoice') {
                $status = 0; //接口返回失败
            }
            if(isset($ret['smscode'])) {
                $redis->setex($key, 120, $code);
                unset($ret['smscode']);
            } else {
                $ret['code'] = 500;
                $ret['msg'] = 'Sending failed, please contact customer service';
            }

            $data = [
                'uid' => $this->uid,
                'phone' => $phone,
                'remark' => json_encode($ret),
                'title' => $v['classname'], //通道名
                'status' => $status,
                'create_time' => time(),
            ];
            $smslogModel->save($data);
            if(isset($ret['result'])) {
                unset($ret['result']);
            }
            return $this->backSucc($ret);
        }
    }

    //短信发送
    public function index() {
        $phone = $this->request->get('phone','');
        $uid = $this->request->get('uid', 0);
        if(empty($phone)) {
            return $this->backErr('Mobile number is empty!', 10020);
        }
        $isexists = false;
        if($this->uid) {
            $userinfo = DUser::where('uid', $this->uid)->field(["uid"])->find();
            if($userinfo) {
                $isexists = true;
            }
        }
        $count = DUserBind::where('unionid', "{$phone}")->count();
        if($count >0 ) {
            return $this->backErr('This Mobile number has been bound to other player.', 503);
        }

        $smsModel = new SConfigSms();
        $res = $smsModel->getDataList();
        if(!$res) {
            return $this->backErr('No SMS channel!', 10021);
        }
        $redis = $this->getRedisObj();
        $code = rand(1000, 9999);
        $phone1 = '+91'.$phone;
        $smslogModel = new DLogSms();
        foreach($res as $v) {
            $p = SMSFather::createInstance($v['classname']);
            if(strtolower($v['classname']) == 'smskmicloudvoice') {
                $phone1 = '0091'.$phone;
            }
            $ret = $p->work($v, $phone1, $code);
            if(isset($ret['smscode'])) {
                $redis->setex('code:'.$phone, 120, $code);
            }
            $data = [
                'uid' => $uid,
                'phone' => $phone,
                'remark' => json_encode($ret),
                'create_time' => time()
            ];
            $smslogModel->save($data);
            if(!$isexists) {
                unset($ret['smscode']);
            }
            return $this->backSucc($ret);
        }
    }
}