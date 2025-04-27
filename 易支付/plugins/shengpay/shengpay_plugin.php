<?php
class shengpay_plugin
{
	static public $info = [
		'name'        => 'shengpay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '盛付通', //支付插件显示名称
		'author'      => '盛付通', //支付插件作者
		'link'        => 'https://www.shengpay.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appsecret' => [
				'name' => '盛付通公钥',
				'type' => 'textarea',
				'note' => '',
			],
			'appswitch' => [
				'name' => '收单接口类型',
				'type' => 'select',
				'options' => [0=>'线上',1=>'线下'],
			],
		],
		'select_alipay' => [
			'1' => '扫码支付',
			'2' => '电脑网站支付',
			'3' => '手机网站支付',
		],
		'select_wxpay' => [
			'1' => 'JSAPI支付',
			'2' => 'Native支付',
			'3' => 'H5支付',
			'4' => '小程序收银台',
		],
		'select' => null,
		'note' => '如果是微信支付，需要<a href="./plugin_page.php?channel=[channel]&func=wxconfig" target="_blank">配置绑定AppId和支付目录</a>', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(checkwechat() && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif(checkmobile() && (in_array('3',$channel['apptype']) || in_array('4',$channel['apptype']))){
				return ['type'=>'jump','url'=>'/pay/wxwappay/'.TRADE_NO.'/'];
			}else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}elseif($order['typename']=='bank'){
			return ['type'=>'jump','url'=>'/pay/bank/'.TRADE_NO.'/'];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $conf, $device, $mdevice;

		if($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat' && in_array('1',$channel['apptype'])){
				return ['type'=>'jump','url'=>$siteurl.'pay/wxjspay/'.TRADE_NO.'/?d=1'];
			}elseif($device=='mobile' && (in_array('3',$channel['apptype']) || in_array('4',$channel['apptype']))){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//统一下单
	static private function addOrder($tradeType, $extra=null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$path = $channel['appswitch'] == 1 ? '/pay/unifiedorderOffline' : '/pay/unifiedorder';

		$param = [
			'outTradeNo' => TRADE_NO,
			'totalFee' => intval(round($order['realmoney']*100)),
			'currency' => 'CNY',
			'tradeType' => $tradeType,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'extra' => $extra,
			'body'  => $ordername,
			'clientIp'  => $clientip,
		];
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $path, $param) {
			$result = $client->execute($path, $param);
			\lib\Payment::updateOrder(TRADE_NO, $result['transactionId']);
			return $result['payInfo'];
		});
	}

