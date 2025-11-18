<?php

namespace app\controller;

use app\BaseController;
use app\model\DKyc;
use app\model\DUser;
use app\model\DUserBank;
use app\model\DUserBind;
use app\model\SGame;
use \think\facade\Db;

//用户相关信息
class User extends BaseController
{
    static $EMPTY_STR = 'empty';

    public function index() {
        echo 'hello';
    }

    protected function getWeekDayStartTime() {
        $start = strtotime(date('Y-m-d'));
        return ($start - 7*86400);
    }

    //记录列表再不弹出弹框
    public function nevershow(){
        $updata = [
            'nevershowtips' => 1,
        ];
        DUser::where('uid', $this->uid)->update($updata);
        return $this->backSucc([]);
    }

    private function getRechargeMemo() {
        $key = "rechargememo";
        $redis= $this->getRedisObj();
        $cache_str = $redis->get($key);
        if(!empty($cache_str) && $cache_str != self::$EMPTY_STR) {
            return $cache_str;
        }

        $memo = '';
        $res  = Db::table('s_config')->field('v')->where('k','=', 'rechargememo')->select()->toArray();
        if(!empty($res)) {
            $memo = $res[0]['v'];
            $redis->set($key,  $memo , 86400);
        } else {
            $redis->set($key,  self::$EMPTY_STR , 86500);
        }
        return $memo;
    }

    private function getPopMsg($cat) {
        $key = "popmsg_{$cat}";
        $redis= $this->getRedisObj();
        $cache_str = $redis->get($key);
        if(!empty($cache_str) && $cache_str!= self::$EMPTY_STR) {
            return $cache_str;
        }
        $popmsg = '';
        $ret = Db::table('s_pay_popup')->field('content')->where('cat','=', $cat)->where('status', '=', 1)->select()->toArray();
        if(isset($ret[0])) {
            $popmsg = $ret[0]['content'];
            $redis->set($key,  $popmsg , 86400);
        } else {
            $redis->set($key,  self::$EMPTY_STR , 86400);
        }
        return $popmsg;
    }

    //个人余额详情
    public function balance() {
        $usercoins = $this->getUserCoin($this->uid);
        $data['user'] = $usercoins;
        $cointype = $this->request->get('cat', 0);
        $coin = $this->request->get('coin', 0); //此次充值金额
        $poptype = $this->request->get('paypop', 0); //支付弹框
        if(intval($cointype) == 2) {
            $res = $this->getBetCoinList($cointype);
            $data['coins'] = [];
            foreach($res as $k=>$v) {
                $data['coins'][] = $v['amount'];
            }
        }
        if($usercoins['nodislabelid']) {
            $channels = $this->getChannelsByLabel(0, $usercoins['svip'], 0, $coin, $usercoins['nodislabelid']);
        } else {
            $channels = $this->getChannels(0, $usercoins['svip'], 0, $coin);
        }
        $data['channels'] = $channels;

        $redis = $this->getRedisObj();
        $key = 'recharge_balance_tips';
        $cache = $redis->get($key);
        if($cache) {
            $json = json_decode($cache, true);
            $data['memo'] = $json['memo'];
            $data['popmsg'] = $json['popmsg'];
            $data['customer'] = $json['customer'];
        } else {
            $item = [];
            $item['memo'] = $this->getRechargeMemo();
            $item['popmsg'] = '';
            if(intval($poptype) < 1) {
                $item['popmsg'] = $this->getPopMsg(1);
            }
            $item['customer'] = $this->getEmail(2);
            array_merge($data, $item);
            $redis->setex($key, 3600 ,  json_encode($item) );
        }
        return $this->backSucc($data);
    }

