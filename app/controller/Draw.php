<?php

namespace app\controller;

use app\BaseController;
use app\model\DAudit;
use app\model\DKyc;
use app\model\DUser;
use app\model\DUserBank;
use app\model\DLogOrder;
use app\model\DUserDraw;
use app\model\SConfig;
use app\model\SPayCfgDraw;
use app\model\SPayCfgDrawlimit;
use app\model\SPayCfgDrawcom;
use think\facade\Config;
use think\facade\Db;
use think\facade\Env;
use think\facade\Log;
use app\model\SPayCfgChannel;
use app\model\SPayCfgOther;

//提现相关
class Draw extends BaseController
{

    //获取提现限制
    private function getLimitInfo($uid) {
        $mincoin = 0;
        $maxcoin = 10000;
        $times = -1;
        $daycoin = -1;
        $totaltimes =  -1;
        $totalcoin = -1;
        $interval = 0;
        $model = new SPayCfgDrawlimit();
        $userinfo = DUser::where('uid',$this->uid)->find();
        $datalist = $model->getDataBySvip($this->uid, $userinfo['svip']);
        if(!empty($datalist)) {
            foreach($datalist as $row) {
//                if($mincoin == 0) {
//                    $mincoin = $row['mincoin'];
//                }
//                if($row['mincoin'] < $mincoin) {
//                    $mincoin = intval($row['mincoin']);
//                }
//                if($row['maxcoin'] > $maxcoin) {
//                    $maxcoin = intval($row['maxcoin']);
//                }
                if($row['times'] > $times) {
                    $times = intval($row['times']);
                }
                if($row['interval'] > $interval) {
                    $interval = intval($row['interval']);
                }
                if($row['daycoin'] > $daycoin) {
                    $daycoin = $row['daycoin'];
                }
                if($row['totaltimes'] > $totaltimes) {
                    $totaltimes = $row['totaltimes'];
                }
                if($row['totalcoin'] > $totalcoin) {
                    $totalcoin = $row['totalcoin'];
                }
            }
        }
        return [
            'mincoin' => $mincoin,
            'maxcoin' => $maxcoin,
            'times' => $times,
            'interval' => $interval,
            'daycoin' => $daycoin, //当天最大金额
            'totaltimes' => $totaltimes, //总提现次数
            'totalcoin' => $totalcoin, //总提现金额
        ];
    }

    //获取draw相关的
    public function index() {
        $usercoins = $this->getUserCoin($this->uid);
        $data['uid']   = $usercoins['uid'];
        $data['dcoin'] = $usercoins['dcoin'];
        $poptype = $this->request->get('drawpop', 0); //提醒弹框
        $svip = $usercoins['svip'];
        if($svip <= 0) {
            $svip = 0;
        }

        $model = new SPayCfgDraw();
        $list = $model->where([['type','=',1]])->select()->toArray();
        if(empty($list)) {
            return $this->backSucc($data, 'No Channel.');
        }
        $mincoin = 0;
        $maxcoin = 0;
        foreach($list as $row) {
            if($mincoin == 0) {
                $mincoin = $row['mincoin'];
            } else {
                if($row['mincoin'] <= $mincoin) {
                    $mincoin = intval($row['mincoin']);
                }
            }

            if($maxcoin == 0) {
                $maxcoin = $row['maxcoin'];
            } else {
                if($row['maxcoin'] > $maxcoin) {
                    $maxcoin = intval($row['maxcoin']);
                }
            }
        }

        $data['limit'] = 0; //提现次数是否被限制
        $data['mincoin'] = $mincoin; //最低
        $data['maxcoin'] = $maxcoin; //最高
        $res = Db::table('s_config')->field('v')->where('k','=', 'drawmemo')->select()->toArray();
        $memo = '';
        if(!empty($res)) {
            $memo = $res[0]['v'];
        }
        $data['memo'] = $memo;
        $data['popmsg'] = '';
        if(intval($poptype) < 1) {
            $ret = Db::table('s_pay_popup')->field('content')->where('cat','=', 2)->where('status', '=', 1)->select()->toArray();
            if(isset($ret[0])) {
                $data['popmsg'] = $ret[0]['content'];
            }
        }
        $data['customer'] = $this->getEmail(3);
        return $this->backSucc($data);
    }

