<?php
namespace app\controller;

use app\BaseController;
use app\model\DCustomerService;
use app\model\DUser;
use app\model\SConfig;
use app\model\SLanguage;
use think\facade\Request;
use think\App;
//服务相关

class Service extends BaseController
{
    /**
     * 支持语言
     * 请求方式：GET
     * 参数:
     *  uid:用户uid
     *  token: 用户的token
     **/
    public function index()
    {
        $retData = [
            'list' => []
        ];
        $langModel = new SLanguage();
        $res = $langModel->getDataList();
        if(!empty($res)) {
            foreach($res as $key=>$val) {
                $item = [
                    'id' => $val['id'],
                    'title' => $val['title']
                ];
                $retData['list'][] = $item;
            }
        }
        return $this->backSucc($retData);
    }

    /**
     * 客服反馈
     * 请求方式：POST
     * 参数:
     *  uid:用户uid
     *  token: 用户登录的token
     *  phone: 留言的手机号码
     *  language: 语言类型
     *  content: 留言内容
     **/
    public function feedback() {
        $phone    = $this->request->post('phone'); //电话
        $category = $this->request->post('category', 5); //类型
        $language = $this->request->post('language'); //语言
        $content  = $this->request->post('content'); //内容
        //参数判断
        if(empty($phone) or empty($language) or empty($content)) {
            return $this->backErr('no data!', 501);
        }
        //频率控制
        $cacheKey = 'feedback_'.$this->uid;
        $lastTime = Cache::store('redis')->get($cacheKey);
        $nowtime = time();
        if(($nowtime - $lastTime) < 10) {
            return $this->backErr('too frequently!', 503);
        }
        Cache::store('redis')->set($cacheKey, $nowtime);

        $userinfo = DUser::where('uid', $this->uid)->field(["uid","playername","svip","coin"])->find();
        if(!$userinfo) {
            return $this->backErr('userinfo err!', 504);
        }
        $data = [
            'uid' => $this->uid,
            'playername' => $userinfo['playername'],
            'language' => $language,
            'category' => $category,
            'phone' => $phone,
            'content' => $content,
            'create_time' => $nowtime,
            'status' => 1,
        ];
        $csModel = new DCustomerService();
        $ret = $csModel->save($data);
        if(!$ret) {
            return $this->backErr('feedback failed!', 505);
        }
        return $this->backSucc();
    }
}
