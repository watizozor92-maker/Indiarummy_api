<?php
namespace app\controller;
use app\BaseController;
use org\Curl;
use org\AESCrypto;
use org\Util;
use app\model\DChannelParter;
use app\model\DUser;
use app\model\DUserBind;
use app\model\DDeskUser;

use think\facade\Request;
use think\facade\Log;
use think\facade\Env;
use think\helper\Str;
use think\helper\Random;
use think\facade\Db;

class Game extends BaseController
{
    private $redis;    
    private $ip = '';
    private $common_key = 'game_api_service_common_key_2024';
    private $GAME_URL = 'https://xyule.cyou/';

    const ERROR_CODE_SUCCESS                    = 0;
    const ERROR_CODE_INVALID_PARAMS             = 1;
    const ERROR_CODE_INVALID_TOKEN              = 2;
    const ERROR_CODE_INVALID_CHANNEL_INFO       = 11;
    const ERROR_CODE_INVALID_USER_INFO          = 12;
    const ERROR_CODE_AGENT_NOT_EXIST            = 13;
    const ERROR_CODE_AGENT_ALREADY_EXIST        = 14;    
    const ERROR_CODE_USER_ALREADY_EXIST         = 15;
    const ERROR_CODE_CREATE_CHANNEL_FAILED      = 16;
    const ERROR_CODE_CREATE_USER_FAILED         = 17;
    const ERROR_CODE_REQUST_SERVICE_ERROR       = 18;
    const ERROR_CODE_REQUST_SERVICE_NO_DATA     = 19;
    const ERROR_CODE_LAUNCH_GAME_FAILED         = 20;
    const ERROR_CODE_INVALID_PAYLOAD            = 21;

    const EXPIRE_TIME                           = 600;
    const EXPIRE_TIME_USER_DATA                 = 3600;

    public function initialize()
    {
        // parent::initialize();
        $this->redis = $this->getRedisObj();
        $this->ip = Request::ip();
    }

