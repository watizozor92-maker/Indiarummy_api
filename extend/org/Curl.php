<?php

namespace org;


use think\Exception;
use think\facade\Env;

class Curl
{
    protected $_game_server_host;
    protected $_game_server_port;
    protected $_timefl;
    protected $response = '';       // Contains the cURL response for debug
    protected $session;             // Contains the cURL handler for a session
    protected $url;                 // URL of the session
    protected $options = [];   // Populates curl_setopt_array
    protected $headers = [];   // Populates extra HTTP headers
    public $error_code;             // Error code returned as an int
    public $error_string;           // Error message returned as a string
    public $info;                   // Returned after request (elapsed time, etc)
    public $timenow;
    private $flag = 0;
    protected $rediscli_model;
    function __construct()
    {
        $this->_timefl = time();
        //log_message('debug', 'cURL Class Initialized');
        if (! $this->is_enabled()) {
            //log_message('error', 'cURL Class - PHP was not built with cURL enabled. Rebuild PHP with --with-curl to use cURL.');
        }
        $this->_game_server_host = Env::get('gameserver_host');
        $this->_game_server_port = Env::get('gameserver_port');
        //$url AND $this->create($url);
        $redis = new \Redis();
        $redis->connect(Env::get('redis.hostname'),Env::get('redis.port'));
        $redis->auth(Env::get('redis.password'));
        $redis->select(Env::get('redis.select'));
        $this->rediscli_model = $redis;
        $this->timenow = time();
    }

    public function setFlag($flag = 1)
    {
        $this->flag = $flag;
    }

    private function setPort()
    {
        $this->_game_server_port = Env::get('gameserver_port');
    }

    public function __call($method, $arguments)
    {
        if (in_array($method, ['simple_get', 'simple_post', 'simple_put', 'simple_delete', 'simple_patch'])) {
            // Take off the "simple_" and past get/post/put/delete/patch to _simple_call
            $verb = str_replace('simple_', '', $method);
            array_unshift($arguments, $verb);
            return call_user_func_array([$this, '_simple_call'], $arguments);
        }
    }

    /* =================================================================================
     * SIMPLE METHODS
     * Using these methods you can make a quick and easy cURL call with one line.
     * ================================================================================= */
    public function _simple_call($method, $url, $params = [], $options = [])
    {
        // Get acts differently, as it doesnt accept parameters in the same way
        if ($method === 'get') {
            // If a URL is provided, create new session
            $url = strstr($url, '?') ? $url.($params ? '&' . http_build_query($params, null, '&') : '') : $url.($params ? '?' . http_build_query($params, null, '&') : '');
            $this->create($url);
        } else {
            // If a URL is provided, create new session
            $this->create($url);
            $this->{$method}($params);
        }
        // Add in the specific options provided
        $this->options($options);
        return $this->execute();
    }

    public function simple_ftp_get($url, $file_path, $username = '', $password = '')
    {
        // If there is no ftp:// or any protocol entered, add ftp://
        if ( ! preg_match('!^(ftp|sftp)://! i', $url)) {
            $url = 'ftp://' . $url;
        }
        // Use an FTP login
        if ($username != '') {
            $auth_string = $username;
            if ($password != '') {
                $auth_string .= ':' . $password;
            }
            // Add the user auth string after the protocol
            $url = str_replace('://', '://' . $auth_string . '@', $url);
        }
        // Add the filepath
        $url .= $file_path;
        $this->option(CURLOPT_BINARYTRANSFER, true);
        $this->option(CURLOPT_VERBOSE, true);
        return $this->execute();
    }

    /* =================================================================================
     * ADVANCED METHODS
     * Use these methods to build up more complex queries
     * ================================================================================= */
    public function post($params = [], $options = [])
    {
        // If its an array (instead of a query string) then format it correctly
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        // Add in the specific options provided
        $this->options($options);
        $this->http_method('post');
        $this->option(CURLOPT_POST, true);
        $this->option(CURLOPT_POSTFIELDS, $params);
    }

    public function put($params = [], $options = [])
    {
        // If its an array (instead of a query string) then format it correctly
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        // Add in the specific options provided
        $this->options($options);
        $this->http_method('put');
        $this->option(CURLOPT_POSTFIELDS, $params);
        // Override method, I think this overrides $_POST with PUT data but... we'll see eh?
        $this->option(CURLOPT_HTTPHEADER, ['X-HTTP-Method-Override: PUT']);
    }

