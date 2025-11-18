<?php
namespace app\controller;

use app\BaseController;
use app\model\DBank;
use app\model\DUser;
use app\model\SConfigAmount;
use app\model\SConfigPics;
use app\model\SPayBank;
use app\model\SPayCfgChannel;
use app\model\SPayGroup;
use app\model\SPayDiscount;
use app\Request;
use think\Db;
use think\facade\Env;
use think\facade\Config;
use think\facade\Log;

class Index extends BaseController
{
    public function index() {
        echo 'hello';
    }

    private function getPicsAndAmount($type) {
        $picModel = new SConfigPics();
        $pics = $picModel->getDataList($type);

        $amModel = new SConfigAmount();
        $amounts = $amModel->getDataList($type);
        $amountArr = [];
        foreach($amounts as $row) {
            $amountArr[] = $row['amount'];
        }
        return [
            'pics' => $pics,
            'amounts' => $amountArr
        ];
    }

    //支付通道获取
    public function channel() {
        $retData = [
            'totalcoin' => 0
        ];

        $userinfo = DUser::where('uid', $this->uid)->field('uid,coin,cashbonus,totalbet,totalwin,totaldraw,maxdraw,candraw,svip')->find();
        $svip = ($userinfo && isset($userinfo['svip'])) ? intval($userinfo['svip']) : 1;
        if($svip <= 0) {
            $svip = 0;
        }

        $usercoins = $this->getUserCoin($this->uid);
        $retData['totalcoin'] = $usercoins['totalcoin'];
        $groupModel = new SPayGroup();
        $groups = $groupModel->getDataBySvip($svip);
        if(empty($groups)) {
            return $this->backSucc($retData, 'no data');
        }

//        $tmp = $this->getPicsAndAmount(2);
//        $retData = array_merge($retData, $tmp);

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
        foreach ($groups as $k=>$row) {
            $groupItem = [
                'id' => $row['id'],
                'title'   => $row['title'],
                'icon'    => Config::get('pay.img_url') . $row['icon'],
                'subject' => $row['subject'],
                'subtype' => isset($dicts[$row['subject']]) ? $dicts[$row['subject']] : '',
                'note' => '',
                'mincoin' => 0, //最小
                'maxcoin' => 0, //最大
                'disrate' => 0, //优惠比例
                'discoin' => 0, //优惠金额
                'type' => 0,
                'pages' => [],
            ];

            $res = $chanModel->getDataListByGroup($row['id'], $svip);
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    $item = [
                        'id'    => $val['id'],
                        'title' => $val['title'],
                        'type'  => 0,
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => number_format($val['disrate']*100, 2),
                        'discoin' => $val['discoin'],
                    ];
                    if($row['id'] == 6) {
                        $item['type'] = 1;
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
                    $groupItem['pages'][] = $item;
                }
            }

            //线下付款方式
            $res = $bankModel->getDataListByGroup($row['id'], $svip);
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    $item = [
                        'id' => $val['id'],
                        'title' => $val['title'],
                        'type' => $val['type'],
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => number_format($val['disrate']*100, 2),
                    ];
                    if($row['subject'] == 7) {
                        $item['type'] = 2;
                    }elseif($row['subject'] == 6) {
                        $item['type'] = 3;
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
                    $groupItem['pages'][] = $item;
                }
            }

            if($groupItem['discoin'] > 0 or $groupItem['disrate'] >0 ){
                $groupItem['note'] = 'Top up on the day ';
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
        $items = [];
        foreach($datalist as $k=>$v) {
            if($v['discoin'] > 0 && empty($v['disrate'])) {
                $v['disrate'] = $v['discoin'];
                unset($v['discoin']);
            }
            if(strlen($v['disrate']) > 0 ) {
                $v['note'] .= ' ' . $v['disrate'];
            }
            $items[] = $v;
        }

        $retData['list'] = $items;
        return $this->backSucc($retData);
    }

