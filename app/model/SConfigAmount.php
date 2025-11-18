<?php

namespace app\model;

use think\Model;

//快捷金额

class SConfigAmount extends Model
{
    protected $table = 's_config_amount';

    //根据类型获取
    //1充值,2提现
    public function getDataList($type=1) {
        return $this->where('type', $type)->where('status', 1)->field('id,amount')->order('amount','asc')->select()->toArray();
    }
}