    public function patch($params = [], $options = [])
    {
        // If its an array (instead of a query string) then format it correctly
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        // Add in the specific options provided
        $this->options($options);
        $this->http_method('patch');
        $this->option(CURLOPT_POSTFIELDS, $params);
        // Override method, I think this overrides $_POST with PATCH data but... we'll see eh?
        $this->option(CURLOPT_HTTPHEADER, ['X-HTTP-Method-Override: PATCH']);
    }

    public function delete($params, $options = [])
    {
        // If its an array (instead of a query string) then format it correctly
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        // Add in the specific options provided
        $this->options($options);
        $this->http_method('delete');
        $this->option(CURLOPT_POSTFIELDS, $params);
    }

    public function set_cookies($params = [])
    {
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        $this->option(CURLOPT_COOKIE, $params);
        return $this;
    }

    public function http_header($header, $content = null)
    {
        $this->headers[] = $content ? $header . ': ' . $content : $header;
        return $this;
    }

    public function http_method($method)
    {
        $this->options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
        return $this;
    }

    public function http_login($username = '', $password = '', $type = 'any')
    {
        $this->option(CURLOPT_HTTPAUTH, constant('CURLAUTH_' . strtoupper($type)));
        $this->option(CURLOPT_USERPWD, $username . ':' . $password);
        return $this;
    }

    public function proxy($url = '', $port = 80)
    {
        $this->option(CURLOPT_HTTPPROXYTUNNEL, true);
        $this->option(CURLOPT_PROXY, $url . ':' . $port);
        return $this;
    }

    public function proxy_login($username = '', $password = '')
    {
        $this->option(CURLOPT_PROXYUSERPWD, $username . ':' . $password);
        return $this;
    }

    public function ssl($verify_peer = true, $verify_host = 2, $path_to_cert = null)
    {
        if ($verify_peer) {
            $this->option(CURLOPT_SSL_VERIFYPEER, true);
            $this->option(CURLOPT_SSL_VERIFYHOST, $verify_host);
            if (isset($path_to_cert)) {
                $path_to_cert = realpath($path_to_cert);
                $this->option(CURLOPT_CAINFO, $path_to_cert);
            }
        } else {
            $this->option(CURLOPT_SSL_VERIFYPEER, false);
            $this->option(CURLOPT_SSL_VERIFYHOST, $verify_host);
        }
        return $this;
    }

    public function options($options = [])
    {
        // Merge options in with the rest - done as array_merge() does not overwrite numeric keys
        foreach ($options as $option_code => $option_value) {
            $this->option($option_code, $option_value);
        }
        // Set all options provided
        curl_setopt_array($this->session, $this->options);
        return $this;
    }

    public function option($code, $value, $prefix = 'opt')
    {
        if (is_string($code) && !is_numeric($code)) {
            $code = constant('CURL' . strtoupper($prefix) . '_' . strtoupper($code));
        }
        $this->options[$code] = $value;
        return $this;
    }

    // Start a session from a URL
    public function create($url)
    {
        // If no a protocol in URL, assume its a CI link
        if ( ! preg_match('!^\w+://! i', $url)) {
            //$this->_ci->load->helper('url');
            //$url = site_url($url);
        }
        $this->url = $url;
        $this->session = curl_init($this->url);
        return $this;
    }

