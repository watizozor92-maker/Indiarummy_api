<?php
namespace app\controller;

use app\BaseController;

class Doc extends BaseController
{
    public function api() 
    {
        $filePath = '../doc/GameApi.html';
        // 检查文件是否存在
        if (file_exists($filePath)) 
        {
            // 读取并输出文件内容
            $contents = file_get_contents($filePath);
            return $contents;
        } 
        else
        {
            
            return '页面不存在';
        }
    }
}