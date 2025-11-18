<?php

namespace app\model;

use think\Model;

//用户银行卡信息
class DUserBank extends Model
{
    protected $table = 'd_user_bank';

    public function getBankinfoById($id, $uid) {
        $item = $this->where('uid',$uid)->where('id', $id)->find();
        return $item;
    }

}