    // End a session and return the results
    public function execute()
    {
        // Set two default options, and merge any extra ones in
        if ( ! isset($this->options[CURLOPT_TIMEOUT])) {
            $this->options[CURLOPT_TIMEOUT] = 30;
        }
        if ( ! isset($this->options[CURLOPT_RETURNTRANSFER])) {
            $this->options[CURLOPT_RETURNTRANSFER] = true;
        }
        if ( ! isset($this->options[CURLOPT_FAILONERROR])) {
            $this->options[CURLOPT_FAILONERROR] = true;
        }

//        log_message('debug', $this->_timefl.": " . $this->url);
//        log_message('debug', $this->_timefl.": METHOD " . (isset($this->options[CURLOPT_CUSTOMREQUEST]) ? $this->options[CURLOPT_CUSTOMREQUEST] : "get"));
//        log_message('debug', $this->_timefl.": DATA " . (isset($this->options[CURLOPT_POSTFIELDS]) ? $this->options[CURLOPT_POSTFIELDS] : "NO POSTFIELDS"));


        // Only set follow location if not running securely
        if ( ! ini_get('safe_mode') && ! ini_get('open_basedir')) {
            // Ok, follow location is not set already so lets set it to true
            if ( ! isset($this->options[CURLOPT_FOLLOWLOCATION])) {
                $this->options[CURLOPT_FOLLOWLOCATION] = true;
            }
        }
        if ( ! empty($this->headers)) {
            $this->option(CURLOPT_HTTPHEADER, $this->headers);
        }
        $this->options();
        // Execute the request & and hide all output
        $this->response = curl_exec($this->session);
        $this->info = curl_getinfo($this->session);

        if (strpos($this->url, 'getgamelist') === false && strpos($this->url, 'serverinfo')===false) {
            $_httplogstr = "[url] {$this->url} [port] {$this->_game_server_port}";
            $_httplogstr.= isset($this->options[CURLOPT_POSTFIELDS]) ? " [_post] ".$this->options[CURLOPT_POSTFIELDS] : "";
            $_httplogstr.= " [_response] {$this->response}";
        }
//        log_message('debug', $this->_timefl.": return ". $this->response);

        // Request failed
        if ($this->response === false) {
            $errno = curl_errno($this->session);
            $error = curl_error($this->session);
            curl_close($this->session);
            $this->set_defaults();
            $this->error_code = $errno;
            $this->error_string = $error;
            return false;
        }
        // Request successful
        else {
            curl_close($this->session);
            $this->last_response = $this->response;
            $this->set_defaults();
            return $this->last_response;
        }
    }

    public function is_enabled()
    {
        return function_exists('curl_init');
    }

    public function debug()
    {
        echo "=============================================<br/>\n";
        echo "<h2>CURL Test</h2>\n";
        echo "=============================================<br/>\n";
        echo "<h3>Response</h3>\n";
        echo "<code>" . nl2br(htmlentities($this->last_response)) . "</code><br/>\n\n";
        if ($this->error_string)
        {
            echo "=============================================<br/>\n";
            echo "<h3>Errors</h3>";
            echo "<strong>Code:</strong> " . $this->error_code . "<br/>\n";
            echo "<strong>Message:</strong> " . $this->error_string . "<br/>\n";
        }
        echo "=============================================<br/>\n";
        echo "<h3>Info</h3>";
        echo "<pre>";
        print_r($this->info);
        echo "</pre>";
    }

    public function debug_request()
    {
        return [
            'url' => $this->url
        ];
    }

    public function set_defaults()
    {
        $this->response = '';
        $this->headers = [];
        $this->options = [];
        $this->error_code = null;
        $this->error_string = '';
        $this->session = null;
    }

    public function getErrMsg()
    {
        return $this->error_string;
    }

    //向游戏服，获取配置文件
    public function getConf($cat) {
        $this->setPort();
        $this->timenow = time();
        $result = $this->simple_get($this->_game_server_host.'?mod=config&act='.$cat, "", ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        $data = json_decode($result, true);
        return $data;
    }

    /**
     * 向游戏服务推送查询玩家是否在线
     * 指定玩家是否在线
     * @param mixed $uid       单个uid，或者uid数组
     * @return number|boolean[]
     */
    public function getOnlinePlayerOne($uid = null)
    {
        $this->setPort();
        $this->timenow = time();

        if(!! ($uido = $uid) && is_array($uid)) $uid = implode(",", $uid);

        if(
            ! ($data['uid'] = $uid)
            || ! ($data['mod'] = 'user')
            || ! ($data['act'] = 'online'))
        {
            //$this->error_string = "CURL参数缺失";
            return -1;
        }
        else
        {
            $_result = $this->simple_get($this->_game_server_host, $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);

            if(is_array($uido))
            {
                $_r = [];
                $_r_uid = $_result ? explode(",", $_result) : [];

                foreach ($uido as $_uid) {
                    if($_result && in_array($_uid, $_r_uid))
                    {
                        $_r[$_uid] = true;
                    }
                    else
                    {
                        $_r[$_uid] = false;
                    }
                }

                return $_r;
            }
            else
            {
                return $_result ? 1 : 0;
            }
        }

        return -1;
    }

    /**
     * 向游戏服务推送询问系统当前在线总数
     * @return number
     */
    public function getStatServerOnlineTotal()
    {
        $this->setPort();
        $this->timenow = time();

        if(! ($data['mod'] = 'serverinfo'))
        {
            $this->error_string = "CURL参数缺失";
            return -1;
        }
        else
        {
            $_result = $this->simple_get($this->_game_server_host, $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);

            if(strpos($_result, "\"onlinenum\"") && !! ($_r = Helper::is_json_str($_result)))
            {
                return $_r['onlinenum'];
            }
            else
            {
                return -1;
            }

        }

        return -1;
    }

    /**
     * 从游戏服务器获取游戏列表
     * 游戏概率配置
     * @return boolean|number
     */
    public function getProb($gameId, $type)
    {
        $this->setPort();
        $this->timenow = time();

        $result = json_decode($this->simple_get($this->_game_server_host.'?mod=getgamerateconf&gameid=' . $gameId . '&type=' . $type, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]), true);

        if (isset($result['data']) && $result['data'])
        {
            return $result['data'];
        }

        return false;
    }

