<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use app\model\DUser;
use app\model\DUserRecharge;
use app\model\SPayBank;
use app\model\SPayCfgChannel;
use app\model\SPayDiscount;
use app\model\SPayGroup;
use app\model\SPayCfgOther;
use app\Request;
use think\facade\Config;
use think\facade\Log;

class Order extends BaseController
{
    public function page() {
        
    }

    
     //玩家在线支付, 返回订单号和支付链接地址 xiaoka
     public function onlinePay() {
        $chanId = $this->request->post('id', 0);
        $amount = $this->request->post('amount',0);
        $pic = $this->request->post('pic','');
        $goodsType = $this->request->post('goodsType', 1);

        $customerName = $this->request->post('name', '');
        $customerEmail = $this->request->post('email', '');
        $customerPhone = $this->request->post('phone', '');
        writelog($customerPhone, 'order_pay');
        if(!$customerName || !$customerEmail  || !$customerPhone ) { // 必填信息 
            return $this->backErr('please check Name , Email , Phone.', 10010);
        }

        if(!$chanId || $amount <=0) {
            return $this->backErr('Order amount must be greater than 0.', 10010);
        }
        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin"])->find();
        if(!$userinfo) {
            return $this->backErr('userinfo err!', 504);
        }
        $channel = SPayCfgChannel::find($chanId);
        if(!$channel || $channel['status']!= 1) {
            return $this->backErr('This payment method is not available now,Please choose other payment methods to pay!', 501);
        }
        $paycfg = SPayCfgOther::find($channel['otherid']);
        if(!$paycfg) {
            return $this->backErr('This payment method is not available now,Please choose other payment methods to pay(2)!', 501);
        }
        $groupInfo = SPayGroup::find($channel['groupid']);

        $nowtime = time();
        $orderid = makeOrderId();
        $tax = floatval($amount * $channel['taxrate']);
        $info = $this->getMaxDiscount($channel, $amount, $groupInfo['category']);
        $data = [
            'orderid' => $orderid,
            'uid' => $this->uid,
            'playername' => $userinfo['playername'],
            'count' => $amount,
            'planid' => $info['planid'],
            'before_coin' => $userinfo['coin'],
            'svip' => $userinfo['svip'],
            'channelid' => $chanId, //支付通道id
            'otherid' => $paycfg['id'],
            'groupid' => $channel['groupid'],
            'tax' => $tax,
            'pic' => $pic,
            'subjectid' => $info['subject'], //交易科目
            'create_time' => $nowtime,
            'status' => 0,
            'type' => $info['category'], //分类
            'disrate' => $info['disrate'], //优惠比例(默认按照每次都有优惠计算)
            'discoin' => $info['discoin'], //固定优惠金额
            'rate' => $info['rate'], //分成比例或者固定分成金额
            'category' => $groupInfo['category'], //支付分组类型(线上，线下等)
        ];

        $chargeModel = new DUserRecharge();
        $ret = $chargeModel->save($data);
        if(!$ret) {
            return $this->backErr('order failed!', 502);
        }

        $pay_applydate = date("Y-m-d H:i:s");  //订单时间
        $native = array(
            // "pay_memberid" => $paycfg['memberid'],
            "merchantOrderNo" => $orderid,  // 商户订单号
            "amount" => $amount,   // 付款金额
            "customerName" => $customerName, // 客户姓名
            "customerEmail" => $customerEmail, // 客户邮箱
            "customerPhone" => $customerPhone, // 客户手机
            
            // "customerName" => $pay_applydate,
            // "pay_bankcode" => trim($channel['code']),
//            "pay_bankcode" => Config::get('pay.pay_bank_code'),
//            "pay_notifyurl" => $this->getCurUrl() .'/order/notify', //服务端返回地址
//            "pay_callbackurl" => $this->getCurUrl() .'/order/page', //页面跳转返回地址
            "notifyUrl" => trim($paycfg['callback']), // 传递时以该 URL 为准，不传递时使用后台配置的 URL
            // "pay_callbackurl" => trim($paycfg['pageback']), //页面跳转返回地址 
        );

        $jsoncode = json_encode($native);
        writelog($jsoncode, 'order_pay');

        $iv = trim($paycfg['pub_key']); // 公钥, 存储偏移量
        $key = trim($paycfg['private_key']); // 私钥 ,存储密钥


        // Log::write('pubkey:' . $pubKey);
        $a= new \org\AESCrypto($key,$iv);
        $arr = $a->encrypt($jsoncode);  // 加密

        $data = [];
        $data['data'] = $arr;
        $header = array('Content-Type: application/json;charset=UTF-8');
        array_push( $header ,  'merchant_key: ' . $paycfg['memberid'] );  // 系统方提供的 merchant_key 就是商户号
        //$result = curlPost(Config::get('pay.order_url'), $data, 300, $header);
        // Log::write('order url:'. $paycfg['apiurl']);
        writelog($data, 'order_pay');
	writelog(json_encode($data), 'order_pay');
        //writelog('---------------', 'order_pay');
        //writelog($paycfg['apiurl'] . '/payment/init' , 'order_pay');
$url = $paycfg['apiurl'].'/payment/init';
if (filter_var($url, FILTER_VALIDATE_URL) === false) {
    writelog('URL格式错误: ' . $url, 'order_pay');
}
        $result = curlPost($paycfg['apiurl'].'/payment/init', $data , 300, $header , 'json');
        writelog('请求结束', 'order_pay');
        writelog('返回结果:[' . $result . ']' , 'order_pay');
        if(!$result) { // 失败则删除订单
            // DUserRecharge::where('orderid', '=', "{$orderid}")->delete();
            return $this->backErr('Network error, please try again later!', 502);
        }
        $result = json_decode($result, true);
        // {"code":0,"msg":"success","data":{"status":"PENDING","orderNo":"20251009145331124919","paymentLinkUrl":"https://paytm-upi-com.s3.ap-south-1.amazonaws.com/pay/pspupi_b1/pay.html?orderId=20251009145331124919","message":"","blackListMatchingInfo":null},"success":true}
	writelog('josn转数组完成', 'order_pay');
	writelog($result, 'order_pay');
        if($result['data']['status'] == 'PENDING') {
            $ret = ['payurl'=> $result['data']['paymentLinkUrl'], 'orderid'=>$orderid];
            return $this->backSucc($ret);
        } else {
//            DUserRecharge::where('orderid', '=', "{$orderid}")->delete();
        }

        return $this->backSucc(['orderid'=>$orderid], 'This payment method is not available now,Please choose other payment methods to pay(3)!', 10011);
    }

