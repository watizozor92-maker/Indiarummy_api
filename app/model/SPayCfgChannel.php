<?php
namespace app\model;
use think\Model;

//线上收款渠道的优惠

class SPayCfgChannel extends Model
{
    protected $table = 's_pay_cfg_channel';

    public function getDataListByGroup($groupid, $svip, $labelid=0)
    {
//        $where = [['svip','like', '%' . $svip . ',%'], ['svip','like','%,' . $svip . '%']];
//        $datalist = $this->where('groupid', $groupid)->where(function($query) use ($where){$query->whereOr($where);})->where('status', 1)->select()->toArray();
        if($labelid) {
            $datalist = $this->where('groupid', $groupid)->where([["nodislabelid", '=', $labelid]])->where('status', 1)->select()->toArray();
        } else {
            $datalist = $this->where('groupid', $groupid)->whereRaw("FIND_IN_SET({$svip},svip)")->where('status', 1)->select()->toArray();
        }
        return $datalist;
    }

}