    //获取用户匹配的在线提现通道
    public function channel() {
        $usercoins = $this->getUserCoin($this->uid);
        $svip = $usercoins['svip'];
        if($svip <= 0) {
            $svip = 0;
        }
        $chanModel = new SPayCfgDrawcom();
        $chans = $chanModel->getDataBySvip($svip);
        if(empty($chans)) {
            return $this->backErr('no data', 502);
        }

        foreach($chans as $key=>$val) {
            $item = [
                'id'    => $val['id'],
                'title' => $val['title'],
                'icon' => Config::get('pay.img_url') . $val['icon'],
                'mincoin' => $val['mincoin'],
                'maxcoin' => $val['maxcoin'],
                'times' => $val['times'],
                'intervel' => $val['intervel'],
            ];
            $datalist[] = $item;
        }

        $retData['list'] = $datalist;
        return $this->backSucc($retData);
    }

    // 新增的提现接口  xiaoka 20251011 
    public function onlineOrder() {
        $bankid = $this->request->param('bankid',0); // 银行账号   选择BANK_TRANSFER时必须填写
        $dcoin = $this->request->param('dcoin', 0); // 提现金额
        $chanid = $this->request->param('chanid', 0); // 渠道id
        $customerName = $this->request->post('name', ''); // 姓名
        $customerEmail = $this->request->post('email', ''); // 邮箱
        $customerPhone = $this->request->post('phone', ''); // 手机号
        $IFSC = $this->request->post('IFSC', ''); //IFSC 选择BANK_TRANSFER时必须填写
        $VPA = $this->request->post('VPA', ''); //VPA 选择UPI时必须填写
        $drawType = $this->request->post('drawType', ''); // 提款类型: BANK_TRANSFER，UPI


        if(!$customerName || !$customerEmail  || !$customerPhone || !$drawType ) { // 必填信息 
            return $this->backErr('please check Name , Email , Phone , DrawType.', 10010);
        }

        if($drawType == 'BANK_TRANSFER' && (!$IFSC || !$bankid) ) { // 选择BANK_TRANSFER时必须填写
            return $this->backErr('please check bank account and IFSC .', 10010);
        }

        if($drawType == 'UPI' && !$VPA ) { // 选择UPI时必须填写 
            return $this->backErr('please check VPA.', 10010);
        }

        if(intval($dcoin) <=100) { // 改为 100  xiaoka 20251012
            return $this->backErr('Withdrawal amount must be greater than 100.', 501);
        }
        if(intval($bankid) <=0 ) {
            return $this->backErr('Please select a withdrawal bankcount.', 502);
        }
        if(intval($chanid) <=0 ) {
            return $this->backErr('Please select a withdrawal channel.', 502);
        }
        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin","ispayer","gamedraw"])->find();
        if(!$userinfo) {
            return $this->backErr('Userinfo Err!', 504);
        }
        //if(intval($userinfo['ispayer']) == 0) { //未充值用户不能超过系统最高金额
        //    $cfg = SConfig::where('k', 'newuser_no_pay_drawlimit')->find();
        //    $maxcoin = intval($cfg['v']);

        //    $haddrawcoin = DUserDraw::where('uid', $this->uid)->where('status','=',2)->sum('coin');
        //    if($maxcoin > 0 && ($dcoin+$haddrawcoin) > $maxcoin) {
        //        return $this->backErr("Withdrawal amount must be less than the maximum withdrawable amount {$maxcoin}.", 501);
        //    }
        //    $doingcnt = DUserDraw::where('uid',$this->uid)->where('status', '=', 0)->count('id');
        //    if($doingcnt >= 1) {
        //        return $this->backErr("You have a withdrawal order under review, please wait patiently.", 501);
        //    }
        //    $donecnt = DUserDraw::where('uid',$this->uid)->where('status', '=', 2)->count('id');
        //    if($donecnt >= 1) {
        //        return $this->backErr("You already have a withdrawal order, please recharge vip.", 501);
        //    }
        //}
        //$usercoins = $this->getUserCoin($this->uid);
        
	if($userinfo['gamedraw'] < $dcoin) {     //  已经有gamedraw可提现金币了, 不知道为什么还要这个,先注释. xiaoka 20251011
            return $this->backErr('Insufficient withdrawal amount, Cannot be greater than ' . $userinfo['gamedraw'], 503);
        }
        $chanModel = new SPayCfgDrawcom();
        $chans = $chanModel->getDataBySvip($userinfo['svip']);
        if(empty($chans)) {
            return $this->backErr('No channel Data', 502);
        }

        $limit = $this->getLimitInfo($this->uid);

        $cnd = [];
        $cnd[] = ['uid', '=', $this->uid];
        $cnd[] = ['status','=', 2];

        $todaytime = strtotime(date('Y-m-d'));
        $cndtoday = $cnd;
        $cndtoday[] = ['create_time','>=', $todaytime];
        $todaytimes = DUserDraw::where($cndtoday)->count(); //today max times
        if($limit['times'] >0 && $todaytimes > $limit['times']) {
            return $this->backErr("Today's withdrawal times has reached the limit({$limit['times']})", 506);
        }
        $todaycoin = DUserDraw::where($cndtoday)->sum('coin'); //today max coin
        if($limit['daycoin'] >0 && $todaycoin >= $limit['daycoin']) {
            return $this->backErr("Today's withdrawal amount has reached the limit({$limit['daycoin']})", 506);
        }
        $alltimes = DUserDraw::where($cnd)->count(); //total max times
        if($limit['totaltimes'] >0 && $alltimes > $limit['totaltimes']) {
            return $this->backErr("Withdrawal times has reached the limit times({$limit['totaltimes']})", 506);
        }
        $allcoin = DUserDraw::where($cnd)->sum('coin');
        if($limit['totalcoin'] >0 && $allcoin >= $limit['totalcoin']) {
            return $this->backErr("Withdrawal Amount has reached the limit coin({$limit['totalcoin']})", 506);
        }

        $lastItem = DUserDraw::where('uid', $this->uid)->order('id','desc')->limit(1)->find();
        $now = time();
        if($lastItem && $limit['interval'] && $lastItem['create_time'] > ($now - $limit['interval']*60)) {
            return $this->backErr('Withdrawals too frequently.', 502);
        }

        // 写入表d_kyc, 因为后面userCenter接口要读取这些信息 xiaoka 20251011
        $dkycData = [
            'uid' => $this->uid,
            'category' => 3,
            'status' => 2,
            // bankid = tonumber(ret[1].bankid or 0),
            'cardnum' => $bankid,  // 银行卡号
            'username' => $customerName, // 姓名

        ];
        $bank = DKyc::create($dkycData);
        $bankid = $bank['id']; // 表d_kyc的id

        $param = [
            'mod' => 'user',
            'act' => 'draw',
            'uid' => $this->uid,
            'coin'=> $dcoin,
            'bankid' => $bankid,
            'chanid' => intval($chanid),
        ];
        // 调用内部接口, 存入提款记录表d_user_draw
        $result = curlGet(Config::get('pay.game_api') .'?'.http_build_query($param));
        
        if($result != 'succ') {
            Log::write("{$this->uid} pay.game_api submit draw order failed, result:" . $result);
            return $this->backErr('Operation failed', 505);
        }
        // 获取刚刚写入的提款单id
        $orderIdAndCoin = DUserDraw::where('uid', $this->uid)->field(["orderid","coin"])->order('id','desc')->limit(1)->find();
        if(!$orderIdAndCoin || intval($dcoin) != intval($orderIdAndCoin['coin']) ) {
            Log::write("{$this->uid} get draw orderid failed");
            return $this->backErr('Operation failed', 505);
        }

        //$channel = SPayCfgChannel::find($chanid);
        //$paycfg = SPayCfgOther::find($channel['otherid']);
	$drawConfig = SPayCfgDrawcom::find($chanid); // 提款的配置表 xiaoka

        // 请求三方支付的代付接口
        $native = array(
            // "pay_memberid" => $paycfg['memberid'],
            "merchantOrderNo" => $orderIdAndCoin['orderid'],  // 商户订单号
            "amount" => $orderIdAndCoin['coin'],   // 支出金额,提款
            "beneficiaryName" => $customerName, // 客户姓名
            "beneficiaryEmail" => $customerEmail, // 客户邮箱
            "beneficiaryPhoneNo" => $customerPhone, // 客户手机
            "transferType" => $drawType ,  // 支付类型 传递以下字符串之  BANK_TRANSFER ， UPI
            "beneficiaryAccount" => $bankid ,  // 银行账号,用户账号
            "beneficiaryIFSC" => $IFSC ,  // 用户 IFSC 支付类型为 BANK_TRANSFER时必传
            "beneficiaryVPA" => $VPA ,  //用户 VPA 支付类型为 UPI 时必传

            "notifyUrl" => trim($drawConfig['queryurl']), // 回调通知地址 传递时以该 URL 为准，不传递时使用后台配置的 URL
            // "pay_callbackurl" => trim($paycfg['pageback']), //页面跳转返回地址 
        );

        $jsoncode = json_encode($native);
        writelog($jsoncode, 'draw_order');
        $iv = trim($drawConfig['publickey']); // 公钥, 存储偏移量
        $key = trim($drawConfig['privatekey']); // 私钥 ,存储密钥


        // Log::write('pubkey:' . $pubKey);
        $a= new \org\AESCrypto($key,$iv);
        $arr = $a->encrypt($jsoncode);  // 加密
        $data = [];
        $data['data'] = $arr;
        $header = array('Content-Type: application/json;charset=UTF-8');
        array_push( $header ,  'merchant_key: ' . $drawConfig['merchant'] );  // 系统方提供的 merchant_key 就是商户号

        writelog($data, 'draw_order');
	    writelog(json_encode($data), 'draw_order');
        
        $result = curlPost($drawConfig['apiurl'].'/payout/init', $data , 300, $header , 'json');
        writelog('返回结果:' . $result, 'draw_order');

        if(!$result) {
            return $this->backErr('Network error, please try again later!', 502);
        }

        $result = json_decode($result, true);
        //  返回示例: 
        writelog('json转数组完成', 'draw_order');
        writelog($result, 'draw_order');

        if($result['data']['status'] == 'ACCEPT') {
            // 根据uid更新用户的游戏分数
            $amount = floatval($dcoin);
            //DUser::where('uid', $this->uid )->dec ('coin', $amount )->update();

            // 更新redis中的游戏分,例如 hget d_user:12948 coin
            $redis = $this->getRedisObj();
            $coinRedis = $redis->hGet('d_user:' . $this->uid, 'coin');
            writelog('coinRedis: ' . $coinRedis, 'draw_order');
            //$redis->hSet('d_user:' . $this->uid , 'coin', $coinRedis - $amount);
            return $this->backSucc();
        }

        //return $this->backSucc();
	return $this->backErr('Operation failed, end.', 505);
    }


