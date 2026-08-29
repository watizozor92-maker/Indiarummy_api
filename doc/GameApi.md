### API 接口说明

api地址 https://api.kjasdakls.shop/

##### 申请到的ApiKey
  
```json
agency_uid: "agency10000"
api_key: "qa/Oc5kikI5BfM81BusHbKeSDVeLQBvsfUyaLzPsA+E="
api_url : "https://api.kjasdakls.shop/"
```
>   **提示: api_key 用于后续 API请求对payload 内容加解密,请妥善保管**

返回内容说明:
| 参数      | 说明|
| ----------- | ----------- |
|code|错误码-详情参见附录1|
|msg|错误提示信息|
|data|包含key信息的数据|
---
##### 1. 创建玩家
请求方法:       post 
url:           /game/createuser
Content-Type:   application/json

请求字段: 

| 参数      | 说明 | 数据类型| 必填|
| ----------- | -----------| --- | ----|
|agency_uid|代理标识  |String|是|
|timestamp| 时间戳(毫秒)|bigint|是|
|payload| 加密的json内容|String|是|

payload字段:
| 参数      | 说明 | 必填|
| ----------- | ----------- | ----|
|account    |玩家用户名     |是|

返回内容:    
```json
{
    "code": 0,
    "msg": "succ",
    "payload": {"uid":13000}
}

```
返回内容说明:
| 参数      | 说明|
| ----------- | ----------- |
|code|错误码-详情参见附录1|
|msg|错误提示信息|
|payload| 加密的json内容|
```
payload示例:
{"uid":13000}
```

---
##### 2. 玩家启动游戏
请求方法: post
url: /game/v1
Content-Type:   application/json

body:
{
    "agency_uid": "agency10000",//替换商户id
    "timestamp": 1762414122945,
    "payload": "(aes-256-ecb encryption text)"
}

请求字段: 

| 参数      | 说明 | 数据类型| 必填|
| ----------- | -----------| --- | ----|
|agency_uid|代理标识  |String|是|
|timestamp| 时间戳(毫秒)|bigint|是|
|payload| 加密的json内容|String|是|

payload 字段:
| 参数      | 说明 |数据类型|必填|
| ---- | ---- |---|---|
|agency_uid|代理ID |String|是|
|timestamp|当前时间戳(毫秒)|bigint|是|
|account|玩家用户名|String|是|
|game_id|游戏ID-参见附录2|int|是|
|credit_amount| 玩家余额|double|是|
|currency_code|货币（参见附录4）|String|是|
|language|语言（参见附录3）|String|是|
|callback_url|注单回调地址|String|否|

返回内容:

```json
{
    "code": 0,
    "msg": "succ",
    "payload": {
        "launch_url": "http://game.xx.com/launch/game?token=05a853da0f2c96c39e133bf2e0c5132f"
    }
}
```
返回内容说明:
| 参数      | 说明|
| ----------- | ----------- |
|code|错误码-详情参见附录1|
|msg|错误提示信息|
|payload| 加密的json内容|

返回值 payload(json 格式):
| 参数      | 说明 |
| ----------- | ----------- |
|url|http://game.xx.com/launch/game?token=05a853da0f2c96c39e133bf2e0c5132f|
---
##### 3. 进入游戏 - (前端)

游戏服务器入口: 
 1. http
    * 登录账号 **43.128.206.225:9951**
    * 连游戏服 **43.128.206.225:9959**
 2. https
    * 登录账号 wss://xyule.cyou/login
    * 连游戏服 wss://xyule.cyou/game
    
构造游戏登录协议:
```json
{
    "c":2,
    "session" : "token value",    
}
```