    //用户的银行卡列表信息
    public function banks() {
        $data = [];
        $split = $this->request->get('split', 0); //是否分开 1:管理支付方式页面 0:提现页面
        $banklist = [];
        $upis = [
            1 => [
                'id' => 1,
                'name' => 'Paytm',
                'checked' => true,
                'card' => 'Link',
                'icon' =>'/static/img/paytm.png',
                'cat' => 1,
            ],
            2 => [
                'id' => 1,
                'name' => 'Phonepe',
                'checked' => true,
                'card' => 'Link',
                'icon' =>'/static/img/phonepe.png',
                'cat' => 2,
            ],
            3 => [
                'id' => 1,
                'name' => 'Gpay',
                'checked' => true,
                'card' => 'Link',
                'icon' =>'/static/img/gpay.png',
                'cat' => 3,
            ],
            4 => [
                'id' => 1,
                'name' => 'Other UPI ID',
                'checked' => true,
                'card' => 'Link',
                'icon' =>'/static/img/upi.png',
                'cat' => 4,
            ],
        ];
        $res = DUserBank::where('uid', $this->uid)->where('status',2)->order('id','asc')->select()->toArray();

        foreach($res as $row) {
//            if($row['cat'] == 1) {
//                $item = [
//                    'id' => $row['id'],
//                    'name' => $row['bankname'],
//                    'checked' => true,
//                    'status' => $row['status'],
//                    'card' => $row['account'],
//                    "value" => "{$row['id']}",
//                ];
//                if($item['status'] <= 1) {
//                    $item['name'] = 'IN REVIEW';
//                } elseif($item['status'] == 2) {
//                    $item['name'] = 'VERIFIED';
//                } elseif($item['status'] == 3) {
//                    $item['name'] = 'Reject';
//                }
//                $len = strlen($row['account']);
//                if($len > 4) {
//                    $len = $len - 4;
//                    $replacestr = str_repeat('*', $len);
//                    $item['card'] = substr_replace($row['account'], $replacestr, 2, $len);
//                }
//                $banklist[] = $item;
//            }else
            if($row['cat'] == 3) {
                $item = [
                    'id' => $row['id'],
                    'name' => 'ETH(ERC20)',
                    'checked' => false,
                    'card' => $row['account'],
                    "value" => "{$row['id']}",
                ];
                if($row['usdttype'] == 2) {
                    $item['name'] = 'BTC(Coin)';
                }
                $len = strlen($row['account']);
                if($len > 8) {
                    $len = $len - 8;
                    $replacestr = str_repeat('*', $len);
                    $item['card'] = substr_replace($row['account'], $replacestr, 4, $len);
                }
                $usdts[] = $item;
            } elseif($row['cat'] == 2) {
                $upitype = intval($row['upitype']);
                $item = $upis[$upitype];
                $item['id'] = $row['id'];
                $item['checked'] = false;
                $item['card'] = $row['account'];
                $len = strlen($row['account']);
                if($len > 8) {
                    $len = $len - 8;
                    $replacestr = str_repeat('*', $len);
                    $item['card'] = substr_replace($row['account'], $replacestr, 4, $len);
                }
                $upis[$row['upitype']] = $item;
            }
        }
        //kyc中的银行卡
        $res = DKyc::where("uid={$this->uid} and status<=2 and category=3")->select()->toArray();
        if(!empty($res)) {
            $banklist = [];
            foreach($res as $row) {
                if(strpos($row['cardnum'], '已废弃') !== false) {
                    continue;
                }
                $item = [
                    'id'      => $row['id'],
                    'name'    => $row['bankname'],
                    'checked' => true,
                    'status'  => $row['status'],
                    'card'    => $row['cardnum'],
                    "value"   => "{$row['id']}",
                ];
                if($item['status'] <= 1) {
                    $item['name'] = 'IN REVIEW';
                } elseif($item['status'] == 2) {
                    $item['name'] = 'VERIFIED';
//                } elseif($item['status'] == 3) {
//                    $item['name'] = 'Reject';
                }
                $len = strlen($row['cardnum']);
                if($len > 4) {
                    $len = $len - 4;
                    $replacestr = str_repeat('*', $len);
                    $item['card'] = substr_replace($row['cardnum'], $replacestr, 2, $len);
                }
                $banklist[] = $item;
            }
        }


        if($split) {
            $data['banks'] = $banklist;
//            $data['usdts'] = $usdts;
            $data['upis']  = $upis;
        } else {
            $data['banks'] = $banklist;
        }
        $user = DUser::where('uid', $this->uid)->field('uid,kyc')->find();
        $data['kyc'] = isset($user['kyc']) ? intval($user['kyc']) : 0; //是否通过kyc验证
        return $this->backSucc($data);
    }

