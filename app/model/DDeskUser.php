<?php

namespace app\model;

use think\Model;
class DDeskUser extends Model
{
    protected $table = 'd_desk_user';

    public function searchUidAttr($query, $uid)
    {
        $query->where('uid', $uid); 
    }

    public function searchCreateTimeAttr($query, $startTime, $endTime)
    {
        $query->whereBetweenTime('create_time', $startTime, $endTime);
    }
}