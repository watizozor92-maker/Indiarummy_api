<?php

namespace app\model;
use think\Model;

//支付分组
class SPayGroup extends Model
{
    protected $table = 's_pay_group';

    //获取支付等级对方的支付分组方法
    public function getDataBySvip($svip , $id=0) {
        if($id > 0 ){
            $datalist = $this->where('status',1)->where('id', $id)->field('id,title,icon,category,subject,recommend')->order('ord','asc')->select()->toArray();
        } else {
//            $where = [['svip','like', '%' . $svip . ',%'], ['svip','like','%,' . $svip . '%']];
            $datalist = $this->where('status',1)->whereRaw("FIND_IN_SET({$svip},svip)")->field('id,title,icon,category,subject,recommend')->order('ord','asc')->select()->toArray();
        }
        return $datalist;
    }
}