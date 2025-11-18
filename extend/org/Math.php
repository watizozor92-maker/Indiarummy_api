<?php

namespace org;

/**
 * PHP精确算法
 */
class Math
{
    /**
     * 精确加法
     */
    public function add($a, $b, $scale = 0)
    {
        return bcadd($a, $b, $scale);
    }

    /**
     * 精确减法
     */
    public function sub($a, $b, $scale = 0)
    {
        return bcsub($a, $b, $scale);
    }

    /**
     * 比较大小
     * @param [type] $a
     * @param [type] $b
     * @param integer $scale 比较到小数点位数
     */
    public function math_comp($a, $b, $scale = 0)
    {
        // $a > $b   1
        // $a = $b   0
        // $a < $b  -1
        return bccomp($a, $b, $scale);
    }
}