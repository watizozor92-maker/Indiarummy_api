<?php
namespace app\model;
use think\Model;

//交易科目优惠(支付分组)

class SPayDiscount extends Model
{
    protected $table = 's_pay_discount';

    //根据交易科目所有有效的数据列表
    public function getDataListBySubject($subject)
    {
        $datalist = $this->where('subject', $subject)->where('status', 1)->select()->toArray();
        return $datalist;
    }

}