<?php

namespace app\controller;

use app\BaseController;
use app\model\DKyc;
use app\model\DUser;
use app\model\DUserBind;
use app\model\SConfig;
use org\Curl;
use think\facade\Db;
use think\facade\Log;

//kyc验证相关

class Kyc extends BaseController
{
    static $STATUS = [
        1 => '待审',
        2 => '审核中',
        3 => '通过',
        4 => '拒绝',
    ];

    private function getKYCStatus($uid, $isbindphone) {
        $kyc_status = [
            'mobile' => 0,
            'pan' => 0,
            'bank' => 0,
        ];
        $items = DKyc::where('uid', $uid)->select()->toArray();
        if(empty($items)) {
            if($isbindphone) {
                $kyc_status['mobile'] = 2;
            }
            return $kyc_status;
        }
        foreach($items as $row) {
            if($row['category'] == 1) {
                $kyc_status['mobile'] = intval($row['status']);

            } elseif($row['category'] == 2) {
                $kyc_status['pan'] = intval($row['status']);
            } elseif($row['category'] == 3) {
                $kyc_status['bank'] = intval($row['status']);
            }
        }
        if($kyc_status['mobile'] == 0) {
            if($isbindphone) {
                $kyc_status['mobile'] = 2;
            }
        }
        return $kyc_status;
    }

    private function getKYCItemNote($uid, $type) {
        $cat = 2;
        if($type == 'mobile') {
            $cat = 1;
        } elseif ($type == 'pan') {
            $cat = 2;
        } elseif ($type == 'bank') {
            $cat = 3;
        }
        $item = DKyc::where('uid', $uid)->where('category', $cat)->find();
        if($cat == 1) {
            return $item['username'];
        }
        $note = substr_replace($item['cardnum'], '*', 0, strlen($item['cardnum'])-4);
        return $note;
    }

    //获取kyc拒绝的备注
    private function getKYCMemo($uid, $type) {
        $cat = 2;
        if($type == 'mobile') {
            $cat = 1;
        } elseif ($type == 'pan') {
            $cat = 2;
        } elseif ($type == 'bank') {
            $cat = 3;
        }

        $item = DKyc::where('uid', $uid)->where('category', $cat)->find();
        if(!empty($item)) {
            return $item['memo'];
        }
        return '';
    }

    //获取kyc打开的验证列表
    public function index() {
        $fields = 'uid,gamedraw,coin,kyc,cashbonus,svip,ispayer,kycfield, isbindphone';
        $userinfo = DUser::where('uid', $this->uid)->field($fields)->find();

        $info = $this->getKYCInfo($userinfo['kycfield']);
        $result = $info['data'];
        uasort($result, function($a, $b) {
            if($a['ord'] == $b['ord']) {
                return 0;
            }
            return ($a['ord'] < $b['ord']) ? -1 : 1;
        });

        $kycstatus = $this->getKYCStatus($this->uid, $userinfo['isbindphone']);
        $previtem = [ //第1个未通过的
            'ord' => 0,
        ];
        foreach ($result as $k=>$v) {
            $status = $kycstatus[$k];
            if($status < 2 or $status==3) { //未通过审核或被拒绝
                $previtem['ord']  = $v['ord'];
                break;
            }
        }

        foreach($result as $k=>$v) {
            $result[$k]['status'] = $kycstatus[$k];

            $result[$k]['link'] = false;
            if($result[$k]['status'] < 2) {
                $result[$k]['link'] = true;
            }elseif($result[$k]['status'] == 2) {
                $result[$k]['note'] = $this->getKYCItemNote($this->uid, $k);
            }
            if($result[$k]['status'] == 3) {
                $result[$k]['link'] = true;
                $result[$k]['note'] = $this->getKYCMemo($this->uid, $k);
            }
            if($v['ord'] > $previtem['ord']) {
                $result[$k]['link'] = false;
            }
        }

        $configModel = new SConfig();
        $key = 'kycmemo';
        $item = $configModel->getItem($key);
        $memo = $item['v'];
        $res = ['list'=>$result,'memo'=>$memo];
        $res['customer'] = $this->getEmail(4);
        return $this->backSucc($res);
    }