    //提现
    public function order() {
        $bankid = $this->request->param('bankid',0);
        $dcoin = $this->request->param('dcoin', 0);
        $chanid = $this->request->param('chanid', 0);
        if(intval($dcoin) <=0) {
            return $this->backErr('Withdrawal amount must be greater than zero.', 501);
        }
        if(intval($bankid) <=0 ) {
            return $this->backErr('Please select a withdrawal bankcount.', 502);
        }
        //if(intval($chanid) <=0 ) {
        //    return $this->backErr('Please select a withdrawal channel.', 502);
        //}
        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin","ispayer","gamedraw"])->find();
        if(!$userinfo) {
            return $this->backErr('Userinfo Err!', 504);
        }
        if(intval($userinfo['ispayer']) == 0) { //未充值用户不能超过系统最高金额
            $cfg = SConfig::where('k', 'newuser_no_pay_drawlimit')->find();
            $maxcoin = intval($cfg['v']);

            $haddrawcoin = DUserDraw::where('uid', $this->uid)->where('status','=',2)->sum('coin');
            if($maxcoin > 0 && ($dcoin+$haddrawcoin) > $maxcoin) {
                return $this->backErr("Withdrawal amount must be less than the maximum withdrawable amount {$maxcoin}.", 501);
            }
            $doingcnt = DUserDraw::where('uid',$this->uid)->where('status', '=', 0)->count('id');
            if($doingcnt >= 1) {
                return $this->backErr("You have a withdrawal order under review, please wait patiently.", 501);
            }
            $donecnt = DUserDraw::where('uid',$this->uid)->where('status', '=', 2)->count('id');
            if($donecnt >= 1) {
                return $this->backErr("You already have a withdrawal order, please recharge vip.", 501);
            }
        }
        $usercoins = $this->getUserCoin($this->uid);
        if($usercoins['dcoin'] < $dcoin) {
            return $this->backErr('Insufficient withdrawal amount.', 503);
        }
        $chanModel = new SPayCfgDrawcom();
        $chans = $chanModel->getDataBySvip($userinfo['svip']);
        if(empty($chans)) {
            return $this->backErr('No channel Data', 502);
        }

        $limit = $this->getLimitInfo($this->uid);
        //$chanInfo = [];
        //foreach($chans as $row) {
        //    if(intval($row['id']) == $chanid) {
        //        $chanInfo = $row;
        //    }
        //}
        //if(empty($chanInfo)) {
        //    return $this->backErr( 'Channel Data Err', 502);
        //}
        //if($chanInfo['mincoin'] > 0 && $dcoin < $chanInfo['mincoin']) {
        //    return $this->backErr('The withdrawal amount is lower than the minimum threshold.', 502);
        //}
        //if($chanInfo['maxcoin'] > 0 && $dcoin > $chanInfo['maxcoin']) {
        //    return $this->backErr('The withdrawal amount is higher than the maximum limit.', 502);
        //}

        $cnd = [];
        $cnd[] = ['uid', '=', $this->uid];
        $cnd[] = ['status','=', 2];

        $todaytime = strtotime(date('Y-m-d'));
        $cndtoday = $cnd;
        $cndtoday[] = ['create_time','>=', $todaytime];
        $todaytimes = DUserDraw::where($cndtoday)->count(); //today max times
        if($limit['times'] >0 && $todaytimes > $limit['times']) {
            return $this->backErr("Today's withdrawal times has reached the limit({$limit['times']})", 506);
        }
        $todaycoin = DUserDraw::where($cndtoday)->sum('coin'); //today max coin
        if($limit['daycoin'] >0 && $todaycoin >= $limit['daycoin']) {
            return $this->backErr("Today's withdrawal amount has reached the limit({$limit['daycoin']})", 506);
        }
        $alltimes = DUserDraw::where($cnd)->count(); //total max times
        if($limit['totaltimes'] >0 && $alltimes > $limit['totaltimes']) {
            return $this->backErr("Withdrawal times has reached the limit times({$limit['totaltimes']})", 506);
        }
        $allcoin = DUserDraw::where($cnd)->sum('coin');
        if($limit['totalcoin'] >0 && $allcoin >= $limit['totalcoin']) {
            return $this->backErr("Withdrawal Amount has reached the limit coin({$limit['totalcoin']})", 506);
        }

        $lastItem = DUserDraw::where('uid', $this->uid)->order('id','desc')->limit(1)->find();
        $now = time();
        if($lastItem && $limit['interval'] && $lastItem['create_time'] > ($now - $limit['interval']*60)) {
            return $this->backErr('Withdrawals too frequently.', 502);
        }




        $where = [];
        $where[] = ['id', '=', $bankid];
        $where[] = ['uid', '=', $this->uid];
        $where[] = ['category', '=', 3];
        $where[] = ['status', '=', 2];

        $bank = DKyc::where($where)->find();
        if(empty($bank)) {
            $bankModel = new DUserBank();
            $bank = $bankModel->getBankinfoById($bankid, $this->uid);
        }
        if(empty($bank)) {
            return $this->backErr('Please select the correct withdrawal method.', 504);
        }
        $param = [
            'mod' => 'user',
            'act' => 'draw',
            'uid' => $this->uid,
            'coin'=> $dcoin,
            'bankid' => $bankid,
            'chanid' => 0,
        ];
        $result = curlGet(Config::get('pay.game_api') .'?'.http_build_query($param));
        if($result == 'succ') {
            return $this->backSucc();
        }
        Log::write("{$this->uid} submit draw order failed:" . $result);
        return $this->backErr('Operation failed', 505);
    }



