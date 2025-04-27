<?php
//微信支付v3配置文件

$wechatpay_config = [
    //应用ID
    'appid' => $channel['appid'],

    //商户号
    'mchid' => $channel['appmchid'],

    //APIv3密钥
    'apikey' => $channel['appsecret'],

    //「商户API私钥」文件路径
    'merchantPrivateKeyFilePath' => PLUGIN_ROOT.$channel['plugin'].'/cert/apiclient_key.pem',

    //「商户API证书」的「证书序列号」
    'merchantCertificateSerial' => $channel['appkey'],

    //「微信支付平台证书或公钥」文件路径
    'platformCertificateFilePath' => PLUGIN_ROOT.$channel['plugin'].'/cert/pub_key.pem',

    //微信支付平台公钥ID
    'platformCertificateSerial' => $channel['publickeyid'],
];

if(file_exists(PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appmchid'].'/apiclient_key.pem')){
    $wechatpay_config['merchantPrivateKeyFilePath'] = PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appmchid'].'/apiclient_key.pem';
	$wechatpay_config['platformCertificateFilePath'] = PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appmchid'].'/pub_key.pem';
}

return $wechatpay_config;