返回内容:
```json
{"backGameCoin":0,"voice":0,"c_idx":1,"servertime":1763372635,"verify":{"report":0,"sender":0,"redem":0},"todayrewards":0,"fbshare":0,"playerInfo":{"charmlist":{},"viproomcnt":[0,2,2,2,2,2,4,4,4,4,4,6,6,6,6,6,8,8,8,8,8],"svipexp":0,"ecoin":0,"dcoin":0,"tableskin":"desk_000","tmpvip":1,"newerpack":1,"emojiskin":"emoji_0","charmpack":100,"fgamelist":{},"levelexp":0,"invits":0,"emojilist":["emoji_0"],"nextvipexp":50,"salontesttime":0,"todaybonus":0,"rp":0,"pokerskin":"poker_free","dcashbonus":0,"levelup":0,"cashbonus":0,"pinlist":{},"isbindfb":0,"uid":13000,"guide":{},"blockuids":{},"leagueexp":0,"isbindphone":0,"nextbag":0,"fbrewards":{},"avatarframe":"avatarframe_1000","fbicon":"","getviplv":[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],"ticket":0,"roomcnt":0,"playername":"agency10000_test102","moneybag":0,"isbindgoogle":0,"frontskin":"font_color_0","usericon":"","charm":0,"logintype":7,"novice":1,"isbindapple":0,"bindfbdiamond":10,"svip":0,"kyc":0,"salonskin":"coffee_1","bankcoin":0,"coin":0,"leaguelevel":0,"hasQuest":0,"sex":1,"diamond":0,"chatskin":"chat_000","faceskin":"poker_face_000","verifyfriend":0,"level":1,"invit_uid":0,"bindfbcoin":1,"status":1,"code":""},"shoptimeout":1,"reg":1,"rate":0,"newbiedone":0,"vipsign":0,"country":0,"poplist":[1],"deskFlag":0,"bonuswheel":0,"maxbet":5000,"c":2,"code":200}
```

---
##### 4. 游戏注单信息回调
提示： 
运营商需要实现该接口，该接口用于一局游戏结束时回传游戏注单信息。

回调方法: post
url: 在调用接口 [/game/v1] 时，传入callback_url 字段
Content-Type:   application/json

回调字段: 

| 参数      | 说明 | 数据类型| 必填|
| ----------- | -----------| --- | ----|
|agency_uid|代理标识  |String|是|
|timestamp| 时间戳(毫秒)|bigint|是|
|payload| 加密的json内容|String|是|

payload 字段:
| 参数      | 说明 |数据类型|必填|
| ---- | ---- |---|---|
|serial_number|记录唯一编号 |String|是|
|currency_code|货币（参见附录4）|String|是|
|game_id|游戏ID-参见附录2|int|是|
|account|玩家账号|String|是|
|win_amount|赢的金额|String|是|
|bet_amount|下注金额|String|是|
|game_round|游戏局号|String|是|
|data|育游戏详细数据|String|否|
---

##### 5. 获取游戏注单记录
请求方法: post
url: /game/transaction
Content-Type:   application/json

body:
{
    "agency_uid": "agency10000",//替换商户id
    "timestamp": 1762414122945,
    "payload": "(aes-256-ecb encryption text)"
}

payload 字段:
| 参数      | 说明 |数据类型|必填|
| ---- | ---- |---|---|
|from_date|开始日期（UTC+0时间戳到毫秒） |long|是|
|to_date|结束日期（UTC+0时间戳到毫秒）|long|是|
|page_no|页码|int|是|
|page_size|页大小（最小：1，最大：5000）|int|是|


返回内容:

```json
{
    "code": 0,
    "msg": "succ",
    "payload": {
        "launch_url":  {"records":[{"agency_uid":"abc"...},{}...],"total_page":1000....}
    }
}
```
| 参数名 | 数据类型 | 描述 | 是否必填 |
|-------|---------|------|---------|
| code | Int | 代码 | 是 |
| msg | String | 消息 | 是 |
| payload | JSONString | 返回结果 | 是 |

**payload内的参数**

| 参数名 | 数据类型 | 描述 | 是否必填 |
|-------|---------|------|---------|
| total_count | Int | 总条数 | 是 |
| current_page | Int | 页码 | 是 |
| page_size | Int | 页大小 | 是 |
| records | JSONArray | JSON数组 | 是 |

**records内的参数**

| 参数名 | 数据类型 | 描述 | 是否必填 |
|-------|---------|------|---------|
| agency_uid | String | 代理识别码 | 是 |
| account | String | 玩家账号名 | 是 |
| bet_amount | String | 下注金额 | 是 |
| win_amount | String | 赢的金额 | 是 |
| currency_code | String | 币种 | 是 |
| serial_number | String | 唯一编号 | 是 |
| game_round | String | 游戏局号 | 是 |
| game_uid | String | 游戏UID | 是 |
| timestamp | long | 交易时间戳 | 是 |

