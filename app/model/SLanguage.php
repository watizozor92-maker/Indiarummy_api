<?php

namespace app\model;

use think\Model;
class SLanguage extends Model
{
    protected $table = 's_language';

    public function getDataList() {
        return $this->where('status', 1)->order('id','desc')->field('id,title')->select()->toArray();
    }
}