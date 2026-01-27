<?php
declare (strict_types = 1);

namespace app;

use app\model\DBank;
use app\model\DDiscountLabel;
use app\model\DLogOrder;
use app\model\DUser;
use app\model\DUserRecharge;
use app\model\SConfig;
use app\model\SConfigAmount;
use app\model\SPayBank;
use app\model\SPayCfgChannel;
use app\model\SPayGroup;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Env;
use think\facade\Request;
use think\Validate;
use think\facade\Config;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    protected $notCheckControllers = ['sms','img', 'game', 'doc'];
    protected $notCheckActions = ['notify','dosend','notifyforonlinepay','notifyforonlinedraw'];

    private $redis_instance ;

    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    static $EMPTY_STR = 'empty';

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    //当前请求的uid
    protected $uid = 0;
    protected $token = ''; //当前请求带的token

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    //获取当前url
    protected function getCurUrl()
    {
        $host = Request::host();
        $scheme = Request::scheme();
        return $scheme.'://'.$host;
    }

    protected function getKycBankPicFlag() {
        $picopen = true;
        $cfg = SConfig::where('k','kycverify')->find();
        if($cfg && !empty($cfg['v'])) { //默认开启图片上传
            $config = json_decode($cfg['v'], true);
            if(isset($config['bank']) && isset($config['bank']['pic'])) {
                if(!intval($config['bank']['pic'])) {
                    $picopen = false;
                }
            }
        }
        return $picopen;
    }

    protected function backjson($data=[], $msg='succ', $err=0) {
        $result = [
            'code' => $err,
            'msg' => $msg,
            'payload' => $data
        ];
        header('Content-type:application/json');
        echo json_encode($result);
        exit;
    }
    //返回json
    protected function backSucc($data=[], $msg='succ', $err=0, $no_url = false) {
        if(!$no_url && !isset($data['url'])) {
            $data['url'] = Env::get('contact_us');
        }
        $result = [
            'code' => $err,
            'msg' => $msg,
            'data' => $data
        ];
        header('Content-type:application/json');
        echo json_encode($result);
        exit;
    }

    //错误返回
    protected function backErr($msg='', $err=0, $no_url = false) {
        $data = [];
        if(!$no_url)
            $data['url'] = Config::get('app.contact_us');
        return $this->backSucc($data, $msg, $err, $no_url);
    }

    protected function checkUser() {
        $uid = $this->request->param('uid',0);
        $token = $this->request->param('token', '');
        if($uid == 0 or empty($token)) {
            return $this->backErr('config err!', 500);
        }
        $redis = $this->getRedisObj();
        $cacheKey = 't_'.trim($token);
        $cacheuid = $redis->get($cacheKey);
        if(intval($cacheuid) != intval($uid)) {
            return $this->backErr('config err1!'.$cacheKey."-".$cacheuid, 500);
        }
        $this->uid = $uid;
        $this->token = $token;
    }

    // 初始化
    protected function initialize()
    {
        $action = Request::action(true);
        $controller = Request::controller(true);
        // file_put_contents('/tmp/test.log', $this->notCheckActions, FILE_APPEND);
        // file_put_contents('/tmp/test.log', $action . PHP_EOL , FILE_APPEND);
        if(!in_array($action, $this->notCheckActions) && !in_array($controller, $this->notCheckControllers)) 
        {
            return $this->checkUser();
        } else {
            $uid = $this->request->param('uid',0);
            if($uid) 
            {
                $this->uid = intval($uid);
            }
        }
    }

    protected function getRedisObj()
    {
        if(!$this->redis_instance) {
            // file_put_contents('/tmp/test.log', 'aaaa'. PHP_EOL, FILE_APPEND);
            $this->redis_instance = new \Redis();
            $this->redis_instance->connect(Env::get('redis.hostname'), intval(Env::get('redis.port', 6379)));
            $this->redis_instance->auth(Env::get('redis.password'));
            $this->redis_instance->select(intval(Env::get('redis.select', 0)));
        }
        return $this->redis_instance;
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    //获取快捷金额列表 2:充值 3:提现
    protected function getBetCoinList($cat) {
        $key = "betcoinlist:{$cat}";
        $redis = $this->getRedisObj();
        $cache_str = $redis->get($key);
        if(!empty($cache_str)) {
            return json_decode($cache_str, true);
        }

        $amountModel = new SConfigAmount();
        $datalist = $amountModel->getDataList($cat);
        $redis->setex($key, 86400, json_encode($datalist));
        return $datalist;
    }

    protected function getKYCInfo($user_kycfield) {
        $configModel = new SConfig();
        $key = 'kycverify';
        $item = $configModel->getItem($key);
        $ret = [
            'open' => 0, //默认关闭
            'data' => []
        ];
        if($item['v'] && !empty($item['v'])) {
            $kycfield = $user_kycfield;
            $userkyc = [
                'mobile' => 0,
                'pan' => 0,
                'bank' => 0,
            ];
            if($kycfield%10 == 1) {
                $userkyc['mobile'] = 1;
            }
            if(floor($kycfield/100) == 1) {
                $userkyc['bank'] = 1;
            }
            $fields = str_split(strval($kycfield));
            if((count($fields) ==2 && $fields[0]==1) || (count($fields)==3 && $fields[1]==1)) {
                $userkyc['pan'] = 1;
            }

            $arr = json_decode($item['v'], true);
            foreach ($arr as $k=>$v) {
                if(intval($v['open']) == 0 && $userkyc[$k]==0) {
                    unset($arr[$k]);
                } else {
                    if($v['open'] || $userkyc[$k]==1) {
                        $ret['open'] = 1; //kyc是打开的
                    }
                }
            }
            $ret['data'] = $arr;
        }
        return $ret;
    }

    //获取所有屏蔽了充值的无优惠id列表
    protected function getDiscountLabelIDs() {
        $key = 'pay_discount_labels';
        $redis = $this->getRedisObj();
        $cache = $redis->get($key);
        if(!$cache) {
            $where = "status = 1 and FIND_IN_SET(1, discount)"; //屏蔽掉了充值优惠的标签id
            $res = DDiscountLabel::where($where)->field('id,title')->select()->toArray();
            $data = array_column($res, 'id');
            $redis->setex($key, 300, json_encode($data));
        } else {
            $data = json_decode($cache, true);
        }
        return $data;
    }

    //获取用户余额相关
    protected function getUserCoin($uid) {
        $fields = 'uid,gamedraw,coin,kyc,cashbonus,svip,ispayer,kycfield, isbindphone, nodislabelid';
        $userinfo = DUser::where('uid', $this->uid)->field($fields)->find();

        //$totalwin = floatval($userinfo['totalwin']); //总赢
        //$totalbet = floatval($userinfo['totalbet']); //总下注
        //$totaldraw = floatval($userinfo['totaldraw']); //总提现
        //$candraw = floatval($userinfo['candraw']); //剩余可提现
        //$dcoin = $totalwin - $totalbet - $totaldraw + $candraw; //--可提现余额



        $dcoin = floatval($userinfo['gamedraw']);;
        if($dcoin < 0) {
            $dcoin = 0;
        }
        if($dcoin > $userinfo['coin']) {
            $dcoin = $userinfo['coin'];
        }
        //if($userinfo['maxdraw'] > 0 and $userinfo['maxdraw'] < $dcoin) {
        //    $dcoin = $userinfo['maxdraw']; //限制条件下的可提金额
        //}
//        $dbModel = new DBank();
//        $bankcoin = $dbModel->getBankCoin($this->uid);
        $bankcoin = 0;
        $totalcoin = $userinfo['coin'] + $bankcoin; //账户总额

        $kyc = intval($userinfo['kyc']);
        if($kyc == 0) { //未完成kyc的情况下
            $kycinfo = $this->getKYCInfo($userinfo['kycfield']);
            if($kycinfo['open'] != 1) {
                $kyc = 1; //服务器关闭了kyc验证，所以用户显示结果直接显示已完成kyc
            }
        }
        //$dcoin = number_format($dcoin, 2);
        $labels = $this->getDiscountLabelIDs();
        $labelid = in_array($userinfo['nodislabelid'], $labels) ? $userinfo['nodislabelid'] : 0;
        return [
            'uid' => $userinfo['uid'],
            'coin'=> floatval($userinfo['coin']), //现金余额
            'dcoin'=> $dcoin, //可提金额
            'ecoin' => $userinfo['coin'] - $dcoin, //cash balance
            'bonus' => floatval($userinfo['cashbonus']), //优惠总余额
            'totalcoin' => $totalcoin,
            'kyc' => $kyc,
            'svip' => intval($userinfo['svip']),
            'ispayer' => intval($userinfo['ispayer']),
            'nodislabelid' => $labelid, //无优惠标签
        ];
    }

    protected function getOrdersByGroup($groups=[]) {
        if(empty($groups)) {
            $groups = SPayGroup::select()->toArray();
        }
        $orders = []; //总订单数
        $orders_today = []; //今日订单数
        $group_ids = [];
        foreach($groups as $k=>$v) {
            $group_ids[] = $v['id'];
        }
        $todaytime = strtotime(date('Y-m-d'));
        $status_pay = [2,3]; //支付成功的状态

        $redis = $this->getRedisObj();
        $key1 = 'pay_orders:'.$this->uid;
        $orders = $redis->hGetAll($key1);
        if(!$orders || empty($orders)) {
            $alllists = Db::table('d_user_recharge')->field('count(id) as t, groupid,channelid,category')->where('uid','=', $this->uid)->where('status','in', $status_pay)->where('groupid','in', $group_ids)->group('groupid,channelid,category')->select()->toArray();
            foreach($alllists as $row) {
                $orders[$row['groupid'].'_'.$row['channelid'].'_'.$row['category']] = $row['t']; //此分组的订单数 首充
                $redis->hSet($key1, $row['groupid'].'_'.$row['channelid'].'_'.$row['category'], $row['t']);
            }
        }

        $key2 = 'pay_orders_today:' . $this->uid;
        $orders_today = $redis->hGetAll($key2);
        if(!$orders_today || empty($orders_today)) {
            $toadylists = Db::table('d_user_recharge')->field('count(id) as t, groupid, channelid,category')->where('create_time','>', $todaytime)->where('uid','=', $this->uid)->where('status','in', $status_pay)->where('groupid','in', $group_ids)->group('groupid,channelid,category')->select()->toArray();
            foreach($toadylists as $row) {
                $orders_today[$row['groupid'].'_'.$row['channelid'].'_'.$row['category']] = $row['t']; //此分组的当天订单数 当日首充
                $redis->hSet($key2, $row['groupid'].'_'.$row['channelid'].'_'.$row['category'], $row['t']);
            }
        }
        return [$orders, $orders_today];
    }

    protected function getChannelsByLabel($rechargeCoin, $svip=0, $groupid=0, $coin=0, $labelid=0) {
        $data = [];
        if($svip <= 0) {
            $svip = 0;
        }

        $groups = [];
        $key = "s_pay_groups_label:{$groupid}:{$labelid}";
        $redis = $this->getRedisObj();
        $cache_groups = $redis->get($key);
        if($cache_groups== self::$EMPTY_STR) {
            return $groups;
        }
        if(empty($cache_groups)) {
            $groupModel = new SPayGroup();
            $groups = $groupModel->getDataBySvip($svip, $groupid);
            if(empty($groups)) {
                $redis->setex($key, 7200, self::$EMPTY_STR);
                return $groups;
            } else {
                $redis->setex($key, 7200, json_encode($groups));
            }
        } else {
            $groups = json_decode($cache_groups, true);
        }
        $dicts = [
            1 => 'paytm',
            2 => 'googlepay',
            3 => 'paytm',
            4 => 'phonePe',
            5 => 'upi',
            6 => 'usdt',
            7 => 'bank',
            8 => 'onlinepay',
            9 => 'addcard',
            10 => 'wallets',
            11 => 'netbank',
        ];
        $chanModel = new SPayCfgChannel();
        $bankModel = new SPayBank();
        $datalist = [];

        $tmp = $this->getOrdersByGroup($groups);
        $orders = $tmp[0]; //总订单数
        $orders_today = $tmp[1]; //今日订单数
        foreach ($groups as $k=>$row) {
            $groupItem = [
                'id' => $row['id'],
                'title'   => $row['title'],
                'icon'    => Config::get('pay.img_url') . $row['icon'],
                'subtype' => isset($dicts[$row['subject']]) ? $dicts[$row['subject']] : '',
                'mincoin' => 0, //最小
                'maxcoin' => 0, //最大
                'disrate' => 0, //优惠比例
                'discoin' => 0,
                'type' => 0,
                'pages' => [],
            ];

            $res = '';
            $key_chan = "s_pay_cfg_channel_label:{$row['id']}:{$labelid}";
            $cache_chan = $redis->get($key_chan);
            if(!empty($cache_chan)) {
                $res = json_decode($cache_chan, true);
            } else {
                $res = $chanModel->getDataListByGroup($row['id'], $svip, $labelid);
                $redis->setex($key_chan, 7200, json_encode($res));
            }
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    if($rechargeCoin > 0 && ($rechargeCoin < $val['mincoin'] || $rechargeCoin > $val['maxcoin'])) {
                        continue;
                    }
                    $item = [
                        'id'    => $val['id'],
                        'title' => $val['title'],
                        'type'  => $row['category'],
                        'banktype' => 0,
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => 0,
                        'discoin' => 0,
                        'rate' => $val['disrate'],
                    ];
                    if($groupItem['mincoin'] == 0) {
                        if(isset($item['mincoin']) && $item['mincoin']) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    } else {
                        if(isset($item['mincoin']) && ($item['mincoin'] < $groupItem['mincoin'])) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    }

                    if($groupItem['maxcoin'] == 0) {
                        if(isset($item['maxcoin']) && $item['maxcoin']) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    } else {
                        if(isset($item['maxcoin']) && ($item['maxcoin'] < $groupItem['maxcoin'])) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    }
                    $groupItem['pages'][] = $item;
                }
            }

            //线下付款方式
            $key_bank = "s_pay_bank_label:{$row['id']}:{$labelid}";
            $cache_bank = $redis->get($key_bank);
            if(!empty($cache_bank)) {
                $res = json_decode($cache_bank, true);
            } else {
                $res = $bankModel->getDataListByGroup($row['id'], $svip, $labelid);
                $redis->setex($key_bank, 7200, json_encode($res));
            }
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    if($rechargeCoin > 0 && ($rechargeCoin < $val['mincoin'] || $rechargeCoin > $val['maxcoin'])) {
                        continue;
                    }
                    $item = [
                        'id' => $val['id'],
                        'title' => $val['title'],
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => 0,
                        'discoin' => 0,
                        'rate' => '',
                        'banktype' => $val['banktype'],
                        'type' => $row['category'],
                    ];

                    if($groupItem['mincoin'] == 0) {
                        if(isset($item['mincoin']) && $item['mincoin']>0) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    } else {
                        if(isset($item['mincoin']) && ($item['mincoin'] < $groupItem['mincoin'])) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    }
                    if($groupItem['maxcoin'] == 0) {
                        if(isset($item['maxcoin']) && $item['maxcoin']>0) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    } else {
                        if(isset($item['maxcoin']) && ($item['maxcoin'] > $groupItem['maxcoin'])) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    }

                    if($groupItem['discoin'] == 0) {
                        if(isset($item['discoin']) && $item['discoin'] > 0) {
                            $groupItem['discoin'] = $item['discoin'];
                        }
                    } else {
                        if(isset($item['discoin']) && ($item['discoin'] > $groupItem['discoin'])) {
                            $groupItem['discoin'] = $item['discoin'];
                        }
                    }

                    $groupItem['pages'][] = $item;
                }
            }

            $groupItem['mincoin'] = intval($groupItem['mincoin']);
            $groupItem['maxcoin'] = intval($groupItem['maxcoin']);
            if(count($groupItem['pages']) > 0) {
                $datalist[$row['id']] = $groupItem;
            }
        }
        foreach($datalist as $k=>$v) {
            if(count($v['pages']) >= 1) {
                $data[] = $v;
            }
        }
        return $data;
    }

    //根据用户充值金额和VIP等级，获取可用的支付通道
    protected function getChannels($rechargeCoin, $svip=0, $groupid=0, $coin=0) {
        $data = [];
        if($svip <= 0) {
            $svip = 0;
        }

        $groups = [];
        $key = "s_pay_groups:{$groupid}:{$svip}";
        $redis = $this->getRedisObj();
        $cache_groups = $redis->get($key);
        if($cache_groups== self::$EMPTY_STR) {
            return $groups;
        }
        if(empty($cache_groups)) {
            $groupModel = new SPayGroup();
            $groups = $groupModel->getDataBySvip($svip, $groupid);
            if(empty($groups)) {
                $redis->setex($key, 7200, self::$EMPTY_STR);
                return $groups;
            } else {
                $redis->setex($key, 7200, json_encode($groups));
            }
        } else {
            $groups = json_decode($cache_groups, true);
        }
        $dicts = [
            1 => 'paytm',
            2 => 'googlepay',
            3 => 'paytm',
            4 => 'phonePe',
            5 => 'upi',
            6 => 'usdt',
            7 => 'bank',
            8 => 'onlinepay',
            9 => 'addcard',
            10 => 'wallets',
            11 => 'netbank',
        ];
        $chanModel = new SPayCfgChannel();
        $bankModel = new SPayBank();
        $datalist = [];

        $tmp = $this->getOrdersByGroup($groups);
        $orders = $tmp[0]; //总订单数
        $orders_today = $tmp[1]; //今日订单数
        foreach ($groups as $k=>$row) {
            $groupItem = [
                'id' => $row['id'],
                'title'   => $row['title'],
                'icon'    => Env::get('IMAGE_URL') . $row['icon'],
                'subtype' => isset($dicts[$row['subject']]) ? $dicts[$row['subject']] : '',
                'mincoin' => 0, //最小
                'maxcoin' => 0, //最大
                'disrate' => 0, //优惠比例
                'discoin' => 0,
                'type' => 0,
                'pages' => [],
            ];

            $res = '';
            $key_chan = "s_pay_cfg_channel:{$row['id']}:{$svip}";
            $cache_chan = $redis->get($key_chan);
            if(!empty($cache_chan)) {
                $res = json_decode($cache_chan, true);
            } else {
                $res = $chanModel->getDataListByGroup($row['id'], $svip);
                $redis->setex($key_chan, 7200, json_encode($res));
            }
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    if($rechargeCoin > 0 && ($rechargeCoin < $val['mincoin'] || $rechargeCoin > $val['maxcoin'])) {
                        continue;
                    }
                    $item = [
                        'id'    => $val['id'],
                        'title' => $val['title'],
                        'type'  => $row['category'],
                        'banktype' => 0,
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => number_format($val['disrate']*100, 2),
                        'discoin' => 0,
                        'rate' => $val['disrate'],
                    ];
                    if(isset($val['discoin']) && $val['discoin']>0) {
                        $item['discoin'] = $val['discoin'];
                        $item['disrate'] = 0;
                        if($coin > 0) {
                            $item['rate'] = number_format($item['discoin']/$coin, 2);
                        }
                    }

                    $order_key = $row['id'].'_'.$val['id'].'_1';
                    if(!isset($orders_today[$order_key])) { //当天首次
                        if($val['fd_dcoin'] > 0) {
                            $item['discoin'] = $val['fd_dcoin'];
                            $item['disrate'] = 0;
                            if($coin > 0) {
                                $item['rate'] = number_format($item['discoin']/$coin, 2);
                            }
                        }
                        if($val['fd_drate'] > 0) {
                            $item['disrate'] = number_format($val['fd_drate']*100, 2);
                            $item['rate'] = $val['fd_drate'];
                            $item['discoin'] = 0;
                        }
                    }

                    //首次
                    if(!isset($orders[$order_key])) {
                        if($val['f_dcoin'] > 0) {
                            $item['discoin'] = $val['f_dcoin'];
                            $item['disrate'] = 0;
                            if($coin > 0) {
                                $item['rate'] = number_format($item['discoin']/$coin, 2);
                            }
                        }
                        if($val['f_drate'] > 0) {
                            $item['disrate'] = number_format($val['f_drate']*100, 2);
                            $item['rate'] = $val['f_drate'];
                            $item['discoin'] = 0;
                        }
                    }

                    if($groupItem['mincoin'] == 0) {
                        if(isset($item['mincoin']) && $item['mincoin']) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    } else {
                        if(isset($item['mincoin']) && ($item['mincoin'] < $groupItem['mincoin'])) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    }

                    if($groupItem['maxcoin'] == 0) {
                        if(isset($item['maxcoin']) && $item['maxcoin']) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    } else {
                        if(isset($item['maxcoin']) && ($item['maxcoin'] < $groupItem['maxcoin'])) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    }
                    if($groupItem['disrate'] == 0) {
                        if(isset($item['disrate']) && $item['disrate'] > 0) {
                            $groupItem['disrate'] = $item['disrate'];
                        }
                    } else {
                        if(isset($item['disrate']) && ($item['disrate'] > $groupItem['disrate'])) {
                            $groupItem['disrate'] = $item['disrate'];
                        }
                    }

                    if($groupItem['discoin'] == 0) {
                        if(isset($item['discoin']) && $item['discoin'] > 0) {
                            $groupItem['discoin'] = $item['discoin'];
                        }
                    } else {
                        if(isset($item['discoin']) && ($item['discoin'] > $groupItem['discoin'])) {
                            $groupItem['discoin'] = $item['discoin'];
                        }
                    }
                    if($groupItem['discoin'] > 0) {
                        $groupItem['discoin'] = intval($groupItem['discoin']);
                        $groupItem['disrate'] = '+'.$groupItem['discoin'];
                    }

                    if($item['disrate'] > 0) {
                        $item['disrate'] = '+'.$item['disrate'] . '%';
                    }
                    if($item['discoin'] > 0) {
                        $item['discoin'] = intval($item['discoin']);
                        $item['disrate'] = '+'.$item['discoin'];
                    }
                    $groupItem['pages'][] = $item;
                }
            }

            //线下付款方式
            $res = '';
            $key_bank = "s_pay_bank:{$row['id']}:{$svip}";
            $cache_bank = $redis->get($key_bank);
            if(!empty($cache_bank)) {
                $res = json_decode($cache_bank, true);
            } else {
                $res = $bankModel->getDataListByGroup($row['id'], $svip);
                $redis->setex($key_bank, 7200, json_encode($res));
            }