    //第3方收款账号列表
    public function upis(Request $request) {
        $retData = [
            'totalcoin' => 0
        ];
        $groupid = $request->get('groupid', 0);
        $userinfo = DUser::where('uid', $this->uid)->field('uid,coin,cashbonus,totalbet,totalwin,totaldraw,maxdraw,candraw,svip')->find();
        $svip = ($userinfo && isset($userinfo['svip'])) ? intval($userinfo['svip']) : 1;
        if($svip <= 0) {
            $svip = 0;
        }

        $usercoins = $this->getUserCoin($this->uid);
        $retData['totalcoin'] = $usercoins['totalcoin'];
        $groupModel = new SPayGroup();
        $groups = [];
        if($groupid == 0) {
            $groups = $groupModel->getDataBySvip($svip);
        } else {
            $group = SPayGroup::find($groupid);
            $groups[] = $group;
        }
        if(empty($groups)) {
            return $this->backSucc($retData, 'no data');
        }

        $chanModel = new SPayCfgChannel();
        $datalist = [];
        foreach ($groups as $k=>$row) {
            $res = $chanModel->getDataListByGroup($row['id'], $svip);
            if(!empty($res)) {
                foreach($res as $key=>$val) {
                    $item = [
                        'id'    => $val['id'],
                        'title' => $val['title'],
                        'type'  => 0,
                        'icon' => Config::get('pay.img_url') . $row['icon'],
                        'mincoin' => $val['mincoin'],
                        'maxcoin' => $val['maxcoin'],
                        'disrate' => $val['disrate'],
                        'discoin' => $val['discoin'],
                        'value' => $val['id'] .'',
                        'rate_str' => '',
                    ];
                    if($row['id'] == 6) {
                        $item['type'] = 1;
                    }
                    if($item['disrate'] > 0) {
                        $item['disrate'] = number_format($item['disrate']*100, 2);
                        $item['rate_str'] = "+{$item['disrate']}%";
                    }
                    if($item['discoin'] > 0) {
                        $item['rate_str'] = "+{$item['discoin']}";
                    }
                    $datalist[] = $item;
                }
            }
        }

        $retData['list'] = $datalist;
        return $this->backSucc($retData);
    }

    public function chanupi() {
        $id = $this->request->get('id', 0);
        if(!$id) {
            return $this->backErr('no data');
        }

        $userinfo = DUser::where('uid', $this->uid)->field('uid,coin,svip')->find();
        $svip = ($userinfo && isset($userinfo['svip'])) ? intval($userinfo['svip']) : 1;
        if($svip <= 0) {
            $svip = 1;
        }
        $bankModel = new SPayBank();
        $res = $bankModel->getDataListByIdAndSvip($id, $svip);
        if(empty($res)) {
            return $this->backErr('no data');
        }
        return $this->backSucc(['list'=>$res]);
    }

    //获取银行收款账号列表
    public function banks() {
        $groupid = $this->request->get('groupid', 8);
        $userinfo = DUser::where('uid', $this->uid)->field('uid,coin,svip')->find();
        $svip = ($userinfo && isset($userinfo['svip'])) ? intval($userinfo['svip']) : 1;
        if($svip <= 0) {
            $svip = 1;
        }
        $bankModel = new SPayBank();
        $res = $bankModel->getDataListBySvip($svip, $groupid);
        if(empty($res)) {
            return $this->backErr('no data');
        }
        $datalist = [];
        foreach($res as $k=>$v) {
            unset($v['isqrcode']);
            unset($v['memo']);
            unset($v['username']);
            $v['rate_str'] = '';
            if($v['disrate'] > 0) {
                $v['disrate'] = number_format($v['disrate']*100, 2);
                $v['rate_str'] = "+{$v['disrate']}%";
            }
            $datalist[] = $v;
        }
        return $this->backSucc(['list'=>$datalist]);
    }