	//微信小程序收银台
	static private function wxlite(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		$param = [
			'outTradeNo' => TRADE_NO,
			'totalFee' => intval(round($order['realmoney']*100)),
			'currency' => 'CNY',
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'pageUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'nonceStr' => random(32),
			'body'  => $ordername,
			'clientIp'  => $clientip,
		];
		
		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $param) {
			$result = $client->execute('/pay/preUnifieAppletdorder', $param);
			\lib\Payment::updateOrder(TRADE_NO, $result['transactionId']);
			return $result['payInfo'];
		});
	}

	//支付宝支付
	static public function alipay(){
		global $channel, $device, $mdevice;
		if(in_array('3',$channel['apptype']) && ($device=='mobile' || checkmobile())){
			$tradeType = 'alipay_wap';
		}elseif(in_array('2',$channel['apptype']) && ($device=='pc' || !checkmobile())){
			$tradeType = 'alipay_pc';
		}else{
			$tradeType = 'alipay_qr';
		}
		try{
			$code_url = self::addOrder($tradeType);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		if($tradeType == 'alipay_qr'){
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
		}else{
			return ['type'=>'jump','url'=>$code_url];
		}
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl;
		if(in_array('2',$channel['apptype'])){
			try{
				$code_url = self::addOrder('wx_native');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
		}elseif(in_array('1',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
		}elseif(in_array('4',$channel['apptype'])){
			$code_url = $siteurl.'pay/wxwappay/'.TRADE_NO.'/';
		}else{
			return ['type'=>'error','msg'=>'当前支付通道没有开启的支付方式'];
		}

		if (checkmobile()) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);
		if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信公众号不存在'];
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->GetOpenid();
		}catch(Exception $e){
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks) return $blocks;

		//②、统一下单
		try{
			$pay_info = self::addOrder('wx_jsapi', json_encode(['openId'=>$openid, 'appId'=>$wxinfo['appid']]));
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if($_GET['d']==1){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>$pay_info, 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl, $channel, $order, $ordername, $conf;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');
		
		//①、获取用户openid
		$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
		if(!$wxinfo)exit('{"code":-1,"msg":"支付通道绑定的微信小程序不存在"}');
		try{
			$tools = new \WeChatPay\JsApiTool($wxinfo['appid'], $wxinfo['appsecret']);
			$openid = $tools->AppGetOpenid($code);
		}catch(Exception $e){
			exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
		}
		$blocks = checkBlockUser($openid, TRADE_NO);
		if($blocks)exit('{"code":-1,"msg":"'.$blocks['msg'].'"}');

		//②、统一下单
		try{
			$pay_info = self::addOrder('wx_lite', json_encode(['openId'=>$openid, 'appId'=>$wxinfo['appid']]));
		}catch(Exception $ex){
			exit('{"code":-1,"msg":"'.$ex->getMessage().'"}');
		}

		exit(json_encode(['code'=>0, 'data'=>json_decode($pay_info, true)]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if(in_array('3',$channel['apptype'])){
			try{
				$code_url = self::addOrder('wx_wap');
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'jump','url'=>$code_url];
		}elseif(in_array('4',$channel['apptype'])){
			try{
				$code_url = self::wxlite();
			}catch(Exception $ex){
				return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}


	//云闪付扫码支付
	static public function bank(){
		global $channel;
		try{
			$code_url = self::addOrder('upacp_qr');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			if ($data['status'] == 'PAY_SUCCESS') {
				$out_trade_no = $data['outTradeNo'];
				$trade_no = $data['transactionId'];
				$payerInfo = json_decode($data['payerInfo'], true);
				$buyer = $payerInfo['openid'];
				$bill_trade_no = $payerInfo['officOrderNum'];
				if($out_trade_no == TRADE_NO){
					processNotify($order, $trade_no, $buyer, $bill_trade_no);
				}
				return ['type'=>'html','data'=>'SUCCESS'];
			}
			return ['type'=>'html','data'=>'FAIL'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel, $conf;
		if(empty($order))exit();

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		
		$param = [
			'outTradeNo' => $order['trade_no'],
			'outRefundNo' => $order['refund_no'] ? $order['refund_no'] : 'R'.$order['trade_no'],
			'refundFee' => intval(round($order['refundmoney']*100)),
			'notifyUrl' => $conf['localurl'].'pay/refundnotify/'.TRADE_NO.'/',
		];

		try{
			$result = $client->execute('/refund/orderRefund', $param);
		}catch(Exception $e){
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}

		return ['code'=>0, 'trade_no'=>$result['refundId'], 'refund_fee'=>$result['refundFee']/100];
	}

	//退款异步回调
	static public function refundnotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		$data = json_decode($json,true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		require_once PAY_ROOT."inc/ShengPayClient.php";

		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);
		$verify_result = $client->verifySign($data);

		if($verify_result){
			if ($data['refundStatus'] == 'REFUND_SUCCESS') {
				$out_trade_no = $data['refundOrderNo'];
				$trade_no = $data['refundId'];

				return ['type'=>'html','data'=>'SUCCESS'];
			}
			return ['type'=>'html','data'=>'FAIL'];
		}
		else {
			return ['type'=>'html','data'=>'SIGN FAIL'];
		}
	}

	//微信参数配置
	static public function wxconfig(){
		global $siteurl,$channel,$islogin;
		if(!$islogin) exit('Access Denied');

		require_once PAY_ROOT."inc/ShengPayClient.php";
		$client = new ShengPayClient($channel['appid'], $channel['appkey'], $channel['appsecret']);

		if(isset($_POST['appid'])){
			$param = [
				'subMchId' => $channel['appid'],
				'configType' => '2',
				'appId' => trim($_POST['appid']),
			];
			try{
				$client->execute('/report/appidBind', $param);
				showmsg('AppID绑定成功',1);
			}catch(Exception $e){
				showmsg('微信参数配置失败！'.$e->getMessage(),4);
			}
		}elseif(isset($_POST['payurl'])){
			$param = [
				'subMchId' => $channel['appid'],
				'configType' => '1',
				'payUrl' => trim($_POST['payurl']),
			];
			try{
				$client->execute('/report/appidBind', $param);
				showmsg('支付授权目录配置成功',1);
			}catch(Exception $e){
				showmsg('微信参数配置失败！'.$e->getMessage(),4);
			}
		}

		$wxinfo = \lib\Channel::getWeixin($channel['appwxmp']);

		$param = [
			'subMchId' => $channel['appid'],
			'configType' => '5',
		];
		try{
			$result = $client->execute('/report/appidBind', $param);
			$appid_list = json_decode($result['appidConfigArray'], true);
			$payurl_list = json_decode($result['payUrlArray'], true);
			$data = ['appid_list' => $appid_list, 'payurl_list' => $payurl_list, 'appid'=>$wxinfo ? $wxinfo['appid'] : ''];
			include PAY_ROOT.'wxconf.page.php';
		}catch(Exception $e){
			showmsg('微信参数配置查询失败！'.$e->getMessage(),4);
		}
	}
}