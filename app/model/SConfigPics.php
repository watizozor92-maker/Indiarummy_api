<?php

namespace app\model;

use think\Model;

//广告图片
class SConfigPics extends Model
{
    protected $table = 's_config_pics';

    //根据类型获取广告图片
    //1大厅,2充值,3提现,4优惠
    public function getDataList($type=2) {
        return $this->where('cat', $type)->where('status', 1)->order('ord','asc')->select()->toArray();
    }
}