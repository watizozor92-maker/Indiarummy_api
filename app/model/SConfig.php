<?php

namespace app\model;

use think\Model;

//文件
class SConfig extends Model
{
    protected $table = 's_config';

    public function getItem($key) {
        $data = $this->where('k', $key)->find();
        return $data;
    }
}