    //银行收款信息详情
    public function bankinfo() {
        $id = $this->request->get('id', 0);
        if(!$id) {
            return $this->backErr('no data');
        }

        $userinfo = DUser::where('uid', $this->uid)->field('uid,coin,svip')->find();
        $svip = ($userinfo && isset($userinfo['svip'])) ? intval($userinfo['svip']) : 0;
        if($svip < 0) {
            $svip = 0;
        }
        $bankModel = new SPayBank();
        $res = $bankModel->getDataListByIdAndSvip($id, $svip);
        if(empty($res)) {
            return $this->backErr('no data');
        }
        $data = $res[0];
        if(!empty($data)) {
            $group = SPayGroup::find($data['groupid']);
            $data['gtitle'] = $group['title'];
        }
        if(!empty($data['qrcode'])) {
            $data['qrcode'] = Config::get('pay.img_url') . $data['qrcode'];
        }

        if(isset($data['discoin']) && $data['discoin'] > 0) {
            $data['discoin'] = $data['discoin'];
            $data['disrate'] = 0;
        }

        $tmp = $this->getOrdersByGroup([$group]);
        $orders = $tmp[0]; //总订单数
        $orders_today = $tmp[1]; //今日订单数
        $order_key = $group['id'].'_'.$data['id'].'_2';
        $order_key2 = $group['id'].'_'.$data['id'].'_3';
        if(!isset($orders_today[$order_key]) && !isset($orders_today[$order_key2])) { //当天首次
            if($data['fd_dcoin'] > 0) {
                $data['discoin'] = $data['fd_dcoin'];
                $data['disrate'] = 0;
            }
            if($data['fd_drate'] > 0) {
                $data['disrate'] = $data['fd_drate'];
            }
        }

        //首次
        if(!isset($orders[$order_key]) && !isset($orders[$order_key2]) ) {
            if($data['f_dcoin'] > 0) {
                $data['discoin'] = $data['f_dcoin'];
                $data['disrate'] = 0;
            }
            if($data['f_drate'] > 0) {
                $data['disrate'] = $data['f_drate'];
            }
        }

        $pics = [];
        $screenshot = $data['screenshot'];
        if(!empty($screenshot)) {
            $pics = explode(',', $screenshot);
            foreach($pics as $k=> $v) {
                $pics[$k] = Config::get('pay.img_url') . $v;
            }
            unset($data['screenshot']);
        }
        $data['pics'] = $pics;
        unset($data['fd_dcoin'], $data['fd_drate'], $data['f_dcoin'], $data['f_drate']);
        return $this->backSucc(['detail'=>$data]);
    }

    //上传图片
    public function uploadFile() {
        $file = request()->file('file');
        $msg = 'Image upload failed!';
        try{
            validate(['imgFile'=>[
                'fileSize' => 5 * 1024 * 1024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif', //这个一定要加上，很重要我认为！
            ]])->check(['imgFile' => $file]);
            // 上传图片到本地服务器

            $thumb = 'storage/';
            $date = date('Ymd');
            //判断文件是否存在，不存在就创建
            file_exists($thumb.$date)?'':mkdir($thumb.$date,0777,true);

            $fileName = $this->uid .'_'. md5(microtime(true) . $file->getPathname()). '.' . $file->extension();
            $saveName = \think\facade\Filesystem::disk('public')->putFileAs($date,$file, $fileName);
//            $imgurl = Config::get('pay.img_url'). '/storage/'. $saveName;
            $url = Env::get('img_url');
            $imgurl = $url. '/storage/'. $saveName;
            $rand = rand(0, 5);
            $domain = Env::get('img_domain');
            //img_domain=rummyvip.com
            //img_url=https://img.rummyvip.com
            if(empty($domain)) {
                $domain = '43.128.206.225';
            }
            if($rand > 0) {
                $imgurl = "https://img{$rand}.{$domain}/storage/{$saveName}";
            }
            $arr = ['code' => 200, 'msg' => '', 'url' => $imgurl];
            $this->imme_return($arr, 60*3);
//            return json($arr);
            $root = app()->getRootPath();
            $cmd = "sh {$root}/flush_cdn.sh {$saveName}";
            $result = shell_exec($cmd);
            $str = date('YmdHis') . "刷CDN: {$imgurl} result：". $result;
            Log::write($str, 'debug');
            return;
        } catch (\Exception $e) {
            $msg .= $e->getMessage();
        }
        $ret = [
            'code' => 500,
            'msg' => $msg,
        ];
        return json($ret);
    }

    private function imme_return($data ='',$set_time_limit=20)
    {
        $str=is_string($data)  ? $data : json_encode($data);
        echo $str;
        if(function_exists('fastcgi_finish_request')){			//Nginx使用
            fastcgi_finish_request();
        }else {			//apache 使用
            $size = ob_get_length();
            header("Content-length: $size");
            header('Connection:close');
            ob_end_flush();
            //ob_flush();       //加了没效果
            flush();
        }
        ignore_user_abort(true);
        set_time_limit($set_time_limit);
        return true;
    }
}
