<?php

namespace app\model;

use think\Model;
//客户反馈

class DCustomerService extends Model
{
    protected $table = 'd_customer_service';

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'create_time'=>'timestamp',
    ];
}