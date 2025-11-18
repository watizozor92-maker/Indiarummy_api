<?php

namespace app\controller;

use app\BaseController;
use think\facade\Config;

class Img extends BaseController
{
    static $FILE_LOG = '/tmp/test5.txt';

    public function index() {
        exit;
        echo 'hello';
    }

    //public path
    private function getPublicPath() {
        $Absolute_Path=$_SERVER['SCRIPT_FILENAME'];
        $Absolute_Path=substr($Absolute_Path,0,-9);
        return $Absolute_Path;
    }

    private function getRandStr($length){
        $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len=strlen($str)-1;
        $randstr='';
        for($i=0;$i<$length;$i++){
            $num=mt_rand(0,$len);
            $randstr .= $str[$num];
        }
        return $randstr;
    }

    function base64imgsave($img, $fileName){
        $b64img = substr($img, 0,100);
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $b64img, $matches)){

            $path = $this->getPublicPath();
            $ymd  = date("Ymd");
            $basedir = 'storage/'.$ymd.'';
            $fullpath = $path.$basedir;
            if(!is_dir($fullpath)){
                mkdir($fullpath,0777,true);
            }

            $photo = '/'.$fileName;
            $base64_image = str_replace($matches[1], '', $img);
            $filePath = $fullpath.$photo;
            file_put_contents(self::$FILE_LOG, $filePath."\n\r", FILE_APPEND);
            file_put_contents($filePath, base64_decode($base64_image));
            $url = Config::get('pay.img_url'). '/'. $basedir.'/'.$fileName;
            return $url;
        }
        return 500;
    }

    public function upload() {
        exit;
        $postData = file_get_contents('php://input');
        file_put_contents(self::$FILE_LOG, strval($postData)."\n\r", FILE_APPEND);


        list($user, $base64_image_content) = explode('&img=', $postData);
        list($str, $uid) = explode('=', $user);
        if(!preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
            $ret['code'] = 502;
            $ret['msg'] = 'file ext error!';
            return json($ret);
        }
        $pos  = strpos($base64_image_content, ';');
        $imgType = explode(':', substr($base64_image_content, 0, $pos))[1];
        try {
            $objName = $uid . '_'. $this->getRandStr(8) . '.png';
            file_put_contents(self::$FILE_LOG, $objName."\n\r", FILE_APPEND);
            $url = $this->base64imgsave($base64_image_content, $objName);
            if($url == 500) {
                $ret['code'] = 500;
                $ret['msg'] = 'file error1!' . $uid . ' yellow';
                file_put_contents(self::$FILE_LOG, json_encode($ret)."\n\r", FILE_APPEND);
                return json($ret);
            }

            $ret['url'] = $url;
            $ret['uid'] = $uid;

            file_put_contents(self::$FILE_LOG, json_encode($ret)."\n\r", FILE_APPEND);
            // unlink($tmp_path);
        } catch (\Exception\Exception $e) {
            file_put_contents(self::$FILE_LOG, date('Y-m-d H:i:s') . json_encode($e), FILE_APPEND);
            $ret['code'] = 500;
            $ret['msg'] = 'upload failed!';
        }
        return json($ret);
    }
}