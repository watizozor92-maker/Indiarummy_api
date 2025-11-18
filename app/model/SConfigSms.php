<?php
namespace app\model;

use think\Model;

//短信通道配置

class SConfigSms extends Model
{
    protected $table = 's_config_sms';

    public function getDataList() {
        return $this->where('status', 1)->order('weight','desc')->select()->toArray();
    }
}