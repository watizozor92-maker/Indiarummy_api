<?php
//支付相关的配置
return [
    //配置
    'queue_order_online' => 'order_online_deliver',
    'contact_us'         => 'https://vm.providesupport.com/04qetijpc30hp11ayhbj2xfvh4',
    'private_key'        => '-----BEGIN RSA PRIVATE KEY-----
MIICXgIBAAKBgQDAOwVPLAb88kIDMidTnCoIWl1rY1TbDVwoNkjenCpTFQk2RhNm
6v9HBpkAqCv68FXuIc6BkuR+PgmaY5fPO63m6OnEPlUNjbZCRN/nn14+OqrAIA7w
UNna3dkJSzwmK++UpoFOwDyyT2rCQIYlvPrfk/AY0uMC8kxnKPkVy8hwNQIDAQAB
AoGBALtNhunS22hB4OJeTwVfx3DmI9qMKdVa2i3wXuZRQcg8WgKBG0yPs3cQTarf
MiNLZF8on5+PcoYzioXdfsEUxGbmTRdizMUhp88SSGJ20aVAUjihNkVVmUhVF5B+
X2/jqhuAXisGGPnczk95ugYFaVQx69dzI3+xoKpKs3dqrWfBAkEA6FRq50GHhJPK
WW7lDSd0yyj1aHALU1Gczqv4KAkCn3xF2X7HAKeGv/sgVDK13zcSd9/oZhcMM8XU
VMQ670V1hQJBANPQvjGk3VZIg7jBHjhZSaRlMTFH6flZL4BSPmD7ac4iGQK/TW/g
aRITRN34y6M4e8ZfItWu7HLgDcYwJ+zL9vECQQDBrpaMgaGjUb3QPtWu2KTSNSwZ
S8b1R8VMuiqf3ZsVL/nfLoRiNEbux0EVUE00b+nyi3Rnn3EpvBOeMxTaofItAkA7
kaYbkhEJVgVtbSpRkJLMYAUQpffgPzvDq92NKsebyC0ef6yIfSUvhynD/1dNxPHS
XSkPzU0EFYzqItrnlokRAkEAujU7CXar3wLnQ7tIuqbjL2KgrdZBl/NYpNK43dNU
HyRbjjBmpJzrZNdo0mkpj7aiTkV2Gzzz9pZWxQWnCwo82Q==
-----END RSA PRIVATE KEY-----',
    'pubKey' => "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDGXu56p2EJ8NWSW9gcqcm5XTXv
bdeYOk6p8/hHwdUgWEXLLmonMM2wVVeQJU12Yfm68iUg5nARvDlJSkpnxZsQvydw
3p2f7ssyB2BVyWAZgJtzSpqGWrJ6OUVkPfU8WU507B3p6WIFrnrKds0GDK39Ntpd
Izbr1F+4byGsSCC0zQIDAQAB
-----END PUBLIC KEY-----",
    'notify_url'    => '',
    'memberid'      => 10201, //商户id
    'md5key'        => '3nhzas66s2qmr8hbkdfnto2cptmx0mly', //md5签名key
    'pay_bank_code' => 1009, //支付通道
    'order_url' => 'https://mangoopay.com/Pay_Index.html', //上游下单地址
    'query_url' => 'https://mangoopay.com/Pay_Trade_query.html',  //上游的查单地址
    'img_url' => 'https://img.yonogames.com', //图片地址接口
    'game_api' => env('GAMESERVER_API'),
    'queue_order' => 'order_online_deliver',
];