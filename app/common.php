<?php
// 应用公共文件
//get
if (!function_exists('curlGet')) {
    function curlGet($url,$type=1) {
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_TIMEOUT,5000);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($curl,CURLOPT_USERAGENT,'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P)');
        curl_setopt($curl,CURLOPT_URL,$url);
        if($type == 1){
            curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        }
        $res = curl_exec($curl);
        if($res){
            curl_close($curl);
            return $res;
        }else {
            $error = curl_errno($curl);
            curl_close($curl);
            return $error;
        }
    }
}

//post
if(!function_exists('curlPost')) {
    function curlPost($url, $post_data = array(), $timeout = 5, $header = "", $data_type = "") {
        $header = empty($header) ? '' : $header;
        //支持json数据数据提交
        if($data_type == 'json'){
            $post_string = json_encode($post_data);
        }elseif($data_type == 'array') {
            $post_string = $post_data;
        }elseif(is_array($post_data)){
            $post_string = http_build_query($post_data, '', '&');
        }
        $ch = curl_init();    // 启动一个CURL会话
        curl_setopt($ch, CURLOPT_URL, $url);     // 要访问的地址

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 对认证证书来源的检查   // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
        //curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        //curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P)'); // 模拟用户使用的浏览器
        curl_setopt($ch, CURLOPT_POST, true); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);     // Post提交的数据包
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);     // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        //curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);     // 获取的信息以文件流的形式返回
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        $result = curl_exec($ch);

        // 打印请求的header信息
        writelog($url, 'curlPost');
        writelog($post_string, 'curlPost');
        $a = curl_getinfo($ch);
        $err =curl_error($ch);
        if($a) {
            writelog('curlinfo:'.json_encode($a), 'curlPost');
        }
        if($err) {
            writelog('curlerr:'.json_encode($err), 'curlPost');
        }
        writelog('curlresult:'.$result, 'curlPost');

//    var_dump($err);
//
//    var_dump($a);

        curl_close($ch);
        return $result;
    }
}

//postjson
if(!function_exists('curlPostJosn')) {
    function curlPostJosn($url, $data, $timeout=30) {
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P)');
        $result = curl_exec($ch);
        return $result;
    }
}

//获取毫秒数
if(!function_exists('msectime')) {
    function msectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
}

//writelog
if(!function_exists('writelog')) {
    function writelog($msg, $filename="spider_err") {
        $content = date('Y-m-d H:i:s') ;
        if(is_array($msg)) {
            $content .= " " . http_build_query($msg);
        } else {
            $content .= ' ' . $msg;
        }
        file_put_contents("/tmp/{$filename}_".date('Ymd').".log", $content . "\r\n", FILE_APPEND);
        return;
    }
}

//getMillisecond
if(!function_exists('getMillisecond')) {
    function getMillisecond() {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }
}

//getCanonicalStringForIsv
if(!function_exists('getCanonicalStringForIsv')) {
    function getCanonicalStringForIsv($timestamp, $secret_key) {
        $result = $timestamp;
        $result .= "\n".$secret_key;
        return $result;
    }
}

//makeOrderId
if(!function_exists('makeOrderId')) {
    function makeOrderId($cat='payonline', $length = 8 )
    {
        $catstr = '01';
        if($cat == 'payoffline') {
            $catstr = '02';
        }elseif($cat =='draw') {
            $catstr = '03';
        }

        return date('YmdHis') . $catstr .substr(implode(null, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, $length);
    }
}

//make_password
if(!function_exists('make_password')) {
    function make_password( $length = 10 )
    {
        // 密码字符集，可任意添加你需要的字符
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l','m', 'n', 'o', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y','z', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L','M', 'N', 'O',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y','Z',
            '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        // 在 $chars 中随机取 $length 个数组元素键名
        $keys = array_rand($chars, $length);
        $password = '';
        for($i = 0; $i < $length; $i++)
        {
            // 将 $length 个数组元素连接成字符串
            $password .= $chars[$keys[$i]];
        }
        return $password;
    }
}

//已某个字符串开头
if(!function_exists('startwith')) {
    function startwith($str,$pattern) {
        return (strpos($str,$pattern)) === 0 ? true:false;
    }
}