//            $res = $bankModel->getDataListByGroup($row['id'], $svip);
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    if($rechargeCoin > 0 && ($rechargeCoin < $val['mincoin'] || $rechargeCoin > $val['maxcoin'])) {
                        continue;
                    }
                    $item = [
                        'id' => $val['id'],
                        'title' => $val['title'],
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => number_format($val['disrate']*100, 2),
                        'discoin' => 0,
                        'rate' => $val['disrate'],
                        'banktype' => $val['banktype'],
                        'type' => $row['category'],
                    ];

                    if(isset($val['discoin']) && $val['discoin'] > 0) {
                        $item['discoin'] = $val['discoin'];
                        $item['disrate'] = 0;
                        if($coin > 0) {
                            $item['rate'] = number_format($item['discoin']/$coin, 2);
                        }
                    }

                    $order_key = $row['id'].'_'.$val['id'].'_2';
                    $order_key2 = $row['id'].'_'.$val['id'].'_3';
                    if(!isset($orders_today[$order_key]) && !isset($orders_today[$order_key2])) { //当天首次
                        if($val['fd_dcoin'] > 0) {
                            $item['discoin'] = $val['fd_dcoin'];
                            $item['disrate'] = 0;
                            if($coin > 0) {
                                $item['rate'] = number_format($item['discoin']/$coin, 2);
                            }
                        }
                        if($val['fd_drate'] > 0) {
                            $item['disrate'] = number_format($val['fd_drate']*100, 2);
                            $item['rate'] = $val['fd_drate'];
                            $item['discoin'] = 0;
                        }
                    }

                    //首次
                    if(!isset($orders[$order_key]) && !isset($orders[$order_key2]) ) {
                        if($val['f_dcoin'] > 0) {
                            $item['discoin'] = $val['f_dcoin'];
                            $item['disrate'] = 0;
                            if($coin > 0) {
                                $item['rate'] = number_format($item['discoin']/$coin, 2);
                            }
                        }
                        if($val['f_drate'] > 0) {
                            $item['disrate'] = number_format($val['f_drate']*100, 2);
                            $item['rate'] = $val['f_drate'];
                            $item['discoin'] = 0;
                        }
                    }

                    if($groupItem['mincoin'] == 0) {
                        if(isset($item['mincoin']) && $item['mincoin']>0) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    } else {
                        if(isset($item['mincoin']) && ($item['mincoin'] < $groupItem['mincoin'])) {
                            $groupItem['mincoin'] = $item['mincoin'];
                        }
                    }
                    if($groupItem['maxcoin'] == 0) {
                        if(isset($item['maxcoin']) && $item['maxcoin']>0) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    } else {
                        if(isset($item['maxcoin']) && ($item['maxcoin'] > $groupItem['maxcoin'])) {
                            $groupItem['maxcoin'] = $item['maxcoin'];
                        }
                    }
                    if($groupItem['disrate'] == 0) {
                        if(isset($item['disrate']) && $item['disrate'] > 0) {
                            $groupItem['disrate'] = $item['disrate'];
                        }
                    } else {
                        if(isset($item['disrate']) && $item['disrate'] > $groupItem['disrate']) {
                            $groupItem['disrate'] = $item['disrate'];
                        }
                    }
                    if($groupItem['discoin'] == 0) {
                        if(isset($item['discoin']) && $item['discoin'] > 0) {
                            $groupItem['discoin'] = $item['discoin'];
                        }
                    } else {
                        if(isset($item['discoin']) && ($item['discoin'] > $groupItem['discoin'])) {
                            $groupItem['discoin'] = $item['discoin'];
                        }
                    }
                    if($groupItem['discoin'] > 0) {
                        $groupItem['discoin'] = intval($groupItem['discoin']);
                        $groupItem['disrate'] = '+'.$groupItem['discoin'];
                    }

                    if($item['disrate'] > 0) {
                        $item['disrate'] = '+'.$item['disrate'] . '%';
                    }
                    if($item['discoin'] > 0) {
                        $item['discoin'] = intval($item['discoin']);
                        $item['disrate'] = '+'.$item['discoin'];
                    }
                    $groupItem['pages'][] = $item;
                }
            }

            if($groupItem['discoin'] > 0 or $groupItem['disrate'] >0 ){
                $groupItem['disrate'] = '+'.$groupItem['disrate'] . '%';
                if($groupItem['discoin'] && $groupItem['disrate'] == 0) {
                    $groupItem['disrate'] = '+'.$groupItem['discoin'];
                }
            }
            $groupItem['mincoin'] = intval($groupItem['mincoin']);
            $groupItem['maxcoin'] = intval($groupItem['maxcoin']);
            if(count($groupItem['pages']) > 0) {
                $datalist[$row['id']] = $groupItem;
            }
        }
        foreach($datalist as $k=>$v) {
            if($v['discoin'] > 0 && empty($v['disrate'])) {
                $v['disrate'] = $v['discoin'];
                unset($v['discoin']);
            }
            if(count($v['pages']) >= 1) {
                $data[] = $v;
            }
        }
        return $data;
    }

    protected function addDrawOrPayLog($orderid, $now_time, $memo, $cat=2) {
        $log = [
            'orderid' => $orderid,
            'cat' => $cat,
            'create_time' => $now_time,
            'uid' => 0,
            'memo' => $memo
        ];
        $logModel = new DLogOrder();
        $logModel->save($log);
    }

    //获取客服email
    protected function getEmail($position=0) {
        if(!$position) {
            return [];
        }
        $user = DUser::where('uid', $this->uid)->find();
        $svip = intval($user['svip']);
        $key = 'email_' . $position;
        $redis = $this->getRedisObj();
        $cache = $redis->get($key);
        if($cache) {
            $res = json_decode($cache, true);
            return $res;
        } else {
            $sql = "select category,url,memo from s_config_customer where status=1 and FIND_IN_SET({$svip},svip) and showpage={$position} and category in (4,5) order by ord asc,id asc";
            $res = Db::query($sql);
            if($res) {
                $data = [];
                foreach($res as $k=>$row) {
                    $data[] =  [
                        'category' => $row['category'],
                        'email' => $row['url'],
                        'memo' => $row['memo']
                    ];
                }

                $redis->setex($key, 60, json_encode($data));
                return $data;
            }
        }

        return [];
    }
}