    //kyc绑定入口
    public function bind() {
        $cat = $this->request->post('cat', '');
        $types = ['mobile','pan','bank'];
        if(!in_array($cat, $types)) {
            return $this->backErr('Wrong action type', 501);
        }
        $userinfo = $this->getUserCoin($this->uid);
        if($userinfo['kyc']) {
            return $this->backErr('You have passed KYC verification and cannot continue uploading information.', 501);
        }

        $save = 0;
        $nowtime = time();
        $model = new DKyc();
        $id = $this->request->post('id', 0);
        $needpic = 1;//需要上传图片
        if($cat == 'bank') {
            $needpic = $this->getKycBankPicFlag();
        }
        if($cat == 'mobile') {
            $name = trim($this->request->post('name'));
            $mobile = trim($this->request->post('mobile'));
            $passwd = $this->request->post('passwd','','trim');
            $opt = $this->request->post('code',''); //验证码
            if(empty($opt)) {
                return $this->backErr('Please fill in the Opt code.', 502);
            }
            $redis= $this->getRedisObj();
            $optkey = 'code:'.$mobile;
            $opt_cache = $redis->get($optkey);
            if($opt_cache != $opt) {
                return $this->backErr('Opt code error.', 502);
            }
            $count = DUserBind::where('unionid', "{$mobile}")->count();
            if($count >0 ) {
                return $this->backErr('This Mobile number has been bound to other player.', 503);
            }
            $where = [];
            $where[] = ['uid','=', $this->uid];
            $where[] = ['category','=',1];
            $where[] = ['status', '=', 2];
            $count = DKyc::where($where)->count();
            if($count >0 ) {
                return $this->backErr('You have passed the phone number verification.', 504);
            }
            $redis->del($optkey);

            $pwdmd5 = md5($passwd.'|'.$this->uid);
            $binddata = [
                'uid' => $this->uid,
                'unionid' => $mobile,
                'passwd' => $pwdmd5,
                'create_time' => $nowtime
            ];
            $dubModel = new DUserBind();
            $save = $dubModel->save($binddata);
            $binddata['nickname'] = '';
            $binddata['sex'] = 0;
            $binddata['platform'] = 0;
            $redis->hMSet('d_user_bind:'.$mobile, $binddata);
            $userInfo = DUser::where('uid', $this->uid)->find();
            $svip = intval($userInfo['svip']);
            $kycdata = [
                'username' => $name,
                'cardnum' => $mobile,
                'svip' => $svip,
                'status' => 2,
                'category' => 1,
                'create_time' => $nowtime,
                'uid' => $this->uid,
            ];
            $save2 = $model->save($kycdata);

            $updata = [
                'isbindphone' => 1,
                'username' => $name,
                'playername' => $name,
                'phone' => $mobile,
            ];
            DUser::where('uid', $this->uid)->update($updata);
            $redis->hset('d_user:'.$this->uid, 'isbindphone', 1);
            $redis->hset('d_user:'.$this->uid, 'phone', $mobile);

            $curl = new \org\Curl();
            $curl->bindinfo($this->uid, 'isbindphone');

        } elseif($cat == 'pan') {
            $name = $this->request->post('name');
            $pan = $this->request->post('pan');
            $birthday = $this->request->post('birthday');
            $pic = $this->request->post('pic');
            if(empty($pic)) {
                return $this->backErr('Please upload a picture.', 503);
            }
            if(empty($name) || empty($pan) || empty($birthday)) {
                return $this->backErr('Please fill in your Name or Pan or Birthday.', 503);
            }
            $count = DKyc::where('cardnum', "{$pan}")->where('uid','<>', $this->uid)->count();
            if($count >0 ) {
                return $this->backErr('This PAN number has been bound to other player.', 503);
            }
            $cond = [
                ['category' ,'=', 2],
                ['status' ,'=', 1],
            ];
            $count = DKyc::where($cond)->where('uid','=', $this->uid)->count();
            if($count >0 ) {
                return $this->backErr('Your certification is being reviewed, please wait.', 503);
            }
            $userInfo = DUser::where('uid', $this->uid)->find();
            $svip = intval($userInfo['svip']);
            $data = [
                'username' => $name,
                'cardnum' => $pan,
                'pic1' => $pic,
                'svip' => $svip,
                'birthday' => $birthday,
                'status' => 1,
                'category' => 2,
                'create_time' => $nowtime,
                'uid' => $this->uid,
            ];
            $item = DKyc::where('id', $id)->where('uid', $this->uid)->find();
            if(!$item) {
                $save = $model->save($data);
            } else {
                $data['update_time'] = $nowtime;
                $data['uuid'] = $this->uid;
                unset($data['create_time']);
                unset($data['uid']);
                $save = DKyc::where('id', $id)->update($data);
            }

        } elseif ($cat == 'bank') {
            $account = $this->request->post('account','','trim');
            $username = $this->request->post('branch','','trim');
//            $bankname = $this->request->post('bankname');
            $ifsc = $this->request->post('ifsc','','trim');
//            $state = $this->request->post('state'); //州
            $pic = $this->request->post('pic','','trim');
            if(empty($account) || empty($username) || empty($ifsc)) {
                return $this->backErr('Please fill in your Account or Name.', 503);
            }
            $flag = $this->getKycBankPicFlag();
            if($flag && empty($pic)) {
                return $this->backErr('Please fill in your Image.', 503);
            }
            $count = DKyc::where('cardnum', "{$account}")->where('uid','<>', $this->uid)->count();
            $sql = DKyc::getLastSql();
            Log::info($sql. ' count:'. $count . PHP_EOL);
            if($count >0 ) {
                return $this->backErr('This bank account number has been bound to other player.', 503);
            }

            $cond = [
                ['category','=', 3],
                ['status' ,'=',  1],
            ];
            $count = DKyc::where($cond)->where('uid','=', $this->uid)->count();
            if($count >0) {
                return $this->backErr('Only one bank account accepted to verify before successfully withdraw.', 503);
            }

            $userInfo = DUser::where('uid', $this->uid)->field('uid,drawsucctimes')->find();
            $drawsucctimes = intval($userInfo['drawsucctimes']);
            $cond = [
                ['category','=', 3],
                ['status' ,'=',  2],
            ];
            $count = DKyc::where($cond)->where('uid','=', $this->uid)->count();
            if($count >0 && $drawsucctimes == 0) {
                return $this->backErr('Only one bank account accepted to verify before successfully withdraw.', 503);
            }
            $status = 1 ; //默认待审核

            if(!$needpic) {
                $status = 2; //不需要图片，就不要后台审核
            }
            $data = [
                'username' => $username,
                'cardnum' => $account,
                'svip' => intval($userInfo['svip']),
//                'bankname' => $bankname,
                'ifsc' => $ifsc,
                'pic1' => $pic,
                'status' => $status,
                'category' => 3,
//                'memo' => $state,
                'create_time' => $nowtime,
                'uid' => $this->uid,
            ];
            $item = DKyc::where('id', $id)->where('uid', $this->uid)->find();
            if(!$item) {
                $id = Db::name('d_kyc')->insertGetId($data);
                if($id > 0) {
                    $save = true;
                }
//                $save = $model->save($data);
            } else {
                $data['update_time'] = $nowtime;
                $data['uuid'] = $this->uid;
                unset($data['create_time']);
                unset($data['uid']);
                $save = DKyc::where('id', $id)->update($data);
            }
        }
        if($save) {
            if(!$needpic && $cat == 'bank') {
                //1种类型可能有多条记录,但只会有1条通过
                $this->updateUserKyc($this->uid);
                $curl = new Curl();
                $data = [
                    'mod'=>'user',
                    'act'=>'kycverify',
                    'id' => $id, //id
                    'uid'=>$this->uid,
                    'cat'=>3,
                    'status' => 2
                ];
                $back = $curl->resetName($data);
            }
            return $this->backSucc();
        }
        return $this->backErr('KYC submit verification failed!', 502);
    }

