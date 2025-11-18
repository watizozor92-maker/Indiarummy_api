<?php

namespace app\model;

use think\Model;

class SGame extends Model
{
    protected $table = 's_game';

   public function getAllData() {
       return $this->where('status',1)->field('id,title')->select()->toArray();
   }

   public function getTitleDict() {
       $datalist = $this->getAllData();
       $arr = [];
       foreach($datalist as $row) {
           $arr[$row['id']] = $row['title'];
       }
       return $arr;
   }
}