    //添加银行卡或usdt账户 作为提现用
    public function addcard() {
        $cat = $this->request->post('cat', '');
        $types = ['usdt','bank'];
        if(!in_array($cat, $types)) {
            return $this->backErr('Wrong action type', 501);
        }

        $save = 0;
        $nowtime = time();
        $model = new DUserBank();
        if($cat == 'usdt') {
            $address = $this->request->post('addr');
            $usdttype = $this->request->post('type', 1);
            if(empty($address)) {
                return $this->backErr('Please fill in the wallet address', 503);
            }
            $data = [
                'account' => $address,
                'usdttype' => $usdttype,
                'status' => 1,
                'cat' => 3,
                'create_time' => $nowtime,
                'uid' => $this->uid,
            ];
            $save = $model->save($data);
        } elseif ($cat == 'bank') {
            $account = $this->request->post('account');
            $username = $this->request->post('username');
            $bankname = $this->request->post('bankname');
            $ifsc = $this->request->post('ifsc');
            $pic = $this->request->post('pic');
            $state = $this->request->post('state'); //州
            $email = $this->request->post('email');
            if(empty($account)) {
                return $this->backErr('Please fill in the bank account information', 503);
            }
            //kyc 审核中的卡也要判断
            $count = DKyc::where('cardnum', "{$account}")->count();
            if($count >0 ) {
                return $this->backErr('This bank account nunber has been bound to other player.', 503);
            }
            //已经过审kyc的 和  后续添加的卡
            $count = DUserBank::where('account', "{$account}")->count();
            if($count >0 ) {
                return $this->backErr('This bank account nunber has been bound to other player.', 503);
            }
            $data = [
                'uid' => $this->uid,
                'username' => $username,
                'account' => $account,
                'bankname' => $bankname,
                'ifsc' => $ifsc,
                'pic' => $pic,
                'email' => $email,
                'status' => 0,
                'cat' => 1,
                'memo' => $state,
                'create_time' => $nowtime,
            ];
            $save = $model->save($data);
        }
        if($save) {
            return $this->backSucc();
        }
        return $this->backErr('Failed to add account!', 502);
    }

    //添加UPI
    public function addupi() {
        $cat = $this->request->post('cat', '');
        $types = [1,2,3,4];
        if(!in_array($cat, $types)) {
            return $this->backErr('Wrong action type', 501);
        }
        $address = $this->request->post('addr');
        if(empty($address)) {
            return $this->backErr('Please fill in the UPI id', 503);
        }
        $test = false;
        if($test) {
            $validate = true;
            $username = random_int(10000, 99999);
        } else {
            $api = 'https://paydigi.airtel.in/web/pg-service/v1/validate/vpa/'.$address;
            $upiDataStr = curlGet($api);
            if(!$upiDataStr) {
                return $this->backErr('Invalid VPA. Please enter a valid Virtual Payment Address', 504);
            }
            $upiData = json_decode($upiDataStr, true);
            $validate = false;
            $username = '';
            if($upiData && isset($upiData['data'])) {
                if(isset($upiData['data']['vpaValid'])) {
                    $validate = $upiData['data']['vpaValid'];
                }
                $username = isset($upiData['data']['payeeAccountName']) ? trim($upiData['data']['payeeAccountName']) : '';
            }
            if(!$validate) {
                return $this->backErr('Invalid VPA. Please enter a valid Virtual Payment Address', 505);
            }
        }

        $item = DUserBank::where('account', "{$address}")->find();
        if(!empty($item)) {
            if($item['uid'] == $this->uid){
                return $this->backErr('You have already bound the UPI ID.', 503);
            } else {
                return $this->backErr('This UPI number has been bound to other player.', 503);
            }
        }

        $nowtime = time();
        $model = new DUserBank();
        $data = [
            'username'=> $username,
            'account' => $address,
            'upitype' => $cat,
            'status' => 2,
            'cat' => 2,
            'create_time' => $nowtime,
            'uid' => $this->uid,
        ];
        $save = $model->save($data);

        if($save) {
            return $this->backSucc();
        }
        return $this->backErr('Failed to add UPI Account', 502);
    }

