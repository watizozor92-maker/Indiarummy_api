<?php

namespace app\model;
use think\Model;

//提现限制
class SPayCfgDrawlimit extends Model
{
    protected $table = 's_pay_cfg_drawlimit';

    //获取支付等级对方的支付分组方法
    public function getDataBySvip($uid, $svip, $superArr=[]) {
        $where = [];
        if($uid ) {
            $where[] = ['useruid', '=', $uid];
        }

        if(!empty($superArr)) {
            $where[] = ['superuid','in','('.implode(',',$superArr).')'];
        }
        if($svip) {
            $datalist = $this->whereOr($where)->whereRaw("FIND_IN_SET({$svip},svip)")->order('id','desc')->select()->toArray();
        } else {
            $datalist = $this->whereOr($where)->order('id','desc')->select()->toArray();
        }

        return $datalist;
    }

    //根据支付vip等级获取提现的限制
    public function getMaxLimitByUidAndSvip($uid, $svip) {
        $result = [
            'daytimes' => -1, //当天最大次数
            'daycoin' => -1, //当天最大金额
            'totaltimes' => -1, //总提现次数
            'totalcoin' => -1, //总提现金额
        ];
        $res = $this->getDataBySvip($uid, $svip);
        if(!empty($res)) {
            foreach($res as $row) {
                if($row['times'] > $result) {
                    $result['daytimes'] = $row['times'];
                }
                if($row['daycoin'] > $result['daycoin']) {
                    $result['daycoin'] = $row['daycoin'];
                }
                if($row['totaltimes'] > $result['totaltimes']) {
                    $result['totaltimes'] = $row['totaltimes'];
                }
                if($row['totalcoin'] > $result['totalcoin']) {
                    $result['totalcoin'] = $row['totalcoin'];
                }
            }
        }
        return $result;
    }
}