<?php
//文件上传封装
use think\facade\Config;
use think\facade\Filesystem;
use think\exception\ValidateException;

class Upload
{
    /**
     * 执行文件上传
     * @param  string $disks 磁盘名称
     * @param  string $field 字段名
     * @param  string $dir   文件存放目录名,默认和字段名相同
     * @return array         文件上传结果数组
     */
    public static function putFile(string $disks = '', string $field = '', string $dir = '')
    {
        // 此时也可能报错
        // 比如上传的文件过大,超出了配置文件中限制的大小
        try {
            $file = request()->file($field);
        } catch (\think\Exception $e) {
            return self::_rtnData(false, self::_languageChange($e->getMessage()));
        }

        // 确定使用的磁盘
        $disks = $disks ?: Filesystem::getDefaultDriver();
        // 文件存放目录名称
        $dirname  = $dir ?: $field;
        // 从存放目录开始的存放路径
        $savename = Filesystem::disk($disks)->putFile($dirname, $file);
        // 完整路径
        $path     = Filesystem::getDiskConfig($disks, 'url') . '/' . str_replace('\\', '/', $savename);

        // 返回上传成功时的数组
        return self::_rtnData(true, '上传成功', $path);
    }

    /**
     * 英文转为中文
     */
    private static function _languageChange($msg)
    {
        $data = [
            // 上传错误信息
            'unknown upload error'                       => '未知上传错误！',
            'file write error'                           => '文件写入失败！',
            'upload temp dir not found'                  => '找不到临时文件夹！',
            'no file to uploaded'                        => '没有文件被上传！',
            'only the portion of file is uploaded'       => '文件只有部分被上传！',
            'upload File size exceeds the maximum value' => '上传文件大小超过了最大值！',
            'upload write error'                         => '文件上传保存错误！',
        ];

        return $data[$msg] ?? $msg;
    }

    /**
     * 文件上传返回结果数组
     */
    private static function _rtnData(bool $result, $msg = null, $path = null)
    {
        // 过滤掉值为null的数组元素
        return array_filter(compact('result', 'msg', 'path'), function($v){
            return !is_null($v);
        });
    }
}

//// 返回数组
//Upload::putFile('磁盘', '文件字段域', '目录名');
//
//Upload::putFile('public', 'img');
//Upload::putFile('public', 'img', 'thumb');