    //更新更人kyc信息
    private function updateUserKyc($uid) {
        $status_pass = 2;
        //1种类型可能有多条记录,但只会有1条通过
        $pan_count = DKyc::where('uid',$uid)->where('status',$status_pass)->where('category',2)->count();
        $bank_count = DKyc::where('uid',$uid)->where('status',$status_pass)->where('category',3)->count();
        $userid = $uid; //用户uid
        $kyced = true; //是否通过kyc的标记
        $verify = SConfig::where('k','kycverify')->find();
        if($verify and !empty($verify['v'])) {
            $verifylist = json_decode($verify['v'], true);
            foreach ($verifylist as $key => $row) {
                if($key =='pan' && ($row['open'] ==1 && $pan_count <1)) {
                    $kyced = false;
                }
                if($key =='bank' && ($row['open'] ==1 && $bank_count <1)) {
                    $kyced = false;
                }
            }
            $user = DUser::where('uid', $userid)->field('kycfield')->find(); //个人的kyc配置项
            $kycfield = $user['kycfield'];
            if($kyced && $kycfield > 0) {
                $userKycArr = [
                    'mobile' => 0,
                    'pan'    => 0,
                    'bank'   => 0,
                ];
                if($kycfield%10 == 1) {
                    $userKycArr['mobile'] = 1;
                }
                if(floor($kycfield/100) == 1) {
                    $userKycArr['bank'] = 1;
                }
                $fields = str_split(strval($kycfield));
                if((count($fields) ==2 && $fields[0]==1) || (count($fields)==3 && $fields[1]==1)) {
                    $userKycArr['pan'] = 1;
                }
                if($userKycArr['mobile']) {
                    $mobile_count = DKyc::where('uid',$uid)->where('status',$status_pass)->where('category',1)->count();
                    if($mobile_count < 1) {
                        $kyced = false;
                    }
                }
                if($userKycArr['pan'] && $pan_count < 1) {
                    $kyced = false;
                }
                if($userKycArr['bank'] && $bank_count < 1) {
                    $kyced = false;
                }
            }
        }
        if($kyced) {
            $update = [
                'kyc' => 1,
            ];
            DUser::where('uid', $uid)->update($update);
            $curl = new Curl();
            $curl->bindinfo($uid, 'kyc');
            return true;
        }
        return false;
    }