    //获取充值记录
    private function getRechargeList($startpage, $pagesize) {
        $start = ($startpage - 1 ) * $pagesize;
        if($start < 0) {
            $start = 0;
        }
        $startime = $this->getWeekDayStartTime();
        $order_status = [
            0 => 'In-Process',
            1 => 'In-Process',
            2 => 'Success',
            3 => 'Failed',
            4 => 'Refuse'
        ];
        $sql ="select a.id,a.orderid,a.uid,a.count,a.status,a.create_time,b.title,a.memo from d_user_recharge a left join s_pay_group b on a.groupid=b.id where a.create_time>={$startime} and a.uid={$this->uid} order by a.create_time desc limit {$start}, {$pagesize}";
        $chargeList = Db::query($sql);
        $datalist = [];
        $nowtime = time();
        foreach($chargeList as $row) {
            $cdate = date('m/d/Y', $row['create_time']);
            if(!isset($datalist[$cdate])) {
                $datalist[$cdate] = [];
            }
            $item = [
                'id' => $row['id'],
                'orderid' => $row['orderid'],
                'coin' => floatval($row['count']),
                'status_str' => isset($order_status[$row['status']]) ? $order_status[$row['status']] : 'In-Process',
                'status' => $row['status'],
                'time' => date('h:i a', $row['create_time']),
                'title' => $row['title'],
                'memo' => '',
            ];
            if($item['status'] == 4) { //失败
                $item['memo'] = mb_substr($row['memo'], 0, 128);
            }
            if($item['coin'] > 0) {
                $item['coin'] = number_format($item['coin'], 2,'.','');
            }
            if(($nowtime - $row['create_time']) <= 300 && $row['status'] == 0) { //5分钟内，未支付成功的订单先不显示need help
                $item['status'] = 1;
            }
            $datalist[$cdate][] = $item;
        }
        return $datalist;
    }

    //获取提现记录
    private function getDrawList($startpage, $pagesize) {
        $start = ($startpage - 1 ) * $pagesize;
        if($start < 0) {
            $start = 0;
        }
        $starttime = $this->getWeekDayStartTime();
        $order_status = [
            0 => 'In-Process',
            1 => 'In-Process',
            2 => 'Success',
            3 => 'Refund',
        ];
        $sql = "select id,orderid,cat,bankid,userbankid,coin,status,create_time,chanstate,memo from d_user_draw where uid={$this->uid} and create_time>={$starttime} order by create_time desc limit {$start},{$pagesize}";
        $chargeList = Db::query($sql);
        $datalist = [];
        $nowtime = time();
        foreach($chargeList as $row) {
            $cdate = date('m/d/Y', $row['create_time']);
            if(!isset($datalist[$cdate])) {
                $datalist[$cdate] = [];
            }
            $item = [
                'id' => $row['id'],
                'orderid' => $row['orderid'],
                'coin' => floatval($row['coin']),
                'status_str' => isset($order_status[$row['status']]) ? $order_status[$row['status']] : '',
                'status' => $row['status'],
                'time' => date('h:i a', $row['create_time']),
                'memo' => '',
            ];
            if($row['status'] == 2) {
               if($row['chanstate'] == 1) {
                   $item['status'] = 1;
                   $item['status_str'] = 'In-Payment';
               } elseif($row['chanstate'] == 3) {
                   $item['status'] = 4;
                   $item['status_str'] = 'Fail';
               }
            }
            if($item['status']==3 or $item['status']==3) { //已拒绝
                $item['memo'] = mb_substr($row['memo'], 0, 128);
            }
            if($item['coin'] > 0) {
                $item['coin'] = number_format($item['coin'], 2,'.','');
            }
            if(($nowtime - $row['create_time']) <= 300 && $row['status'] == 0) { //5分钟内，未支付成功的订单先不显示need help
                $item['status'] = 1;
            }
            $datalist[$cdate][] = $item;
        }
        return $datalist;
    }

