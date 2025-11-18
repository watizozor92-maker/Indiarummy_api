<?php

namespace app\model;

use think\Model;

//无优惠标签id
class DDiscountLabel  extends Model
{
    protected $table = 'd_discount_label';

    protected $dateFormat = 'Y-m-d H:i:s';
    protected $type = [
        'create_time'=>'timestamp',
    ];
}
