<?php

namespace app\model;

use think\Model;
//用户提现记录
class DUserDraw extends Model
{
    protected $table = 'd_user_draw';

    public function getTimes($uid, $today = false) {
        $where = [];
        if($uid ) {
            $where[] = ['uid', '=', $uid];
        }
        if($today) {
            $todaytime = strtotime(date('Y-m-d'));
            $where[] = ['create_time','>=', $todaytime];
        }
        $where[] = ['status','=', 2]; //成功次数
        $total = $this->whereOr($where)->count('id');
        return $total;
    }
}