#### 加密说明
payload: 使用 AES-256-ECB 加密算法 并使用base64 对加密内容进行编码; 解密时,按照先base64解码再 AES-256-ECB 解密的顺序即可得到 原文
密钥: 通过 game/applykey 接口申请后自行妥善保存

PHP代码示例:
```php
//加密
$API_URL = "https://api.kjasdakls.shop/game/createuser"           //API 地址
$agency_uid = 'agency10000';                                    //商户号
$timestamp = round(microtime(true) * 1000);                     //时间戳
$payload = [    
    'account' => 'account10000'                                 //要创建的用户名
];
$apikey = '6HGcGNejIYM2Wb0JSK4Ox1HFv7zzY2l6w5y0A2dBWk8=';
$data = openssl_encrypt(json_encode($payload), 'AES-256-ECB', $apikey, OPENSSL_RAW_DATA);
//base64编码
$base64str = base64_encode($data);
#request_json 需要自行实现
request_json($API_URL, [
    'agency_uid' => $agency_uid,
    'timestamp' => $timestamp,
    'payload' => $payload
]);

```
---

#### 附录1
code - 错误码:

| 错误码      | 说明 |
| ----------- | ----------- |
|0|成功          |
|1|无效参数          |
|1|无效token          |
|11|无效渠道信息          |
|12|无效用户信息          |
|13|代理不存在          |
|14|代理已经存在          |
|15|用户已经存在          |
|16|创建渠道失败          |
|17|创建用户失败          |
|18|创建信息服务执行失败          |
|19|创建请求未生成数据          |
|20|启动游戏失败          |
|21|无效的Payload信息          |

#### 附录2
游戏ID

| 游戏ID      | Game Name | Game Type|
| ----------- | ----------- |---------- |
|419|regaltiger          |slot|
|541|powerofthekraken          |slot|
|635|thelegendofdragon          |slot|
|692|archer          |slot|
|693|alibaba          |slot|
|694|xerxes          |slot|
|695|hanginggarden          |slot|
|696|sinbad          |slot|
|697|gorgeouscleopatra          |slot|
|698|lampofaladdin          |slot|
|699|Sphinx          |slot|



#### 附录3
语言,调用接口时,使用语言代码 (Language) 来指定语言

| 语言标识 (Locale) | 语言代码 (Language) | 地区代码 (Region) | 显示名称 |
| :--- | :--- | :--- | :--- |
| **zh-CN** | zh | CN | 简体中文 |
| **zh-TW** | zh | TW | 繁體中文 |
| **zh-HK** | zh | HK | 繁體中文 (香港) |
| **en-US** | en | US | English |
| **en-GB** | en | GB | English (UK) |
| **en-MY** | en | MY | English (Malaysia) |
| **ja-JP** | ja | JP | 日本語 |
| **ko-KR** | ko | KR | 한국어 |
| **vi-VN** | vi | VN | Tiếng Việt |
| **th-TH** | th | TH | ไทย |
| **id-ID** | id | ID | Bahasa Indonesia |
| **ms-MY** | ms | MY | Bahasa Melayu |

#### 附录4
货币,调用接口时,使用货币代码 (Currency Code) 来指定货币

| 语言标识 (Locale) | 货币代码 (Currency Code) | 货币符号 (Symbol) | 显示名称 (Display Name) |
| :--- | :--- | :--- | :--- |
| **zh-CN** | CNY | ¥ | 人民币 (Renminbi) |
| **zh-TW** | TWD | NT$ | 新台币 (New Taiwan Dollar) |
| **zh-HK** | HKD | HK$ | 港币 (Hong Kong Dollar) |
| **en-US** | USD | $ | 美元 (US Dollar) |
| **en-GB** | GBP | £ | 英镑 (British Pound) |
| **en-MY** | MYR | RM | 马来西亚林吉特 (Malaysian Ringgit) |
| **ja-JP** | JPY | ¥ | 日元 (Japanese Yen) |
| **ko-KR** | KRW | ₩ | 韩元 (South Korean Won) |
| **vi-VN** | VND | ₫ | 越南盾 (Vietnamese Dong) |
| **th-TH** | THB | ฿ | 泰铢 (Thai Baht) |
| **id-ID** | IDR | Rp | 印尼盾 (Indonesian Rupiah) |
| **ms-MY** | MYR | RM | 马来西亚林吉特 (Malaysian Ringgit) |