    //下单支付
    public function pay() {
        $chanId = $this->request->post('id', 0);
        $amount = $this->request->post('amount',0);
        $pic = $this->request->post('pic','');
        $goodsType = $this->request->post('goodsType', 1);
        if(!$chanId || $amount <=0) {
            return $this->backErr('Order amount must be greater than 0.', 10010);
        }
        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin"])->find();
        if(!$userinfo) {
            return $this->backErr('userinfo err!', 504);
        }
        $channel = SPayCfgChannel::find($chanId);
        if(!$channel || $channel['status']!= 1) {
            return $this->backErr('This payment method is not available now,Please choose other payment methods to pay!', 501);
        }
        $paycfg = SPayCfgOther::find($channel['otherid']);
        if(!$paycfg) {
            return $this->backErr('This payment method is not available now,Please choose other payment methods to pay(2)!', 501);
        }
        $groupInfo = SPayGroup::find($channel['groupid']);

        $nowtime = time();
        $orderid = makeOrderId();
        $tax = floatval($amount * $channel['taxrate']);
        $info = $this->getMaxDiscount($channel, $amount, $groupInfo['category']);
        $data = [
            'orderid' => $orderid,
            'uid' => $this->uid,
            'playername' => $userinfo['playername'],
            'count' => $amount,
            'planid' => $info['planid'],
            'before_coin' => $userinfo['coin'],
            'svip' => $userinfo['svip'],
            'channelid' => $chanId, //支付通道id
            'otherid' => $paycfg['id'],
            'groupid' => $channel['groupid'],
            'tax' => $tax,
            'pic' => $pic,
            'subjectid' => $info['subject'], //交易科目
            'create_time' => $nowtime,
            'status' => 0,
            'type' => $info['category'], //分类
            'disrate' => $info['disrate'], //优惠比例(默认按照每次都有优惠计算)
            'discoin' => $info['discoin'], //固定优惠金额
            'rate' => $info['rate'], //分成比例或者固定分成金额
            'category' => $groupInfo['category'], //支付分组类型(线上，线下等)
        ];

        $chargeModel = new DUserRecharge();
        $ret = $chargeModel->save($data);
        if(!$ret) {
            return $this->backErr('order failed!', 502);
        }

        $pay_applydate = date("Y-m-d H:i:s");  //订单时间
        $native = array(
            "pay_memberid" => $paycfg['memberid'],
            "pay_orderid" => $orderid,
            "pay_amount" => $amount,
            "pay_applydate" => $pay_applydate,
            "pay_bankcode" => trim($channel['code']),
//            "pay_bankcode" => Config::get('pay.pay_bank_code'),
//            "pay_notifyurl" => $this->getCurUrl() .'/order/notify', //服务端返回地址
//            "pay_callbackurl" => $this->getCurUrl() .'/order/page', //页面跳转返回地址
            "pay_notifyurl" => trim($paycfg['callback']), //服务端返回地址
            "pay_callbackurl" => trim($paycfg['pageback']), //页面跳转返回地址
        );
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        //$sign = strtoupper(md5($md5str . "key=" . Config::get('pay.md5key')));
        $sign = strtoupper(md5($md5str . "key=" . trim($paycfg['md5key'])));
        $native["pay_md5sign"] = $sign;
        $native['pay_attach'] = "1234|456";
        $native['pay_productname'] ='团购商品';

        $jsoncode = json_encode($native);
        writelog($jsoncode, 'order_pay');

        $pubKey = trim($paycfg['pub_key']);

//        $pubKey = "-----BEGIN PUBLIC KEY-----
//        MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDGXu56p2EJ8NWSW9gcqcm5XTXv
//        bdeYOk6p8/hHwdUgWEXLLmonMM2wVVeQJU12Yfm68iUg5nARvDlJSkpnxZsQvydw
//        3p2f7ssyB2BVyWAZgJtzSpqGWrJ6OUVkPfU8WU507B3p6WIFrnrKds0GDK39Ntpd
//        Izbr1F+4byGsSCC0zQIDAQAB
//-----END PUBLIC KEY-----";

        Log::write('pubkey:' . $pubKey);
        $a = new \org\CryptRsa();
        $arr = $a->superLongPublicKeyEncrypt($jsoncode,$pubKey,true,true); //加密

        $data = [];
        $data['data'] = $arr;
        $header = array('Content-type: application/x-www-form-urlencoded;charset=UTF-8');
        //$result = curlPost(Config::get('pay.order_url'), $data, 300, $header);
        Log::write('order url:'. $paycfg['apiurl']);
        $result = curlPost($paycfg['apiurl'], $data, 300, $header);
        if(!$result) {
            DUserRecharge::where('orderid', '=', "{$orderid}")->delete();
            return $this->backErr('Network error, please try again later!', 502);
        }
        $result = json_decode($result, true);
        if($result['msg'] == 'success') {
            $ret = ['payurl'=> $result['data']['payQcodeUrl'], 'orderid'=>$orderid];
            return $this->backSucc($ret);
        } else {
//            DUserRecharge::where('orderid', '=', "{$orderid}")->delete();
        }

        return $this->backSucc(['orderid'=>$orderid], 'This payment method is not available now,Please choose other payment methods to pay(3)!', 10011);
    }