    /**
     * 修改概率配置
     * 游戏概率配置
     * @return boolean
     */
    public function pushGameProb($gameId, $type, $prob)
    {
        $this->setPort();
        $this->timenow = time();

        $result = $this->simple_post($this->_game_server_host.'?mod=setgamerateconf&gameids=' . $gameId . '&type=' . $type, $prob, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);

        if ($result == 'succ')
        {
            return true;
        }

        return false;
    }

    /**
     * 向游戏服务器推送数据
     * 修改游戏状态
     * @return boolean
     */
    public function pushSystemSettingGameStatus($gameId)
    {
        $this->setPort();
        $this->timenow = time();

        $result = json_decode($this->simple_post($this->_game_server_host.'?mod=setgamestatus&gameid=' . $gameId, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]), true);
        if (isset($result['id']) && $result['id']) {

            $this->rediscli_model->del('server_gamelist');
            return $result['id'];
        }
        return false;
    }

    public function pushGameTag($gameids, $tag)
    {
        $this->setPort();
        $this->timenow = time();

        $result = $this->simple_get($this->_game_server_host.'?mod=setgametag&gameids=' . $gameids . '&tag=' . $tag, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($result == 'succ')
        {
            $this->rediscli_model->del('server_gamelist');
            return true;
        }

        return false;
    }

    public function putGameSort($gameid, $type, $ord)
    {
        $this->setPort();
        $this->timenow = time();

        $result = $this->simple_get($this->_game_server_host.'?mod=setgameord&gameid=' . $gameid . '&ord=' . $ord . '&type=' . $type, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($result == 'succ')
        {
            return true;
        }

        return false;
    }

    public function putGameLevel($gameid, $level, $type=2)
    {
        $this->setPort();
        $this->timenow = time();

        $result = $this->simple_get($this->_game_server_host.'?mod=setgamelevel&gameids=' . $gameid . '&level=' . $level . '&type=' . $type, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($result == 'succ')
        {
            return true;
        }

        return false;
    }

    //加载策略
    public function reloadStrategy($act, $id, $gameid)
    {
        $this->setPort();
        $this->timenow = time();
        $result = $this->simple_get($this->_game_server_host.'?mod=strategy&act='.$act.'&id=' . $id.'&gameid='.$gameid, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($result == 'succ') {
            return true;
        }
        return false;
    }


    public function reloadGame($gameid)
    {
        $this->setPort();
        $this->timenow = time();
        $result = $this->simple_get($this->_game_server_host.'?mod=game&act=reload&gameid=' . $gameid, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($result == 'succ') {
            $this->rediscli_model->del('server_gamelist');
            return true;
        }
        return false;
    }

    function __destruct()
    {
        if ($this->flag) {
            echo "<pre>";
            print_r("释放curl对象 ".time());
            echo "</pre>".PHP_EOL;
        }
    }

    /**
     * 获取多人游戏设置
     * @return boolean|number
     */
    public function getMultgamesetting($uid, $gameId)
    {
        $this->setPort();
        $this->timenow = time();

        if(
            ! ($_result = $this->simple_get($this->_game_server_host.'?mod=getmultgamesetting&uid='.$uid.'&gameid='.$gameId, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5])))
        {
            $this->error_string = "游戏服无响应";
            return false;
        }
        else
        {
            return json_decode($_result, true);
        }

        return false;
    }

    /**
     * 修改多人游戏设置
     * @return boolean|number
     */
    public function setMultgamesetting($uid, $gameId, $setting)
    {
        $this->setPort();
        $this->timenow = time();

        if(
            ! ($_result = $this->simple_get($this->_game_server_host.'?mod=setmultgamesetting&uid='.$uid.'&gameid='.$gameId.'&setting='.$setting, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]))
            || (string)$_result !== 'succ')
        {
            $this->error_string = "游戏服无响应";
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * 获取多人游戏
     * @return boolean|number
     */
    public function getMultgame()
    {
        $this->setPort();
        $this->timenow = time();

        if(
            ! ($_result = $this->simple_get($this->_game_server_host.'?mod=getmultgame', [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5])))
        {
            $this->error_string = "游戏服无响应";
            return false;
        }
        else
        {
            return json_decode($_result, true);
        }

        return false;
    }

    public function sendSlotsWin(string $uid = '0', string $game_id = '0', string $iswin = '0') : void
    {
        $this->setPort();
        $this->timenow = time();

        $this->simple_get($this->_game_server_host . '?mod=slotstimes&act=add&uid=' . $uid . '&gameid=' . $game_id . '&iswin=' . $iswin, [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
    }

    /**
     * 通知游戏设置变化
     * @return boolean|number
     */
    public function notifyGameSetting($uids, $gameId, $setting, $type)
    {
        $this->setPort();
        $this->timenow = time();

        $url = $this->_game_server_host.'?mod=notifygamesetting&gameid=' . $gameId . '&setting=' . $setting . '&type=' . $type;

        $result = $this->simple_post($url, ['uids'=> $uids], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);

        if ((string)$result !== 'succ') {
            $this->error_string = "游戏服无响应";
            return false;
        }
        return true;
    }

    public function sendMail($data){
        if ($mail_host_url = config('app.mail_host_url')){

        }else{
            throw new Exception('没有配置邮件地址');
        }
        $url = $mail_host_url.'?'.http_build_query($data);
        return $this->simple_get($url,[]);
    }

    /****
     * 获取在线用户数据
     */
    public function getOnlineUser(){
        $data = [];
        $data['mod'] = 'serverinfo';
        $data['timenow'] = time();
        $_result = $this->simple_get($this->_game_server_host,$data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if(strpos($_result, "\"onlinenum\"") && ($_r = json_decode($_result,true)))
        {
            return $_r['onlinenum'];
        }
        else
        {
            return -1;
        }
    }

    /***
     * 重置用户名信息
     * @param $data
     * @return int
     */
    public function resetName($data){
        $_result = $this->simple_get($this->_game_server_host,$data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if($_result == 'succ')
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function getOnlineData(){
        $data = [];
        $data['mod'] = 'user';
        $data['act'] = 'info';
        $_result = $this->simple_get($this->_game_server_host,$data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if(strpos($_result, "\"platform\"") && ($_r = json_decode($_result,true)))
        {
            return $_r;
        }
        else
        {
            return false;
        }
    }

    public function requestSetting($pars,$redisObj){
        $_result = $this->simple_post($this->_game_server_host.'?mod=gameswitch', ['msg'=> json_encode($pars, JSON_UNESCAPED_UNICODE)], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        $redisObj->set(RedisKey::SYSTEM_SETTING,json_encode($pars));
        if ($_result !== 'succ'){
            return false;
        }else{
            $redis = $redisObj->set('maintain_server', !$pars['game_switch'] ? 1 : 0);
        }
        return true;
    }

    public function reloadConfig(){
        $_result = $this->simple_post($this->_game_server_host.'?mod=config&act=reload', '', ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if (($_r = json_decode($_result,true))&&$_r['code'] ==200) {
            return true;
        }
        return false;

    }

    /***
     * 踢玩家下线
     * @param $uid
     * @return bool
     */
    public function player_offline($uid){
        $_result = $this->simple_post($this->_game_server_host.'?mod=user&act=offline', compact('uid'), ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if (($_r = json_decode($_result,true))&&$_r['code'] ==200) {
            return true;
        }
        return false;

    }

    public function suspendAgent($uid) {
        $_result = $this->simple_post($this->_game_server_host.'?mod=user&act=suspendagent', compact('uid'), ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if (($_r = json_decode($_result,true))&&$_r['code'] ==200) {
            return true;
        }
        return false;
    }

    //虚假举报 或 举报警告
    public function reportBack($uid, $type){
        $url = $this->_game_server_host.'?mod=report&act=send2Reporter&uid='.$uid;
        if($type == 2) {
            $url = $this->_game_server_host.'?mod=report&act=send2Other&uid='.$uid;
        }
        $_result = $this->simple_get($url, compact('uid'), ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if (($_r = json_decode($_result,true))&&$_r['code'] ==200) {
            return true;
        }
        return false;

    }

    public function setSwitch($uid, $rtype, $switch) {
        $_result = $this->simple_get($this->_game_server_host."?mod=user&act=switch&uid={$uid}&rtype={$rtype}&switch={$switch}", [], ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if (($_r = json_decode($_result,true))&&$_r['code'] ==200) {
            return true;
        }
        return false;
    }

    public function banUser($data,$banby_id = 0){
        if ($banby_id){
        }
    }

    //游戏服重新加载用户数据
    public function reloadUserInfo($data = []){
        $_result = $this->simple_get($this->_game_server_host.'?mod=user&act=update&uid='.$data['uid'], $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ((string)$_result === 'succ'){
            return true;
        }
        return false;
    }

    public function reloadUserVipInfo($data = []){
        $_result = $this->simple_get($this->_game_server_host.'?mod=user&act=updatevip&uid='.$data['uid'], $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ((string)$_result === 'succ'){
            return true;
        }
        return false;
    }

    /***
     *
     * @param $data
     * @return bool
     */
    public function reloadsess($data = []){
        $_result = $this->simple_get($this->_game_server_host.'?mod=control&act=reloadsess', $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ((string)$_result === 'succ'){
            return true;
        }
        return false;
    }

    /***
     * 加复活卡
     * @param $data
     * @return bool
     */
    public function resurrectionAdd($data, $type){
        $_result = $this->simple_get($this->_game_server_host.'?mod=user&act=addprop&type=' . $type, $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($_result  == 'succ'){
            return true;
        }
        return false;
    }

    public function bindinfo($uid, $key){
        $_result = $this->simple_get($this->_game_server_host.'?mod=user&act=bindinfo&field=' . $key.'&uid='.$uid, '', ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($_result  == 'succ'){
            return true;
        }
        return false;
    }

    /***
     * @return bool
     */
    public function reloadNickName($data =[]){
        $_result = $this->simple_get($this->_game_server_host.'?mod=nickname&act=reload', $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($_result  == 'succ'){
            return true;
        }
        return false;
    }

    /***
     * 热更新等级配置
     * @param $data
     * @return bool
     */
    public function reloadLevel($data =[]){
        $_result = $this->simple_get($this->_game_server_host.'?mod=level&act=reload', $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($_result  == 'succ'){
            return true;
        }
        return false;

    }

    /***
     *4、配置列表
     * @param array $data
     * @return bool
     */
    public function reloadCondfig($data =[]){
        $_result = $this->simple_get($this->_game_server_host.'?mod=config&act=reload', $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($_result  == 'succ'){
            return true;
        }
        return false;
    }

    public function addHideRank($data){
        $_result = $this->simple_get($this->_game_server_host.'?mod=user&act=hiderank&uid='.$data['uid'], $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        $_result = json_decode($_result,true);
        if ($_result && $_result['code'] == 200){
            return true;
        }
        return false;
    }

    /***
     * 系统公告
     */
    public function system_notice($query, $data){
        $_result = $this->simple_post($this->_game_server_host.'?mod=pushnotice&' . $query, $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 5]);
        if ($_result  == 'succ'){
            return true;
        }
        return false;
    }

    //渠道登录
    public function channel_login($data)
    {
        $_result = $this->simple_post($this->_game_server_host.'?mod=channellogin', $data, ['PORT'=> $this->_game_server_port, 'TIMEOUT'=> 120]);        
        return $_result;
    }
}