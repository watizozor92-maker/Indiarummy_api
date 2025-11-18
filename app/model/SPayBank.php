<?php
namespace app\model;
use think\Model;

//线下收款方式

class SPayBank extends Model
{
    protected $table = 's_pay_bank';

    public function getDataListByGroup($groupid, $svip, $labelid=0)
    {
        if($labelid) {
            return $this->where('groupid', $groupid)->where('status', 1)->where("nodislabelid", $labelid)->order('ord','desc')->select()->toArray();
        }
        return $this->where('groupid', $groupid)->where('status', 1)->whereRaw("FIND_IN_SET({$svip},svip)")->order('ord','desc')->select()->toArray();
    }

    public function getDataListByIdAndSvip($id, $svip)
    {
        return $this->where('id', $id)->where('status', 1)->whereRaw("FIND_IN_SET({$svip},svip)")->field('id,title,username,bankname,cardnum,qrcode,mincoin,maxcoin,isqrcode,memo,disrate,banktype,ifsc,url,groupid,usdtrate,screenshot,memo,discoin,fd_dcoin,fd_drate,f_dcoin,f_drate')->select()->toArray();
    }

    public function getDataListBySvip($svip, $groupid)
    {
//        $where = [['svip','like', '%' . $svip . ',%'], ['svip','like','%,' . $svip . '%']];
//        return $this->where('groupid', $groupid)->where('status', 1)->where(function($query) use ($where){$query->whereOr($where);})->field('id,title,username,disrate,cardnum,mincoin,maxcoin,isqrcode,memo,disrate,banktype,ifsc,url,groupid,usdtrate,screenshot')->select()->toArray();
        return $this->where('groupid', $groupid)->where('status', 1)->whereRaw("FIND_IN_SET({$svip},svip)")->field('id,title,username,disrate,cardnum,mincoin,maxcoin,isqrcode,memo,disrate,banktype,ifsc,url,groupid,usdtrate,screenshot')->select()->toArray();
    }
}