    //下注记录
    private function getBetList($startpage, $pagesize) {
        $start = ($startpage - 1 ) * $pagesize;
        if($start < 0) {
            $start = 0;
        }
        $starttime = $this->getWeekDayStartTime();
        $sql = "select id,gameid,bet,wincoin,create_time,issue from d_desk_user where uid={$this->uid} and create_time>={$starttime} order by create_time desc limit {$start}, {$pagesize}";
        $chargeList = Db::query($sql);
        $datalist = [];
        $gModel = new SGame();
        $gameDict = $gModel->getTitleDict();
        foreach($chargeList as $row) {
            $cdate = date('m/d/Y', $row['create_time']);
            if(!isset($datalist[$cdate])) {
                $datalist[$cdate] = [];
            }
            $item = [
                'id' => $row['id'],
                'orderid' => $row['issue'],
                'bet' => floatval($row['bet']),
                'wincoin' => floatval($row['wincoin']-$row['bet']),
                'status' => ($row['wincoin'] > 0) ? 2: 3, //2 win 3:lose
                'gameid' => $row['gameid'],
                'title' => isset($gameDict[$row['gameid']]) ? $gameDict[$row['gameid']] : '',
                'time' => date('h:i a', $row['create_time']),
            ];
            if(($item['wincoin']) > 0) {
                $item['wincoin'] = number_format($item['wincoin'], 2,'.','');
            } else {
                $item['wincoin'] = 0;
            }
            $datalist[$cdate][] = $item;
        }
        return $datalist;
    }