    //提现异步通知
    public function notify() {
        $data = file_get_contents("php://input");
        file_put_contents('/tmp/draw.log', date('Ymd/H:i:s').json_encode($data).PHP_EOL, FILE_APPEND);
        Log::write(json_encode($data), 'notice');
        //基础参数校验
        $memberid = $this->request->post('memberid');
        $orderid = $this->request->post('orderid');
        $tkmoney =$this->request->post('tkmoney');//交易金额
        $amount = $this->request->post('amount');//实际金额
        $sxfmoney =$this->request->post('sxfmoney'); //手续费
        $status =$this->request->post('status'); //1 待支付 2成功 3失败
        $transaction_id = $this->request->post('transaction_id');
        $returnArray = array( // 返回字段
            "memberid" =>  $memberid, // 商户ID
            "orderid"  =>  $orderid, // 订单号
            "transaction_id" => $transaction_id, // 支付流水号
            "tkmoney" => $tkmoney,
            "sxfmoney" => $sxfmoney,
            "amount"   =>  $amount, // 交易金额
            "datetime" =>  $this->request->post('datetime'), // 交易时间
            "returncode"     => $this->request->post('returncode'),//
            "status" => $status,
        );
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $now_time = time();
        $this->addDrawOrPayLog($orderid, $now_time, 'notify data:' . json_encode($data));

        $order = DUserDraw::where('orderid',$orderid)->find();
        if(!$order) {
            $str = date('YmdHis') . "找不到提现的订单 orderid：" . $orderid;
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '找不到提现的订单');
            echo 'fail';
            exit;
        }
        if($order['status'] == 2 and in_array($order['chanstate'], [2,3])) {
            $str = date('YmdHis') . "此订单已经成功了，无需再处理 orderid：" . $orderid;
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '此订单已经成功了，无需再处理');
            echo 'succ';
            exit;
        }
        $channel = SPayCfgDrawcom::find($order['channelid']);
        if(!$channel) {
            $str = date('YmdHis') . "找不到提现的支付通道信息 orderid：" . $orderid;
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '找不到提现的支付通道信息');
            echo 'fail';
            exit;
        }
        $sign = strtoupper(md5($md5str . "key=" . $channel['secretkey']));
        if ($sign == $this->request->post("sign") && ($this->request->post("returncode") == "00")) {
            $str = date('YmdHis') . "提现通知校验成功！订单号：". $orderid. ' status:'. $status;
            Log::write($str, 'notice');

            $param = [
//                'backcoin' => $amount,
                //'taxthird' => $sxfmoney,
                'chanstate' => $status,
                'notify_time'=> $now_time,
            ];
            $actStatus = 3;
            if(intval($status) == 2) {
                $param['backcoin'] = $amount;
                $actStatus = 2;
            }
            $ret = DUserDraw::where('orderid', $orderid)->update($param);
            if($ret) {
                $param = [
                    'mod' => 'user',
                    'act' => 'drawverify',
                    'uid' => $order['uid'],
                    'coin'=> $order['coin'],
                    'id' => $order['id'],
                    'status' => $actStatus,
                ];
                $result = curlGet(Config::get('pay.game_api') .'?'.http_build_query($param));
                $this->addDrawOrPayLog($orderid, $now_time, '提现订单异步通知处理成功');
                Log::write("提现订单处理成功！订单号：".$orderid, 'notice');
                exit("ok");
            } else {
                Log::write("提现订单处理失败！订单号：".$orderid, 'notice');
                $this->addDrawOrPayLog($orderid, $now_time, '提现订单异步通知处理失败');
                exit("fail");
            }
        } else {
            $this->addDrawOrPayLog($orderid, $now_time, '异步通知签名不对');
            Log::write("签名不对！订单号：".$orderid, 'notice');
            exit("fail");
        }
    }

    //提现异步通知 xiaoka 20251011
    public function notifyForOnlineDraw() {
        $data = file_get_contents("php://input");
        file_put_contents('/tmp/draw.log', date('Ymd/H:i:s').json_encode($data).PHP_EOL, FILE_APPEND);
        Log::write(json_encode($data), 'notice');
        //基础参数校验
        $data = $this->request->post('data','');
        $signature_n = $this->request->post('signature_n',''); // signature_n  验签( 新商户请使用此验签)
        file_put_contents('/tmp/draw.log', date('Ymd/H:i:s').json_encode($data) . '|' .$signature_n .PHP_EOL, FILE_APPEND);
        if(!$data || !$signature_n){
                file_put_contents('/tmp/draw.log', date('Ymd/H:i:s').' data or signature_n is null.'.PHP_EOL, FILE_APPEND);
                exit("Fail");
        }

        $message = $data['message']; // 失败原因 
        $amount = $data['amount']; // 支出金额
        $status = $data['status']; // 支付状态
        $orderid = $data['merchantOrderNo']; // 商户订单号
        $orderNo = $data['orderNo']; // 流水号

        // 检验是否success状态
        if('SUCCESS' != $status){
            file_put_contents('/tmp/draw.log', date('Ymd/H:i:s').'状态为'.$status.PHP_EOL, FILE_APPEND);
            exit('{"code":200}');
        }

        // MD5 加密,  merchantKey + message+ amount+ status+ merchantOrderNo+ orderNo+ aeskey
        $md5str = 'mqqMkcSD0wMMvi8I'. $message . $amount . $status . $orderid . $orderNo . '7pz28mE+C0jlonNF';
        $sign =  md5($md5str);
        if ($sign != $signature_n) {
            file_put_contents('/tmp/draw.log', date('Ymd/H:i:s'). ' 签名验证失败. 签名后$sign:' . $sign . ' | $signature_n' . $signature_n . PHP_EOL, FILE_APPEND);
            $this->addDrawOrPayLog($orderid, $now_time, '异步通知签名不对');
            Log::write("签名不对！订单号：".$orderid, 'notice');
            exit("fail");
        }

        $now_time = time();
        $this->addDrawOrPayLog($orderid, $now_time, 'notify data:' . json_encode($data));
        
        // 根据订单号查询订单
        $order = DUserDraw::where('orderid',$orderid)->find();
        if(!$order) {
            $str = date('YmdHis') . "找不到提现的订单 orderid：" . $orderid;
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '找不到提现的订单');
            echo 'fail';
            exit;
        }
        // chanstate是渠道出款状态,0:待处理 1:出款中 2:已出款 3:出款失败
        // status是订单状态, 订单已经处理过了, 0:待处理 1：处理中  2:已处理 3:拒绝
        if($order['status'] == 2 || in_array($order['chanstate'], [2,3])) { 
            $str = date('YmdHis') . "此订单已经成功了，无需再处理 orderid：" . $orderid;
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '此订单已经成功了，无需再处理');
            //echo 'succ';
            //exit;
	    exit('{"code":200}');
        }

        $channel = SPayCfgDrawcom::find($order['channelid']);
        if(!$channel) {
            $str = date('YmdHis') . "找不到提现的支付通道信息 channelid：" . $order['channelid'];
            Log::write($str, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '找不到提现的支付通道信息');
            echo 'fail';
            exit;
        }

        $str = date('YmdHis') . "提现通知校验成功！订单号：". $orderid. ' status:'. $status;
        Log::write($str, 'notice');

        $param = [
            'chanstate' => 2 ,
            'notify_time'=> $now_time,
        ];
        $param['backcoin'] = $amount;
        $actStatus = 3;
        
        $ret = DUserDraw::where('orderid', $orderid)->update($param);
        if($ret) {
            $param = [
                'mod' => 'user',
                'act' => 'drawverify',
                'uid' => $order['uid'],
                'coin'=> $order['coin'],
                'id' => $order['id'],
                'status' => 2,
            ];
            $result = curlGet(Config::get('pay.game_api') .'?'.http_build_query($param));
            $this->addDrawOrPayLog($orderid, $now_time, '提现订单异步通知处理成功');
            Log::write("提现订单处理成功！订单号：".$orderid, 'notice');
            // 0:待处理 1：处理中  2:已处理 3:拒绝
            DUserDraw::where('orderid', $orderid)->update(['status' => 2]);
            //exit("ok");
            exit('{"code":200}');
        } else {
            Log::write("提现订单处理失败！订单号：".$orderid, 'notice');
            $this->addDrawOrPayLog($orderid, $now_time, '提现订单异步通知处理失败');
            exit("fail");
        }
    }
}