    private function getMaxDiscount($chan, $amount, $category) {
        $tmp = $this->getOrdersByGroup();
        $orders = $tmp[0]; //全部订单
        $orders_today = $tmp[1]; //今日订单
        $rate = '0:0:0'; //比例
        $planid = 0;
        $disrate = 0;
        $discoin = 0;
        if($chan['disrate']>0 || $chan['discoin']>0) {
            $disrate = $chan['disrate'];
            $discoin = $chan['discoin'];
            $rate = $chan['rate'];
            $planid = 2;
        }
        $order_key = $chan['groupid'].'_'.$chan['id'].'_'.$category;
        if(!isset($orders_today[$order_key])) {
            if($chan['fd_drate']>0 || $chan['fd_dcoin']>0) {
                $disrate = $chan['fd_drate'];
                $discoin = $chan['fd_dcoin'];
                $rate = $chan['fd_rate'];
                $planid = 3;
            }
        }

        if(!isset($orders[$order_key])) {
            if($chan['f_drate']>0 || $chan['f_dcoin']>0) {
                $disrate = $chan['f_drate'];
                $discoin = $chan['f_dcoin'];
                $rate = $chan['f_rate'];
                $planid = 1;
            }
        }

        if($discoin > 0 && $disrate > 0) { //从优惠比例 或 固定优惠金额中挑选出最大值
            $disCount = floatval($amount*$disrate);
            if($disCount > $discoin) { //比例大优惠金额大，就先采用比例
                $discoin = 0;
            } else {
                $disrate = 0;
            }
        }

        $group = SPayGroup::find($chan['groupid']);
        return [
            'disrate' => $disrate,
            'discoin' => $discoin,
            'rate' => $rate,
            'planid' => $planid,
            'subject' => $group['subject'],
            'category' => $group['category'],
        ];
    }