    //bonus
    private function getBonusList($startpage, $pagesize) {
        $start = ($startpage - 1 ) * $pagesize;
        if($start < 0) {
            $start = 0;
        }
        $starttime = $this->getWeekDayStartTime();
        $sql = "select id,title,coin,category,create_time,orderid,useruid from d_log_cashbonus where uid={$this->uid} and create_time>={$starttime} order by create_time desc limit {$start},{$pagesize}";
        $chargeList = Db::query($sql);
        $datalist = [];
        foreach($chargeList as $row) {
            $cdate = date('m/d/Y', $row['create_time']);
            if(!isset($datalist[$cdate])) {
                $datalist[$cdate] = [];
            }
            $item = [
                'id' => $row['id'],
                'orderid' => $row['orderid'],
                'coin' => $row['coin'],
                'title' => $row['title'],
                'status' => $row['category'],
                'time' => date('h:i a', $row['create_time']),
            ];
            if($item['coin'] > 0) {
                $item['coin'] = number_format($item['coin'], 2,'.','');
            }
            switch ($row['category']) {
                case 1:
                    $item['status_str'] = 'Register bonus';
                    if($row['useruid']) {
                        $item['status_str'] = 'Refer & earn bonus referral register';
                    }
                    break;
                case 2:
                    $item['status_str'] = 'Deposit bonus';
                    if($row['useruid']) {
                        $item['status_str'] = 'Refer & earn bonus referral deposit';
                    }
                    break;
                case 3:
                    $item['status_str'] = 'User Bet';
                    if($row['useruid']) {
                        $item['status_str'] = 'Refer & earn bonus referral bet';
                    }
                    break;
                case 4:
                    $item['status_str'] = 'Quest';
                    break;
                case 5: //vip升级
                    $item['status_str'] = 'VIP level up bonus';
                    break;
                case 6://转出
                    $item['status_str'] = 'Transfer out';
                    break;
                case 9: //vip周奖励
                    $item['status_str'] = 'VIP weekly bonus';
                    break;
                case 10: //vip月奖励
                    $item['status_str'] = 'VIP monthly bonus';
                    break;
                case 11: //充值任务
                    $item['status_str'] = 'Task bonus deposit task';
                    break;
                case 22: //游戏任务
                    $item['status_str'] = 'Task bonus game task';
                    break;
                case 23: //盈利任务
                    $item['status_str'] = 'Task bonus profit task';
                    break;
                case 33: //下注任务
                    $item['status_str'] = 'Task bonus betting task';
                    break;
                case 34: //转出到现金钱包
                    $item['status_str'] = 'Transfer to Cash Balance';
                    break;
                case 35: //签到
                    $item['status_str'] = 'Login bonus';
                    break;
                case 36: //转盘分享
                    $item['status_str'] = 'Share & spin bonus';
                    break;
                case 37: //注册返水
                    $item['status_str'] = 'Rebate bonus';
                    break;
                case 38: //沙龙收益
                    $item['status_str'] = 'Salon room tax bonus';
                    break;
                case 39: //cash balance转入
                    $item['status_str'] = 'Free Winnings transfer in';
                    break;
                default:
                    $item['status_str'] = 'Lucky bonus';
            }
            $datalist[$cdate][] = $item;
        }
        return $datalist;
    }

    //bank list
    private function getBankList() {
        return [];
        $order_status = [
            1 => 'Transfer In',
            2 => 'Transfer Out',
        ];
        $sql = "select id, orderid, coin, type, create_time from d_bank_record where uid={$this->uid} order by create_time desc limit 20";
        $chargeList = Db::query($sql);
        $datalist = [];
        foreach($chargeList as $row) {
            $cdate = date('m/d/Y', $row['create_time']);
            if(!isset($datalist[$cdate])) {
                $datalist[$cdate] = [];
            }
            $item = [
                'id' => $row['id'],
                'orderid' => $row['orderid'],
                'coin' => $row['coin'],
                'status' => ($row['type']==1) ? 3 : $row['type'],
                'status_str' => $order_status[$row['type']],
                'time' => date('h:i a', $row['create_time'])
            ];
            if($item['coin'] > 0) {
                $item['coin'] = number_format($item['coin'], 2, '.','');
            }
            $datalist[$cdate][] = $item;
        }
        return $datalist;
    }

    //用户的记录记录
    public function history() {
        $num = $this->request->get('num', 0); //当前页
        $pagesize = $this->request->get('size', 20); //每页显示条数
        if($num < 0) {
            $num = 0;
        }
        if($pagesize > 20 || $pagesize < 0) {
            $pagesize = 20;
        }
        $data = [
            'deplist'   => $this->getRechargeList($num, $pagesize),
            'drawlist'  => $this->getDrawList($num, $pagesize),
            'betlist'   => $this->getBetList($num, $pagesize),
            'bonuslist' => $this->getBonusList($num, $pagesize),
            'banklist'  => [],
//            'banklist'  => $this->getBankList(),
            'show' => 1,
        ];
        $user = DUser::where('uid', '=',$this->uid)->field('uid,nevershowtips')->find();
        if(intval($user['nevershowtips']) > 0) {
            $data['show'] = 0;
        }
        return $this->backSucc($data);
    }

}
