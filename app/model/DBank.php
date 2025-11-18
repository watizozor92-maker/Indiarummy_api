<?php

namespace app\model;

use think\Model;
//保险箱
class DBank extends Model
{
    protected $table = 'd_bank';

    //获取用户保险箱内余额
    public function getBankCoin($uid) {
        $item = $this->where('uid', $uid)->find();
        if($item && $item['coin']) {
            return intval($item['coin']);
        }
        return 0;
    }
}