    //银行转账下单
    public function bank() {
        $chanId = $this->request->post('id', 0);
        $amount = $this->request->post('amount');
        $utrnum = $this->request->post('utrnum', '');
        $pic = $this->request->post('pic','');
        $rsamount = $this->request->post('rsamount','');
        $drate = $this->request->post('drate', '');
        if(!$chanId || $amount <=0) {
            return $this->backErr('The paid order number is empty or the amount is 0.', 10010);
        }
        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin"])->find();
        if(!$userinfo) {
            return $this->backErr('userinfo err!', 504);
        }
        $chan = SPayBank::find($chanId);
        if(!$chan || $chan['status']!=1) {
            return $this->backErr('This payment method is not available now,Please choose other payment methods to pay!', 501);
        }
        $nowtime = time();
        $orderid =  makeOrderId('payoffline');

        $groupInfo = SPayGroup::find($chan['groupid']);
        $info = $this->getMaxDiscount($chan, $amount,$groupInfo['category']);
        $data = [
            'orderid' => $orderid,
            'uid' => $this->uid,
            'playername' => $userinfo['playername'],
            'count' => $amount,
            'planid' => $info['planid'],
            'before_coin' => $userinfo['coin'],
            'svip' => $userinfo['svip'],
            'channelid' => $chanId,
            'groupid' => $chan['groupid'],
            'pic' => $pic,
            'subjectid' => $info['subject'],
            'create_time' => $nowtime,
            'status' => 0,
            'type' => $info['category'],
            'disrate' => $info['disrate'], //优惠比例
            'discoin' => $info['discoin'], //固定优惠金额
            'rate' => $info['rate'], //分成比例或者固定分成金额
            'utrnum' => $utrnum,
            'rsamount' => $rsamount,
            'drate' => $drate,
            'category' => $groupInfo['category'],
        ];

        $chargeModel = new DUserRecharge();
        $ret = $chargeModel->save($data);
        if(!$ret) {
            return $this->backErr('Order failed!', 502);
        }
        $retData = [
            'orderid' => $orderid
        ];
        return $this->backSucc($retData, 'Order succ');
    }

    //upi下单
    public function upi() {
        $chanId = $this->request->get('id', 0);
        $amount = $this->request->post('amount');
        $pic = $this->request->post('pic','');
        if(!$chanId || $amount <=0) {
            return $this->backErr('The paid order number is empty or the amount is 0.', 10010);
        }
        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin"])->find();
        if(!$userinfo) {
            return $this->backErr('Userinfo err!', 504);
        }
        $chan = SPayBank::find($chanId);
        if(!$chan || $chan['status']!=1) {
            return $this->backErr('This payment method is not available now,Please choose other payment methods to pay!', 501);
        }
        $nowtime = time();
        $orderid =  makeOrderId();

        $groupInfo = SPayGroup::find($chan['groupid']);
        $info = $this->getMaxDiscount($chan, $amount, $groupInfo['category']);
        $data = [
            'orderid' => $orderid,
            'uid' => $this->uid,
            'playername' => $userinfo['playername'],
            'count' => $amount,
            'planid' => $info['planid'],
            'before_coin' => $userinfo['coin'],
            'svip' => $userinfo['svip'],
            'channelid' => $chanId,
            'groupid' => $chan['groupid'],
            'pic' => $pic,
            'subjectid' => $info['subject'],
            'create_time' => $nowtime,
            'status' => 0,
            'type' => $info['category'],
            'disrate' => $info['disrate'], //优惠比例
            'discoin' => $info['discoin'], //固定优惠金额
            'rate' => $info['rate'],
            'category' => $groupInfo['category'],
        ];

        $chargeModel = new DUserRecharge();
        $ret = $chargeModel->save($data);
        if(!$ret) {
            return $this->backErr('order failed!', 502);
        }
        $retData = [
            'orderid' => $orderid
         ];
        return $this->backSucc($retData, 'order succ');
    }