    //获取绑定手机号时候绑定的fullname
    private function getFullName($uid) {
        $item = DKyc::where('uid', $uid)->where('category', 1)->find();
        if(!$item) {
            return '';
        }
        return $item['username'];
    }

    //获取kyc的单个信息，配合客户端进行修改
    public function info() {
        $cat = $this->request->get('cat', '');
        $poptype = $this->request->get('bankpop', 0); //提醒弹框
        $types = ['pan','bank'];
        if(!in_array($cat, $types)) {
            return $this->backErr('Type error', 501);
        }
        $category = 2;
        if($cat == 'pan') {
            $category = 2;
        } elseif($cat == 'bank') {
            $category = 3;
        }
        $data = [];
        $data['name'] = $this->getFullName($this->uid);
        $item = DKyc::where('uid', $this->uid)->where('category', $category)->find();
        if($item && !empty($item)) {
            $data = [
                'id' => $item['id'],
                'username' => $data['name'],
                'cardnum' => $item['cardnum'],
                'pic' => $item['pic1'],
                'birthday' => $item['birthday'],
                'status' => $item['status'],
            ];
        }
        if($category == 3) {
            $data['ifsc'] = $item['ifsc'];
        }
        $res = ['item'=>$data];
        $res['popmsg'] = '';
        if(intval($poptype) < 1) {
            $ret = Db::table('s_pay_popup')->field('content')->where('cat','=', 3)->where('status', '=', 1)->select()->toArray();
            if(isset($ret[0])) {
                $res['popmsg'] = $ret[0]['content'];
            }
        }
        $res['picopen'] = $this->getKycBankPicFlag() ? 1 : 0;
        return $this->backSucc($res);
    }

    //获取绑定手机号时候填写的fullname
    public function name() {
        $poptype = $this->request->get('bankpop', 0); //提醒弹框
        $data = [];
        $data['name'] = $this->getFullName($this->uid);
        $res = ['item'=>$data];
        $res['popmsg'] = '';
        if(intval($poptype) < 1) {
            $ret = Db::table('s_pay_popup')->field('content')->where('cat','=', 3)->where('status', '=', 1)->select()->toArray();
            if(isset($ret[0])) {
                $res['popmsg'] = $ret[0]['content'];
            }
        }

        return $this->backSucc($res);
    }
}