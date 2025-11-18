<?php

namespace app\model;
use think\Model;

//提现渠道
class SPayCfgDrawcom extends Model
{
    protected $table = 's_pay_cfg_drawcom';

    public function getDataBySvip($svip) {
        $whereStatus = [];
        $whereStatus[] = ['status','=',1];
        if($svip) {
            $datalist = $this->where($whereStatus)->whereRaw("FIND_IN_SET({$svip},svip)")->order('id','desc')->select()->toArray();
        } else {
            $datalist = $this->where($whereStatus)->order('id','desc')->select()->toArray();
        }
        return $datalist;
    }
}