    //支付成功异步通知
    public function notify() {
        $data = file_get_contents("php://input");
        file_put_contents('/tmp/pay.log', date('Ymd/H:i:s').json_encode($data).PHP_EOL, FILE_APPEND);
        //基础参数校验
        $memberid = $this->request->post('memberid');
        $orderid = $this->request->post('orderid');
        $amount = $this->request->post('amount');
        $transaction_id = $this->request->post('transaction_id');
        $returnArray = array( // 返回字段
            "memberid" =>  $memberid, // 商户ID
            "orderid"  =>  $orderid, // 订单号
            "amount"   =>  $amount, // 交易金额
            "datetime" =>  $this->request->post('datetime'), // 交易时间
            "transaction_id" => $transaction_id, // 支付流水号
            "returncode"     => $this->request->post('returncode'),
        );
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $nowtime = time();
        $this->addDrawOrPayLog($orderid, $nowtime, 'notify data:'.json_encode($data), 1);
        $order = DUserRecharge::where([['orderid','=', $orderid]])->find(); //订单信息
        if(!$order || empty($order)) {
            $str = date('YmdHis') . "订单号：". $orderid . ' 找不到订单:'.$transaction_id;
            Log::write($str, 'error');
            $this->addDrawOrPayLog($orderid, $nowtime, '找不到订单', 1);
            exit('Fail');//找不到订单信息
        }
        $channel = SPayCfgChannel::where([['id','=',$order['channelid']]])->find();//找到支付通道
        if(!$channel || empty($channel)) {
            $str = date('YmdHis') . "订单号：". $orderid . ' 找不到支付通道:'.$order['channelid'];
            Log::write($str, 'error');
            $this->addDrawOrPayLog($orderid, $nowtime, '找不到支付通道', 1);
            exit('Fail'); //找不到订单对应的支付通道
        }
        $md5key = Config::get('pay.md5key');
        $payother = SPayCfgOther::where([['id', '=', $channel['otherid']]])->find();
        if(!$payother || empty($payother)) {
            $str = date('YmdHis') . "订单号：". $orderid . ' 通道id:'.$order['channelid'] . ' 找不到第3方信息';
            Log::write($str, 'error');
            $this->addDrawOrPayLog($orderid, $nowtime, '找不到第3方信息', 1);
            exit('Fail'); //找不到订单对应的支付通道
        } else {
            $md5key = $payother['md5key'];
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));
//        $sign = $this->request->post("sign");
        if ($sign == $this->request->post("sign") && ($this->request->post("returncode") == "00")) {
            $nowtime = time();
            $order_time = $order['create_time'];
            if(is_string($order_time)) {
                $order_time = strtotime($order_time);
            }
            if(($nowtime - $order_time) > 86400) {
                $str = date('YmdHis') . "通知校验成功！但是超时1天了，不让通过,订单号：". $orderid . ' agentno:'.$transaction_id;
                Log::write($str, 'notice');
                $this->addDrawOrPayLog($orderid, $nowtime, '通知校验成功, 但超时1天了，不让通过', 1);
                exit("Fail");
            }

            $str = date('YmdHis') . "通知校验成功！订单号：". $orderid;
            $this->addDrawOrPayLog($orderid, $nowtime, '通知校验成功', 1);
            Log::write($str, 'notice');
            $redis = $this->getRedisObj();
            $data=[
                'orderid' => $orderid,
                'amount'  => $amount,
                'agentno' => $transaction_id,
            ];
            $queryString = http_build_query($data);
            $redis->lPush(Config::get('pay.queue_order'), $queryString);
            $this->addDrawOrPayLog($orderid, $nowtime, '校验通过，成功进入发货队列', 1);
            $res = $redis->zAdd('order_delay_queue', ($nowtime+60), $orderid);
            if ($res == 0) {
                $this->addDrawOrPayLog($orderid, $nowtime, '加入延迟检查队列失败,已经加入了', 1);
            }

            Log::write("交易入队列成功！订单号：".$orderid, 'notice');
            $this->addDrawOrPayLog($orderid, $nowtime, '校验通过，成功进入发货队列', 1);
            exit("OK");
        }
        $this->addDrawOrPayLog($orderid, $nowtime, '签名验证失败', 1);
        exit('Fail');
    }


    
    // 在线支付成功后, 三方异步通知我们
    public function notifyForOnlinePay() {
        $data = file_get_contents("php://input");
        file_put_contents('/tmp/pay.log', date('Ymd/H:i:s').json_encode($data).PHP_EOL, FILE_APPEND);
        //$data = json_decode($data, true);
// {\"data\":{\"amount\":\"300.00\",\"customerEmail\":\"1361171234@gmail.com\",\"customerPhone\":\"6876543210\",\"merchantOrderNo\":\"202510091713000152985098\"
//,\"message\":\"SUCCESS\",\"orderNo\":\"20251009171300306175\",\"processedTime\":\"2025-10-09 17:16:32\",\"status\":\"SUCCESS\",\"transferMode\":\"\",\"type\":\"payment\",\"vpa\":\"\"}
//,\"complaint\":{},\"signature\":\"4ce9808c2c80feca50c49cfc33583b85\",\"signature_n\":\"18dcafedd3ec3834bea2badf349289a4\"}"
        
       // exit('{"code":200}');
        //基础参数校验
        $data = $this->request->post('data','');
        $signature_n = $this->request->post('signature_n',''); // signature_n  验签( 新商户请使用此验签)
        file_put_contents('/tmp/pay.log', date('Ymd/H:i:s').json_encode($data) . '|' .$signature_n .PHP_EOL, FILE_APPEND);
        if(!$data || !$signature_n){
                file_put_contents('/tmp/pay.log', date('Ymd/H:i:s').' data or signature_n is null.'.PHP_EOL, FILE_APPEND);
                exit("Fail");
        }

        $message = $data['message']; // 失败原因 
        $amount = $data['amount']; // 支出金额
        $status = $data['status']; // 支付状态
        $orderid = $data['merchantOrderNo']; // 商户订单号
        $orderNo = $data['orderNo']; // 流水号
        $returnArray = array( // 返回字段
            "orderid"  =>  $orderid, // 订单号
            "amount"   =>  $amount, // 交易金额
            "datetime" =>  $data['processedTime'], // 交易时间 , 成功时间
            "transaction_id" => $orderNo, // 支付流水号
            "returncode"     => $status,
        );

        // 检验是否success状态
	if('SUCCESS' != $status){
		file_put_contents('/tmp/pay.log', date('Ymd/H:i:s').'状态为'.$status.PHP_EOL, FILE_APPEND);
        exit('{"code":200}');
	}
        
        // MD5 加密, 'merchantKey'+ message+ amount+ status+ merchantOrderNo+ orderNo+ aeskey
        $md5str = 'mqqMkcSD0wMMvi8I'. $message . $amount . $status . $orderid . $orderNo . '7pz28mE+C0jlonNF';
        $sign =  md5($md5str);
        if ($sign != $signature_n) {
            // $str = date('YmdHis') . "订单号：". $orderid . ' 签名验证失败:'.$transaction_id;
            // Log::write($str, 'error');
            file_put_contents('/tmp/pay.log', date('Ymd/H:i:s'). ' 签名验证失败. 签名后$sign:' . $sign . ' | $signature_n' . $signature_n . PHP_EOL, FILE_APPEND);
            $this->addDrawOrPayLog($orderid, $nowtime, '签名验证失败', 1);
            exit('Fail'); // 签名错误
        }

        $nowtime = time();
        $this->addDrawOrPayLog($orderid, $nowtime, 'notify data:'.json_encode($data), 1);
        $order = DUserRecharge::where([['orderid','=', $orderid]])->find(); //订单信息
        if(!$order || empty($order)) {
            $str = date('YmdHis') . "订单号：". $orderid . ' 找不到订单:'.$transaction_id;
            Log::write($str, 'error');
            $this->addDrawOrPayLog($orderid, $nowtime, '找不到订单', 1);
            exit('Fail');//找不到订单信息
        }
        $channel = SPayCfgChannel::where([['id','=',$order['channelid']]])->find();//找到支付通道
        if(!$channel || empty($channel)) {
            $str = date('YmdHis') . "订单号：". $orderid . ' 找不到支付通道:'.$order['channelid'];
            Log::write($str, 'error');
            $this->addDrawOrPayLog($orderid, $nowtime, '找不到支付通道', 1);
            exit('Fail'); //找不到订单对应的支付通道
        }
        // $md5key = Config::get('pay.md5key');
        $payother = SPayCfgOther::where([['id', '=', $channel['otherid']]])->find();
        if(!$payother || empty($payother)) {
            $str = date('YmdHis') . "订单号：". $orderid . ' 通道id:'.$order['channelid'] . ' 找不到第3方信息';
            Log::write($str, 'error');
            $this->addDrawOrPayLog($orderid, $nowtime, '找不到第3方信息', 1);
            exit('Fail'); //找不到订单对应的支付通道
        } 


        $nowtime = time();
        $order_time = $order['create_time'];
        if(is_string($order_time)) {
            $order_time = strtotime($order_time);
        }
        if(($nowtime - $order_time) > 86400) {
            $str = date('YmdHis') . "通知校验成功！但是超时1天了，不让通过,订单号：". $orderid . ' agentno:'.$orderNo;
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $nowtime, '通知校验成功, 但超时1天了，不让通过', 1);
            exit("Fail");
        }

        $str = date('YmdHis') . "通知校验成功！订单号：". $orderid;
        Log::write($str, 'notice');
        $this->addDrawOrPayLog($orderid, $nowtime, '通知校验成功', 1);
        $redis = $this->getRedisObj();
        $data=[
            'orderid' => $orderid,
            'amount'  => $amount,
            'agentno' => $orderNo,
        ];
        $queryString = http_build_query($data);
        $redis->lPush(Config::get('pay.queue_order'), $queryString); 
        $this->addDrawOrPayLog($orderid, $nowtime, '校验通过，更新玩家游戏分', 1);

        // 根据uid更新用户的游戏分数
	$amount = floatval($amount);
        DUser::where('uid', $order['uid'])->inc ('coin', $amount )->update();

        // 0:刚创建 1:处理中 2：支付成功 3:人工到账
        DUserRecharge::where('orderid', $orderid)->update(['status' => 2]);

        $res = $redis->zAdd('order_delay_queue', ($nowtime+60), $orderid);
        if ($res == 0) {
            $this->addDrawOrPayLog($orderid, $nowtime, '加入延迟检查队列失败,已经加入了', 1);
        }

	// 更新redis中的游戏分,例如 hget d_user:12948 coin
	$coinRedis = $redis->hGet('d_user:' . $order['uid'] , 'coin');
        $redis->hSet('d_user:' . $order['uid'] , 'coin', $coinRedis + $amount);


        Log::write("交易入队列成功！更新玩家游戏分和订单状态！订单号：".$orderid, 'notice');
        $this->addDrawOrPayLog($orderid, $nowtime, '校验通过，成功进入发货队列,更新订单状态', 1);
        // exit("OK");
        exit('{"code":200}'); // 返回第三方支付

    }


    //去上游平台查单
    public function query() {
        $orderid = $this->request->get('orderid', '');
        if(empty($orderid)) {
            return $this->backErr('Orderid is empty!', 10010);
        }
        $native = array(
            "pay_memberid" => Config::get('pay.memberid'),
            "pay_orderid" => $orderid,
        );
        ksort($native);
        $md5str = "";
        foreach ($native as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . Config::get('pay.md5key')));
        $native["pay_md5sign"] = $sign;

        $header = array('Content-type: application/x-www-form-urlencoded;charset=UTF-8');
        $result = curlPost(Config::get('pay.query_url'), $native, 10, $header);
//{
//    "memberid": "10201",
//    "orderid": "E20220914053923642044",
//    "amount": "100.0000",
//    "time_end": "1970-01-01 05:30:00",
//    "transaction_id": "20220914110920565548",
//    "returncode": "00",
//    "trade_state": "NOTPAY",
//    "sign": "0F3A91DBCDF5200B45189701FB69DDAD"
//}
        $result = json_decode($result, true);
        if($result['returncode'] == '00' ) {
            $ret = [
                'orderid' => $orderid,
                'amount' => intval($result['amount']),
                'agentno' => $result['transaction_id'], //第3方平台订单号
                'paytime' => $result['time_end'], //支付成功时间
                'state' => $result['trade_state'], // NOTPAY-未支付 SUCCESS已支付
            ];
            return $this->backSucc($ret, 'success', 200);
        }
        return $this->backSucc(['orderid'=>$orderid], $result['msg'], 10011);
    }
}