    public function test()
    {
        // echo base64_encode(openssl_random_pseudo_bytes(32));
        http_response_code(500);
        echo $this->redis->get("23kjsdkfj") == null;
        exit(1);
    }
    //申请APIKey
    public function applykey()
    {
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");
        }
        else
        {
            $agency_uid = $this->request->get('agency_uid', "");
        }
        if(empty($agency_uid))
        {
            return $this->backErr('Invalid params ', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $channelstr = $this->getAgentApikey($agency_uid);
        if(!empty($channelstr))
        {
            return $this->backErr('the agent code is exist', Game::ERROR_CODE_AGENT_ALREADY_EXIST, true);
        }
        // 32字节的密钥
        $key = base64_encode(openssl_random_pseudo_bytes(32));
        Db::startTrans();
        $ret = DChannelParter::create(
            [
                'agency_uid'=> $agency_uid,
                'apikey' => $key,
            ]
        );
        if(!$ret)
        {
            Db::rollback();
            return $this->backErr('Invalid params: there are not required parameters', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        //关联agent 账号-用于登录代理后台       
        $salt = rand(111111,999999);
        $params = array(
            'username' => $post_param['username'],
            'password' => md5(strtolower(trim($post_param['username'])).md5($password).$salt),
            'nickname' => $post_param['nickname'],
            'agentids' => trim($post_param['agent']),
            'phone' => $post_param['phone'],
            'remark' => $post_param['remark'],
            'salt'=>  $salt,
            'uid' => session('user_info.id'),
            'create_time' => time(),
            'status' => 1
        );
        $model = new \app\agent\model\AccountAgent();        
        for($i = 0; $i < 3; $i++) 
        {
            $account = $model->where(['username'=>$params['username']])->find();
            if(!$account)
            {
                break;
            }
            $params['username'] = $params['username'].rand(1,9);
        }
        
        if($account) 
        {
            return $this->backErr('agency name is exist.', Game::ERROR_CODE_AGENT_ALREADY_EXIST, true);
        }

        $re = $model->save($params);
        if($re)
        {
            Db::commit();
        }
        else
        {
            Db::rollback();
            return $this->backErr('Invalid params: there are not required parameters', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        // $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')); // 16字节的IV        
        return $this->backjson([
            'api_key' => $key
        ]);
    }

    public function v1()
    {
        Log::write('[v1]contentType:'. $this->request->contentType());
        Log::write('[v1]getContent:'. $this->request->getContent());
        if ($this->request->isPost() && $this->request->contentType() == 'application/json') {
            $content = $this->request->getContent();
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->backErr('Invalid params:the request data must be json format', Game::ERROR_CODE_INVALID_PARAMS, true);
                
            } 
            // 将JSON数据存储到POST参数中
            $this->request->post($data);
        }
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");            
            $timestamp = $this->request->post('timestamp', 0);            
            $payload = $this->request->post('payload', "");            
        }
        if(empty($agency_uid) || $timestamp  == 0  ||empty($payload))
        {
            return $this->backErr('Invalid params: there are not required parameters', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }
        //AES解密
        Log::write('payload:'. $payload);
        Log::write('apikey:'. $apikey);
        $payload = AESCrypto::decrypt_ecb($payload, $apikey);
        Log::write('decrypt payload:'. $payload);        
        $payloadArr = json_decode($payload, true);
        if(empty($payloadArr))
        {
            return $this->backErr('Invalid payload data ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['agency_uid']) || $payloadArr['agency_uid'] != $agency_uid)
        {
            return $this->backErr('Invalid payload agency_uid ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(strlen((string)$timestamp) >= 13)
        {
            $timestamp /= 1000;
        }
        //误差不能超过1天
        if(!isset($payloadArr['timestamp']) || abs($timestamp - time()) > 86400)
        {
            return $this->backErr('Invalid payload timestamp ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['account']))
        {
            return $this->backErr('Invalid payload account ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['game_id']))
        {
            return $this->backErr('Invalid payload game_id ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['credit_amount']))
        {
            return $this->backErr('Invalid payload credit_amount ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['currency_code']))
        {
            return $this->backErr('Invalid payload currency_code ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['language']))
        {
            return $this->backErr('Invalid payload language ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        return $this->launchv2(
            $payloadArr['agency_uid'],
            $apikey,
            $payloadArr['account'],
            isset($payloadArr['game_id']) ? $payloadArr['game_id'] : 0,
            $payloadArr['credit_amount'],
            $payloadArr['currency_code'],
            $payloadArr['language'],            
            isset($payloadArr['callback_url']) ? $payloadArr['callback_url']: ""           
        );
    }

    //启动游戏
    public function v2()
    {
        Log::write('[v1]contentType:'. $this->request->contentType());
        Log::write('[v1]getContent:'. $this->request->getContent());
        if ($this->request->isPost() && $this->request->contentType() == 'application/json') {
            $content = $this->request->getContent();
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->backErr('Invalid params:the request data must be json format', Game::ERROR_CODE_INVALID_PARAMS, true);
                
            } 
            // 将JSON数据存储到POST参数中
            $this->request->post($data);
        }
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");            
            $timestamp = $this->request->post('timestamp', 0);            
            $payload = $this->request->post('payload', "");            
        }
        if(empty($agency_uid) || $timestamp  == 0  ||empty($payload))
        {
            return $this->backErr('Invalid params: there are not required parameters', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }
        //AES解密
        Log::write('payload:'. $payload);
        Log::write('apikey:'. $apikey);
        $payload = AESCrypto::decrypt_ecb($payload, $apikey);
        Log::write('decrypt payload:'. $payload);        
        $payloadArr = json_decode($payload, true);
        if(empty($payloadArr))
        {
            return $this->backErr('Invalid payload data ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['agency_uid']) || $payloadArr['agency_uid'] != $agency_uid)
        {
            return $this->backErr('Invalid payload agency_uid ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(strlen((string)$timestamp) >= 13)
        {
            $timestamp /= 1000;
        }
        //误差不能超过1天
        if(!isset($payloadArr['timestamp']) || abs($timestamp - time()) > 86400)
        {
            return $this->backErr('Invalid payload timestamp ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['account']))
        {
            return $this->backErr('Invalid payload account ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['game_id']))
        {
            return $this->backErr('Invalid payload game_id ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['credit_amount']))
        {
            return $this->backErr('Invalid payload credit_amount ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['currency_code']))
        {
            return $this->backErr('Invalid payload currency_code ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['language']))
        {
            return $this->backErr('Invalid payload language ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        
    //    $clientInfo = [
    //         'user' => $payloadArr['username'],
    //         'client_uuid' => $payloadArr['clientuuid'],
    //         'passwd' => $payloadArr['passwd'],
    //         'bundleid' => 'com.yono.games.free.test',
    //         't' => 7, // 暂定7，token验证
    //         'phone' => 'api_web',
    //         'gameid' => (int)$payloadArr['game_code'],
    //         'token' => $payloadArr['token'],
    //         'bwss' => 0,
    //         'balance' => isset($payloadArr['user_balance']) ? (int)$payloadArr['user_balance'] : 0,
    //         'app' => 7, // 都暂定 7
    //         'callback_url' => $payloadArr['callback_url'],
    //         'currency_code' => $payloadArr['currency_code']
    //     ];

        return $this->launch(
            $payloadArr['agency_uid'],
            $apikey,
            $payloadArr['account'],
            isset($payloadArr['game_id']) ? $payloadArr['game_id'] : 0,
            $payloadArr['credit_amount'],
            $payloadArr['currency_code'],
            $payloadArr['language'],            
            isset($payloadArr['callback_url']) ? $payloadArr['callback_url']: ""           
        );
    }

    //token换连接信息
    public function session()
    {
        if($this->request->isPost())
        {
            $token = $this->request->post('token', '');
        }
        else
        {
            $token = $this->request->get('token', '');
        }
        if(empty($token))
        {
            return $this->backErr('Invalid params ', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $jsonstr = $this->redis->get("GAME_LAUNCH:".$token);
        if(empty($jsonstr))
        {
            return $this->backErr('Invalid token ', Game::ERROR_CODE_INVALID_TOKEN, true);
        }
        $jsondata = json_decode($jsonstr, true);
        $jsondata["conn_info"] = $jsondata["data"];
        unset($jsondata["data"]);
        return $this->backjson($jsondata);
    }

    public function genv1()
    {
        $user_code = $this->request->get('user', 'test_101');
        $data = [
            'agency_uid' => $this->request->get('agency', 'agency10000'),
            'timestamp' => Util::getTimestampMs(),
            'account' => $user_code,
            'game_id' => $this->request->get('gameid', 696),
            'credit_amount' => 100000,
            'currency_code' => '$',
            'language' => 'en',
            'callback_url' => $this->request->get('callback', 'https://xyule.cyou:7002/game/handleGameRecordtest'),
        ];
        $apikey = $this->getAgentApikey($data['agency_uid']);
        if(empty($apikey))
        {
            return $this->backErr('Invalid params', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }
        //AES解密
        $payload = AESCrypto::encrypt_ecb(json_encode($data), $apikey);
        header('Content-type:application/json');
        echo json_encode([
            'agency_uid' => $data['agency_uid'],
            'timestamp' => $data['timestamp'],
            'payload' => $payload,
        ]);
        // echo AESCrypto::decrypt_ecb($payload, $apikey);
        exit;
    }

    public function genCreateInfo()
    {
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");             
            $account = $this->request->post('account', "");            
        }
        else
        {
            $agency_uid = $this->request->get('agency_uid', "");           
            $account = $this->request->get('account', '');         
        }
        if(empty($account))
        {
            return $this->backErr('Invalid params', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }        
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }
        $data = [
            'agency_uid' => $agency_uid,
            'timestamp' => Util::getTimestampMs(),
            'account' => $account,
        ];
        //AES解密
        $payload = AESCrypto::encrypt_ecb(json_encode($data), $apikey);
        header('Content-type:application/json');
        echo json_encode([
            'agency_uid' => $data['agency_uid'],
            'timestamp' => $data['timestamp'],
            'payload' => $payload,
        ]);
        // echo AESCrypto::decrypt_ecb($payload, $apikey);
        exit;
    }

    //测试解密内容
    public function testdecrypt()
    {
        if ($this->request->isPost() && $this->request->contentType() == 'application/json') 
        {
            $content = $this->request->getContent();
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->backErr('Invalid params:the request data must be json format', Game::ERROR_CODE_INVALID_PARAMS, true);
                
            } 
            // 将JSON数据存储到POST参数中
            $this->request->post($data);
        }
        $agency_uid = $this->request->post('agency_uid', "");   
        $payload = $this->request->post('payload', "");   
        if(empty($agency_uid) || empty($payload))
        {
            return $this->backErr('Invalid params ', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }   
        echo AESCrypto::decrypt_ecb($payload, $apikey);
        exit;
    }

    public function handleGameRecordTest()
    {
        Log::write('handleGameRecordTest:'.$this->request->contentType()." ispost:".$this->request->isPost());
        if ($this->request->isPost() && $this->request->contentType() == 'application/json') 
        {
            $content = $this->request->getContent();
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->backErr('Invalid params:the request data must be json format', Game::ERROR_CODE_INVALID_PARAMS, true);
                
            } 
            // 将JSON数据存储到POST参数中
            $this->request->post($data);
        }
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");            
            $timestamp = $this->request->post('timestamp', 0);            
            $payload = $this->request->post('payload', "");            
        }
        if(empty($agency_uid) || $timestamp  == 0  ||empty($payload))
        {
            return $this->backErr('Invalid params: there are not required parameters', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }
        //AES解密
        Log::write('payload:'. $payload);
        Log::write('apikey:'. $apikey);
        $payload = AESCrypto::decrypt_ecb($payload, $apikey);
        Log::write('callbacktest sucess decrypt payload:'. $payload);       
        $credit_amount = rand(100, 100000); 
        $payload = ["credit_amount" => $credit_amount, "timestamp"=> Util::getTimestampMs()];
        Log::write('callbacktest response:'. json_encode($payload));   
        return $this->backJson(AESCrypto::encrypt_ecb(json_encode($payload), $apikey));
    }
    //创建用户 
    // agency_uid(代理商代码) 
    // agent_token(代理商密钥) 
    // user_code(玩家id)
    public function createUser()
    {
        if ($this->request->isPost() && $this->request->contentType() == 'application/json') 
        {
            $content = $this->request->getContent();
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) 
            {
                return $this->backErr('Invalid params:the request data must be json format', Game::ERROR_CODE_INVALID_PARAMS, true);                
            }
            // 将JSON数据存储到POST参数中
            $this->request->post($data);
        }
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");             
            $timestamp = $this->request->post('timestamp', 0);             
            $payload = $this->request->post('payload', "");            
        }
        if(empty($agency_uid) || $timestamp  == 0  ||empty($payload))
        {
            return $this->backErr('Invalid params ', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }        
        $payload = AESCrypto::decrypt_ecb($payload, $apikey);
        $payloadArr = json_decode($payload, true);
        if(empty($payloadArr))
        {
            return $this->backErr('Invalid payload data ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        // if(!isset($payloadArr['agency_uid']) || $payloadArr['agency_uid'] != $agency_uid)
        // {
        //     return $this->backErr('Invalid payload agency_uid ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        // }
        if(abs($timestamp/1000 - time()) > 86400)
        {
            return $this->backErr('Invalid payload timestamp ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['account']))
        {
            return $this->backErr('Invalid payload account ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        $result = $this->doCreateAccount($agency_uid, $apikey, $payloadArr['account']);
        header('Content-type:application/json');
        echo json_encode($result);
        exit;
    }
    
    //获取交易记录
    public function transaction()
    {
        if ($this->request->isPost() && $this->request->contentType() == 'application/json') 
        {
            $content = $this->request->getContent();
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) 
            {
                return $this->backErr('Invalid params:the request data must be json format', Game::ERROR_CODE_INVALID_PARAMS, true);                
            }
            // 将JSON数据存储到POST参数中
            $this->request->post($data);
        }
        if($this->request->isPost())
        {
            $agency_uid = $this->request->post('agency_uid', "");             
            $timestamp = $this->request->post('timestamp', 0);             
            $payload = $this->request->post('payload', "");      
        }
        if(empty($agency_uid) || $timestamp  == 0  ||empty($payload))
        {
            return $this->backErr('Invalid params ', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        $apikey = $this->getAgentApikey($agency_uid);
        if(empty($apikey))
        {
            return $this->backErr('Invalid channel info ', Game::ERROR_CODE_INVALID_CHANNEL_INFO, true);
        }        
        $payload = AESCrypto::decrypt_ecb($payload, $apikey);
        $payloadArr = json_decode($payload, true);
        if(empty($payloadArr))
        {
            return $this->backErr('Invalid payload data ', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(abs($timestamp/1000 - time()) > 86400)
        {
            return $this->backErr('Invalid payload timestamp', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['from_date']))
        {
            return $this->backErr('Invalid payload from_date', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['to_date']))
        {
            return $this->backErr('Invalid payload to_date', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        if(!isset($payloadArr['page_no']))
        {
            return $this->backErr('Invalid payload page_no', Game::ERROR_CODE_INVALID_PAYLOAD, true);
        }
        $startTime = date('Y-m-d H:i:s', strtotime($payloadArr['from_date']));
        $endTime = date('Y-m-d H:i:s', strtotime($payloadArr['to_date']));

        $agencyId = $this->getAgencyIdByAgUid($agency_uid);
        
        $pageNo = max((int)$payloadArr['page_no'], 1);
        $pageSize = isset($payloadArr['page_size'])? (int)$payloadArr['page_size']: 10;
        //查询历史记录
        $totalCount =  Db::view('d_desk_user', 'id,uid')
            ->view('d_user', 'uid,channel_agency', 'd_user.uid=d_desk_user.uid')
            ->view('d_channel_parter', 'id', 'd_channel_parter.id=d_user.channel_agency')
            ->whereBetweenTime('create_time', $startTime, $endTime)
            ->count();
        $retList = Db::view('d_desk_user', 'id,issue as serial_number,uid,gameid,bet as bet_amount ,wincoin as win_amount,currency_code,create_time as timestamp')
            ->view('d_user', 'uid,channel_agency,username as account', 'd_user.uid=d_desk_user.uid')
            ->view('d_channel_parter', 'id,agency_uid', 'd_channel_parter.id=d_user.channel_agency')
            ->whereBetweenTime('create_time', $startTime, $endTime)
            ->field('agency_uid,account,serial_number,gameid,bet_amount,win_amount,currency_code,timestamp')
            ->page($pageNo, $pageSize)
            ->select();
        $payload = [
            'total_count' => $totalCount,
            'current_page' => $pageNo,
            'page_size' => $pageSize,
            'records' => $retList,
        ];
        $this->backjson(AESCrypto::encrypt_ecb(json_encode($payload), $apikey));
    }

    //被游戏回调
    public function handleGameRecord()
    {
        if($this->request->isPost())
        {
            $uid = $this->request->post('uid', 0);             
            $gameid = $this->request->post('gameid', 0);            
            $betcoin = $this->request->post('betcoin', 0);            
            $wincoin = $this->request->post('wincoin', 0);   
            $issue = $this->request->post('issue', '');                        
            $round = $this->request->post('round', 0);                        
            $currency_code = $this->request->post('currency_code', '');                        
        }
        else
        {
            $uid = $this->request->get('uid', 0);             
            $gameid = $this->request->get('gameid', 0);            
            $betcoin = $this->request->get('betcoin', 0);            
            $wincoin = $this->request->get('wincoin', 0);   
            $issue = $this->request->get('issue', '');                        
            $round = $this->request->get('round', 0);                        
            $currency_code = $this->request->get('currency_code', '');                        
        }
        if($uid == 0)
        {
            // http_response_code(500);
            echo 'fail';
            exit;
        }
        $callback = $this->getUserCallback($uid);
        Log::write('[handleGameRecord]uid:'.$uid.'callback'.$callback);
        if(empty($callback))
        {
            // http_response_code(500);
            Log::write('[handleGameRecord]getUserCallback:'. $callback);
            echo 'success';
            exit;
        }    
        $username = $this->getUserBindAgency($uid);  
        if(!$username)
        {
            // http_response_code(500);
            Log::write('[handleGameRecord]username:'. $username);
            echo 'success';
            exit;
        }    
        $auid = $this->getAgencyIdByUid($uid);        
        if(!$auid)
        {
            // http_response_code(500);
            Log::write('[handleGameRecord]auid:'. $uid);
            echo 'success';
            exit;
        }   
        $agencyUId = $this->getAgencyUidById($auid);
        if(!$agencyUId)
        {
            // http_response_code(500);
            Log::write('[handleGameRecord]agencyUId:'.$auid);
            echo 'success';
            exit;
        }
        
        $apikey = $this->getAgentApikey($agencyUId);
        if(empty($apikey))
        {
            http_response_code(500);
            Log::write('[handleGameRecord]apikey:'. $agencyUId);
            echo 'success';
            exit;
        } 
        $timestamp = Util::getTimestampMs();
        $data = [
            'serial_number' => $issue,
            'currency_code' => $currency_code,
            'game_id' => $gameid,
            'account' => $username,
            'win_amount' => $wincoin,
            'bet_amount' => $betcoin,
            'timestamp' => $timestamp,
            'game_round' => $round,
        ];
        $payload = AESCrypto::encrypt_ecb(json_encode($data), $apikey);
        $back = curlPostJosn($callback, ['agency_uid'=>$agencyUId, 'timestamp' => $timestamp, 'payload' => $payload]);
        Log::write('[handleGameRecord]curlPostJosn:'. $back);
        $backArr = json_decode($back, true);
        if($backArr['code'] == '0' || $backArr['code'] == 0) 
        {
            if(isset($backArr['payload']))
            {
                $payload = AESCrypto::decrypt_ecb($backArr['payload'], $apikey);
                $payloadArr = json_decode($payload, true);
                Log::write('[handleGameRecord]curlPostJosn:'. $payload);
            }
            echo 'success';
            exit;
        }
        http_response_code(500);
        echo 'fail';
        exit;
    }

    
    ////////////////////////////////////////////////////////////////////////////////////////
    //内部功能函数
    /////////////////////////////////////////////////////////////////////////////////////////
    private function getAgentApikey($agency_uid)
    {        
        $channelstr = $this->redis->get('channel:apikey:'.$agency_uid);
        if(empty($channelstr))
        {
            $channelstr = DChannelParter::where('agency_uid', $agency_uid)->value('apikey');            
        }
        if(!empty($channelstr))
        {
            //#缓存代理token 1小时
            $this->redis->set('channel:apikey:'.$agency_uid, $channelstr, Game::EXPIRE_TIME_USER_DATA);
        }
        return $channelstr;
    }

    private function getUserToken($agency_uid, $user_code)
    {
        $username = $this->genUserName($agency_uid, $user_code);
        $channelstr = $this->redis->hget('channel:user:'.$agency_uid, $username);
        if(empty($channelstr))
        {
            $channelstr = DUser::where('username', $username)->value('token');            
        }
        if(!empty($channelstr))
        {
            //#缓存代理token 1小时
            $this->redis->hset('channel:user:'.$agency_uid, $username, $channelstr);
        }
        return $channelstr;
    }

    private function getUserUID($agency_uid, $user_code)
    {
        $username = $this->genUserName($agency_uid, $user_code);
        $channelstr = $this->redis->hget('channel:user:'.$agency_uid, 'uid:'.$username);
        if(empty($channelstr))
        {
            $channelstr = DUser::where('username', $username)->value('uid');            
        }
        if(!empty($channelstr))
        {
            $this->redis->hset('channel:user:'.$agency_uid, 'uid:'.$username, $channelstr);
        }
        return $channelstr;
    }

    private function getUserCallback($uid)
    {
        $valuestr = $this->redis->get("callback:".$uid);
        if($valuestr == null)
        {
            $valuestr = DUserBind::where('uid', $uid)->value('callback');            
        }
        if($valuestr != null)
        {
            //#缓存回调 1小时
            $this->redis->set("callback:".$uid, $valuestr, Game::EXPIRE_TIME_USER_DATA);
        }
        return $valuestr;
    }
    
    private function getUserBindAgency($uid)
    {
        $username = $this->redis->get("bind:agent:".$uid);
        if($username == null)
        {
            $username = DUserBind::where('uid', $uid)->value('nickname');         
        }
        if(!empty($username))
        {
            $this->redis->set("bind:agent:".$uid, $username, Game::EXPIRE_TIME_USER_DATA);
        }
        return $username;
    }

    private function getAgencyIdByUid($uid)
    {
        $agencyId = $this->redis->get("bind:agencyid:".$uid);
        if($agencyId == null)
        {
            $agencyId = DUser::where('uid', $uid)->value('channel_agency');
        }
        if($agencyId && !empty($agencyId))
        {
            $this->redis->set("bind:agencyid:".$uid, $agencyId, Game::EXPIRE_TIME_USER_DATA);
        }
        return $agencyId;
    }

    private function getAgencyUidById($id)
    {
        $agency_uid = $this->redis->get("agencyuid:".$id);
        if($agency_uid == null)
        {
            $agency_uid = DChannelParter::where('id', $id)->value('agency_uid');
        }
        if($agency_uid && !empty($agency_uid))
        {
            $this->redis->set("agencyuid:".$id, $agency_uid, Game::EXPIRE_TIME_USER_DATA);
        }
        return $agency_uid;
    }

    private function getAgencyIdByAgUid($Agency_uid)
    {
        $agency_id = $this->redis->get("agencyid:".$Agency_uid);
        if($agency_id == null)
        {
            $agency_id = DChannelParter::where('agency_uid', $Agency_uid)->value('id');
        }
        if($agency_id && !empty($agency_id))
        {
            $this->redis->set("agencyid:".$Agency_uid, $agency_id, Game::EXPIRE_TIME_USER_DATA);
        }
        return $agency_id;
    }

    private function genClientUUID($userfix)
    {
        return $userfix.'-'.Request::ip();
    }

    private function genUserName($agency_uid, $user_code)
    {
        return $agency_uid.'_'.$user_code;
    }

    //agency_uid(代理商代码) , 
    //agent_token(代理商密钥) , 
    //user_code(玩家id) , 
    //game_code (游戏代码) , 
    //user_balance(用户余额) , 
    //lang(语言)
    private function launch($agency_uid, $agent_token, $user_code, $game_id, $user_balance, $currency_code, $lang = 'en', $callback_url = '') 
    {        
        if(empty($agency_uid) || empty($agent_token))
        {
            return $this->backErr('Invalid params agency_uid or agent_token'.empty($agency_uid), Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        if(empty($user_code))
        {
            return $this->backErr('Invalid params user_code', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        if($game_id == 0 || $user_balance < 0)
        {
            return $this->backErr('Invalid params game_id or balance', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        
        if(empty($lang))
        {
            $lang = 'en';
        }        
        $token = $this->getUserToken($agency_uid, $user_code);
        if(empty($token))
        {
            //自动创建
            $result = $this->doCreateAccount($agency_uid, $agent_token, $user_code);
            if($result['code'] != 0)
            {
                header('Content-type:application/json');
                echo json_encode($result);
                exit;
            }
            $token = $this->getUserToken($agency_uid, $user_code);
            Log::write('token:'. $token);
            if(empty($token))
            {
                $token = $result['data']['token'];
                Log::write('token1:'. $token);
            }
            
            // return $this->backErr('Invalid user info ', Game::ERROR_CODE_INVALID_USER_INFO, true);
        }
        $username = $this->genUserName($agency_uid, $user_code);
        $ip = Request::ip();        
        $data = [
            'agency_uid' => $agency_uid,
            'agent_token' => $agent_token,
            'username' => $username,
            'game_code' => $game_id,
            'game_type' => 'default',//暂时写死
            'user_balance' => $user_balance,
            'currency_code' => $currency_code,//货币
            'passwd' => md5($username.'_'.$this->common_key),
            'clientuuid' => $user_code.'.'.$ip,
            'token' => $token,
            'platform' => 0,
            'ip' => $ip,
            'lang' => $lang,
            'callback_url' => $callback_url
        ];
        $curl = new Curl();
        $result = $curl->channel_login($data);
        if(!empty($result))
        {            
            $retArr = json_decode($result, true);            
            if(isset($retArr['code']) && $retArr['code'] == 200)
            {
                $cacheKey = md5($token.time()); 
                $retArr['gameid'] = $game_id;               
                //有效时间5分钟
                $this->redis->set("GAME_LAUNCH:".$cacheKey, json_encode($retArr), Game::EXPIRE_TIME);                
                #保存uid
                $uid = $this->getUserUID($agency_uid, $user_code);
                if($this->redis->hget("d_user:".$uid, 'status') == null)
                {
                    $this->redis->hset("data_change:".$uid, 'd_user', 1);
                }
                // Log::write('launch:'. $uid."  ".$callback_url);
                #保存回调
                //           
                {
                    DUserBind::update(['callback' => $callback_url], ['uid' => (int)$uid]);        
                    if(!empty($callback_url))            
                        $this->redis->set("callback:".$uid, $callback_url, Game::EXPIRE_TIME_USER_DATA);
                    else
                        $this->redis->del("callback:".$uid);
                    $this->getUserBindAgency($uid);
                    $auid = $this->getAgencyIdByUid($uid);                    
                    if(!$auid)
                    {
                        $auid = $this->getAgencyIdByAgUid($agency_uid);
                        if($auid && !empty($auid))
                            DUser::where('uid', $uid)->save(['channel_agency' => $auid]);
                    }
                    $this->getAgencyUidById($auid);
                    
                }
                $retData = [
                    'launch_url' => $this->GAME_URL . '?token=' . $cacheKey."&gameid=".$game_id,
                ];
                // $retData = AESCrypto::encrypt_ecb(json_encode($retData), $agent_token);
                return $this->backjson($retData);
            }
        }
        Log::write('data:'. $result);
        return $this->backErr("launch game failed", Game::ERROR_CODE_LAUNCH_GAME_FAILED, true);
    }

    private function launchv2($agency_uid, $agent_token, $user_code, $game_id, $user_balance, $currency_code, $lang = 'en', $callback_url = '') 
    {        
        if(empty($agency_uid) || empty($agent_token))
        {
            return $this->backErr('Invalid params agency_uid or agent_token'.empty($agency_uid), Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        if(empty($user_code))
        {
            return $this->backErr('Invalid params user_code', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        if($game_id == 0 || $user_balance < 0)
        {
            return $this->backErr('Invalid params game_id or balance', Game::ERROR_CODE_INVALID_PARAMS, true);
        }
        
        if(empty($lang))
        {
            $lang = 'en';
        }        
        $token = $this->getUserToken($agency_uid, $user_code);
        if(empty($token))
        {
            //自动创建
            $result = $this->doCreateAccount($agency_uid, $agent_token, $user_code);
            if($result['code'] != 0)
            {
                header('Content-type:application/json');
                echo json_encode($result);
                exit;
            }
            $token = $this->getUserToken($agency_uid, $user_code);
            Log::write('token:'. $token);
            if(empty($token))
            {
                $token = $result['data']['token'];
                Log::write('token1:'. $token);
            }            
            // return $this->backErr('Invalid user info ', Game::ERROR_CODE_INVALID_USER_INFO, true);
        }
        $username = $this->genUserName($agency_uid, $user_code);
        $ip = Request::ip();        
        $data = [
            'app' => 7,
            't' => 7,
            'bundleid' => 'com.yono.games.free.test',
            'passwd' => md5($username.'_'.$this->common_key),
            'user' => $username,
            'gameid' => $game_id,
            'bwss' => 0,
            'token' => $token,
            'platform' => 0,
            'ip' => $ip,
            'lang' => $lang,
            'language' => 2,
            'balance' => $user_balance,
            'clientuuid' => $username,
            'callback_url' => $callback_url,
            'currency_code' => $currency_code,//货币            
            'agency_uid' => $agency_uid,
            'agent_token' => $agent_token,
            'game_type' => 'slots',//暂时写死
        ];
       
        $cacheKey = md5($token.time());            
        //有效时间5分钟
        $this->redis->set("GAME_LAUNCH:".$cacheKey, json_encode($data), Game::EXPIRE_TIME);   
        $this->redis->hset("GAME_SESSION:TOKEN", $token, $cacheKey);   

        #保存uid
        $uid = $this->getUserUID($agency_uid, $user_code);
        if(empty($uid))
        {
            return $this->backErr("launch game failed", Game::ERROR_CODE_LAUNCH_GAME_FAILED, true);
        }
        if($this->redis->hget("d_user:".$uid, 'status') == null)
        {
            $this->redis->hset("data_change:".$uid, 'd_user', 1);
        }
        // Log::write('launch:'. $uid."  ".$callback_url);
        #保存回调
        //
        {
            DUserBind::update(['callback' => $callback_url], ['uid' => (int)$uid]);        
            if(!empty($callback_url))            
                $this->redis->set("callback:".$uid, $callback_url, Game::EXPIRE_TIME_USER_DATA);
            else
                $this->redis->del("callback:".$uid);
            $this->getUserBindAgency($uid);
            $auid = $this->getAgencyIdByUid($uid);                    
            if(!$auid)
            {
                $auid = $this->getAgencyIdByAgUid($agency_uid);
                if($auid && !empty($auid))
                    DUser::where('uid', $uid)->save(['channel_agency' => $auid]);
            }
            $this->getAgencyUidById($auid);
            
        }
        //设置金币余额
        //$this->redis->hset("d_user:".$uid, 'coin', $user_balance);
        $retData = [
            'launch_url' => $this->GAME_URL . '?token=' . $cacheKey."&gameid=".$game_id,
        ];
        // $retData = AESCrypto::encrypt_ecb(json_encode($retData), $agent_token);
        return $this->backjson($retData);
    }

    private function createAccountBySwoole($agency_uid, $agent_token, $user_code)
    {
        $username = $this->genUserName($agency_uid, $user_code);
        $pwdmd5 = md5($username.'_'.$this->common_key);
        $clientuuid = $this->genClientUUID($username);
        $data = [
            'username' => $username,
            'pwdmd5' => $pwdmd5,
            'ip' => Request::ip(),
            'clientuuid' => $clientuuid,
            'id_token' => '',
            'nickname' => $username,
        ];
        // Log::write('the url:'. Env::get('SWOOLE_SERVER_API'));
        Log::write('data:'. json_encode($data));
        return curlGet(Env::get('SWOOLE_SERVER_API').'/game/account/registerchannel?'.http_build_query($data, '', '&'));
    }

    private function doCreateAccount($agency_uid, $apikey, $account)
    {
        $token = $this->getUserToken($agency_uid, $account);
        if(!empty($token))
        {
            return [
                'code' => Game::ERROR_CODE_USER_ALREADY_EXIST,
                'msg' => 'user info already exists',
            ];
        }
        //请求api_swoole_server创建用户
        $result = $this->createAccountBySwoole($agency_uid, $apikey, $account);        
        Log::write('Game createUser result:'. $result);
        $result = json_decode($result, true);
        if($result["errcode"] != 0)
        {
            return [
                'code' => Game::ERROR_CODE_REQUST_SERVICE_ERROR,
                'msg' => 'request service failed:'.$result["error"],
            ];
        }
        if(!isset($result["data"]) || !isset($result["data"]['account']))
        {
            return [
                'msg' => "register service can't return any data.", 
                'code' => Game::ERROR_CODE_REQUST_SERVICE_NO_DATA
            ];
        }
        $token = $result["data"]['token'];
        $accountArr = $result["data"]['account'];
        $username = $this->genUserName($agency_uid, $account);
        $ret = true;
        Db::startTrans();
        try {            
            #生成游戏账号
            $userBind = DUserBind::create(
                [
                    'uid'=> $accountArr['id'],
                    'unionid' => $username,
                    'nickname' => $account,
                    'sex' => 0,
                    'platform' => 0,
                    'create_time' => time(),
                ]
            );
            $agent_id = DChannelParter::where('agency_uid', $agency_uid)->value('id');
            
            $dUser = DUser::create(
                [
                    "uid" => $accountArr['id'],
                    "coin" => 0,
                    "dcoin" => 0, 
                    "usericon" => "", 
                    "create_time" => time(), 
                    "username" => $username, 
                    "playername" => $account,
                    "red_envelope" => 0,
                    "level" => 1,
                    "levelexp" => 0,
                    "invit_uid" => 0,
                    "code" => '', 
                    "appid" => 0, 
                    "justreg" => 1,
                    "isbindfb" => 0, 
                    "isbindgg" => 0, 
                    "kouuid" => "",
                    "create_platform" => 0,
                    "from_channel" => 0,
                    "adchannel" => "api_service",
                    "fgamelist" => "DOMINO",#初始化的游戏列表
                    "avatarframe" => 'avatarframe_1000',#头像框-从游戏里复制来的
                    "chatskin" => "chat_000",
                    "tableskin" => 'desk_000',
                    "pokerskin" => 'poker_free',
                    "frontskin" => 'font_color_0',
                    "emojiskin" => 'emoji_0',
                    "faceskin" => 'poker_face_000',
                    "salonskin" => 'coffee_1',
                    "fbicon" => "",
                    "device" => "",
                    "ddid" => "",
                    "reg_ip" => Request::ip(),
                    "token" => $token,
                    "channel_agency" => $agent_id,
                ]
            );
            
            // 提交事务
            Db::commit();
            #保存token
            $this->redis->hset('channel:user:'.$agency_uid, $username, $token);     
            //设置数据改变状态
            $this->redis->hset("data_change:".$accountArr['id'], 'd_user', 1);
        }
        catch (\Exception $e) 
        {
            // 回滚事务
            Db::rollback();
            Log::write('[createUser]:'. $e->getMessage() . " file:" . $e->getFile() . " line:" . $e->getLine() . " 行");     
            $ret = false;
        }
        if(!$ret)
        {
            return ['msg' => 'create user failed', 'code' => Game::ERROR_CODE_CREATE_USER_FAILED];
        }
        $ret = [
            "uid" => $accountArr['id'],
        ];
        return ['code' => 0, 'msg' => 'succ', 'data' => $ret];
    }
}