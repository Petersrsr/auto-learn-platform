<?php

class lakala_plugin
{
	static public $info = [
		'name'        => 'lakala', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '拉卡拉', //支付插件显示名称
		'author'      => '拉卡拉', //支付插件作者
		'link'        => 'https://www.lakala.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay','bank'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appid' => [
				'name' => 'APPID',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '商户号',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '终端号',
				'type' => 'input',
				'note' => '',
			],
			'appselect' => [
				'name' => '接口类型',
				'type' => 'select',
				'options' => [0=>'聚合扫码',1=>'聚合收银台'],
			],
			'appswitch' => [
				'name' => '环境选择',
				'type' => 'select',
				'options' => [0=>'生产环境',1=>'测试环境'],
			],
		],
		'select' => null,
		'note' => '将商户证书 api_cert.cer（或APPID.cer）、商户私钥 api_private_key.pem（或APPID.pem）上传到 /plugins/lakala/cert/', //支付密钥填写说明
		'bindwxmp' => true, //是否支持绑定微信公众号
		'bindwxa' => true, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($channel['appselect'] == 1){
			return ['type'=>'jump','url'=>'/pay/cashier/'.TRADE_NO.'/?type='.$order['typename']];
		}elseif($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
			if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false && $channel['appwxmp']>0){
				return ['type'=>'jump','url'=>'/pay/wxjspay/'.TRADE_NO.'/'];
			}elseif(checkmobile()==true && $channel['appwxa']>0){
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

		if($channel['appselect'] == 1){
			return ['type'=>'jump','url'=>'/pay/cashier/'.TRADE_NO.'/?type='.$order['typename']];
		}elseif($order['typename']=='alipay'){
			return self::alipay();
		}elseif($order['typename']=='wxpay'){
			if($mdevice=='wechat'){
				return self::wxjspay();
			}elseif($device=='mobile' && $channel['appwxa']>0){
				return self::wxwappay();
			}else{
				return self::wxpay();
			}
		}elseif($order['typename']=='bank'){
			return self::bank();
		}
	}

	//聚合扫码下单
	static private function qrcode($account_type, $trans_type, $extend = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/LakalaClient.php");

		$params = [
			'merchant_no' => $channel['appmchid'],
			'term_no' => $channel['appkey'],
			'out_trade_no' => TRADE_NO,
			'account_type' => $account_type,
			'trans_type' => $trans_type,
			'total_amount' => strval($order['realmoney']*100),
			'location_info' => [
				'request_ip' => $clientip
			],
			'subject' => $ordername,
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];
		if($extend){
			$params['acc_busi_fields'] = $extend;
		}

		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);

		return \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
			global $channel;
			$result = $client->execute('/api/v3/labs/trans/preorder', $params);
			\lib\Payment::updateOrder(TRADE_NO, $result['trade_no']);
			if($channel['appswitch']==1){
				echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
			}
        	return $result['acc_resp_fields'];
		});
	}

	//被扫支付
	static public function micropay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		$auth_code = $_GET['auth_code'];
		if(!$auth_code) return ['type'=>'error','msg'=>'auth_code不能为空'];

		require(PAY_ROOT."inc/LakalaClient.php");

		$params = [
			'merchant_no' => $channel['appmchid'],
			'term_no' => $channel['appkey'],
			'out_trade_no' => TRADE_NO,
			'auth_code' => $auth_code,
			'total_amount' => strval($order['realmoney']*100),
			'location_info' => [
				'request_ip' => $clientip
			],
			'subject' => $ordername,
			'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
		];

		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);

		try{
			$result = \lib\Payment::lockPayData(TRADE_NO, function() use($client, $params) {
				global $channel;
				$result = $client->execute('/api/v3/labs/trans/micropay', $params);
				\lib\Payment::updateOrder(TRADE_NO, $result['trade_no']);
				if($channel['appswitch']==1){
					echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
				}
				return $result['acc_resp_fields'];
			});
			return ['type'=>'error','msg'=>'被扫支付下单成功！'.json_encode($result)];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'被扫支付下单失败！'.$ex->getMessage()];
		}
	}

	//交易查询
	static public function query(){
		global $channel, $order;

		require(PAY_ROOT."inc/LakalaClient.php");

		$params = [
			'merchant_no' => $channel['appmchid'],
			'term_no' => $channel['appkey'],
			'out_trade_no' => TRADE_NO,
		];

		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);

		try{
			$result = $client->execute('/api/v3/labs/query/tradequery', $params);
			if($channel['appswitch']==1){
				echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
			}
			echo '交易查询成功！'.json_encode($result);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'交易查询失败！'.$ex->getMessage()];
		}
	}

	//收银台下单
	static public function cashier(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;
		$pay_type = $_GET['type'];
		if($pay_type == 'alipay'){
			$pay_mode = 'ALIPAY';
		}elseif($pay_type == 'wxpay'){
			$pay_mode = 'WECHAT';
		}elseif($pay_type == 'bank'){
			$pay_mode = 'UNION';
		}

		require(PAY_ROOT."inc/LakalaClient.php");

		$params = [
			'out_order_no' => TRADE_NO,
			'merchant_no' => $channel['appmchid'],
			//'term_no' => $channel['appkey'],
			'total_amount' => strval($order['realmoney']*100),
			'order_efficient_time' => date('YmdHis', time()+1200),
			'notify_url' => $conf['localurl'] . 'pay/cashiernotify/' . TRADE_NO . '/',
			'support_refund' => 1,
			'callback_url' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'order_info' => $ordername,
			'counter_param' => json_encode(['pay_mode'=>$pay_mode]),
		];

		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);

		try{
			$result = $client->cashier('/api/v3/ccss/counter/order/special_create', $params);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'收银台下单失败！'.$ex->getMessage()];
		}
		\lib\Payment::updateOrder(TRADE_NO, $result['pay_order_no']);
		if($channel['appswitch']==1){
			echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea><br/><br/><a href="'.$result['counter_url'].'">点此继续支付</a></div>';
			exit;
		}
		return ['type'=>'jump','url'=>$result['counter_url']];
	}

	//交易查询
	static public function cashierquery(){
		global $channel, $order;

		require(PAY_ROOT."inc/LakalaClient.php");

		$params = [
			'merchant_no' => $channel['appmchid'],
			'out_order_no' => TRADE_NO,
		];

		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);

		try{
			$result = $client->cashier('/api/v3/ccss/counter/order/query', $params);
			if($channel['appswitch']==1){
				echo '<div>请求报文：<br/><textarea name="request_body" rows="3" style="width:100%">'.$client->request_body.'</textarea><br/>返回报文：<br/><textarea name="response_body" rows="3" style="width:100%">'.$client->response_body.'</textarea></div>';
			}
			echo '交易查询成功！'.json_encode($result);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'交易查询失败！'.$ex->getMessage()];
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$result = self::qrcode('ALIPAY', '41');
			$code_url = $result['code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$code_url];
	}

	//微信扫码支付
	static public function wxpay(){
		global $channel, $siteurl;
		/*try{
			$result = self::qrcode('WECHAT', '41');
			$code_url = $result['code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}*/
		$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';

		if (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$code_url];
		}
	}

	//微信公众号支付
	static public function wxjspay(){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

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

		try{
			$extend = ['sub_appid' => $wxinfo['appid'], 'user_id' => $openid];
			$result = self::qrcode('WECHAT', '51', $extend);
			$pay_info = ['appId'=>$result['app_id'],'timeStamp'=>$result['time_stamp'],'nonceStr'=>$result['nonce_str'],'package'=>$result['package'],'paySign'=>$result['pay_sign'],'signType'=>$result['sign_type']];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败 '.$ex->getMessage()];
		}

		if($_GET['d']=='1'){
			$redirect_url='data.backurl';
		}else{
			$redirect_url='\'/pay/ok/'.TRADE_NO.'/\'';
		}
		return ['type'=>'page','page'=>'wxpay_jspay','data'=>['jsApiParameters'=>json_encode($pay_info), 'redirect_url'=>$redirect_url]];
	}

	//微信小程序支付
	static public function wxminipay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		$code = isset($_GET['code'])?trim($_GET['code']):exit('{"code":-1,"msg":"code不能为空"}');

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

		try{
			$extend = ['sub_appid' => $wxinfo['appid'], 'user_id' => $openid];
			$result = self::qrcode('WECHAT', '71', $extend);
			$pay_info = ['appId'=>$result['app_id'],'timeStamp'=>$result['time_stamp'],'nonceStr'=>$result['nonce_str'],'package'=>$result['package'],'paySign'=>$result['pay_sign'],'signType'=>$result['sign_type']];
		}catch(Exception $ex){
			exit(json_encode(['code'=>-1, 'msg'=>'微信支付下单失败 '.$ex->getMessage()]));
		}

		exit(json_encode(['code'=>0, 'data'=>$pay_info]));
	}

	//微信手机支付
	static public function wxwappay(){
		global $siteurl,$channel, $order, $ordername, $conf, $clientip;

		if($channel['appwxa']>0){
			$wxinfo = \lib\Channel::getWeixin($channel['appwxa']);
			if(!$wxinfo) return ['type'=>'error','msg'=>'支付通道绑定的微信小程序不存在'];
			try{
				$code_url = wxminipay_jump_scheme($wxinfo['id'], TRADE_NO);
			}catch(Exception $e){
				return ['type'=>'error','msg'=>$e->getMessage()];
			}
			return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$code_url];
		}elseif($channel['appwxmp']>0){
			$code_url = $siteurl.'pay/wxjspay/'.TRADE_NO.'/';
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$code_url];
		}else{
			return self::wxpay();
		}
	}

	//云闪付扫码支付
	static public function bank(){
		try{
			$result = self::qrcode('UQRCODEPAY', '41');
			$code_url = $result['code'];
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'云闪付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'bank_qrcode','url'=>$code_url];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		if($channel['appswitch']==1){
			file_put_contents('lakala_log.txt',$json);
		}

		$data = json_decode($json, true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		$authorization = $_SERVER['HTTP_AUTHORIZATION'];
		if(!$authorization) return ['type'=>'html','data'=>'no sign'];

		require(PAY_ROOT."inc/LakalaClient.php");

		//计算得出通知验证结果
		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);
		$verify_result = $client->verifySign($authorization, $json);

		if($verify_result) {//验证成功
			$out_trade_no = $data['out_trade_no'];
			$api_trade_no = $data['trade_no'];
			$total_amount = $data['total_amount'];
			$buyer = $data['user_id2'];

			if($data['trade_status'] == 'SUCCESS'){
				if($out_trade_no == TRADE_NO){
					processNotify($order, $api_trade_no, $buyer);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//异步回调
	static public function cashiernotify(){
		global $channel, $order;

		$json = file_get_contents('php://input');
		if($channel['appswitch']==1){
			file_put_contents('lakala_log.txt',$json);
		}

		$data = json_decode($json, true);
		if(!$data) return ['type'=>'html','data'=>'no data'];

		$authorization = $_SERVER['HTTP_AUTHORIZATION'];
		if(!$authorization) return ['type'=>'html','data'=>'no sign'];

		require(PAY_ROOT."inc/LakalaClient.php");

		//计算得出通知验证结果
		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);
		$verify_result = $client->verifySign($authorization, $json);

		if($verify_result) {//验证成功
			$out_trade_no = $data['out_order_no'];
			$api_trade_no = $data['order_trade_info']['trade_no'];
			$total_amount = $data['total_amount'];
			$buyer = $data['order_trade_info']['user_id2'];

			if($data['order_status'] == '2'){
				if($out_trade_no == TRADE_NO){
					processNotify($order, $api_trade_no, $buyer);
				}
			}
			return ['type'=>'html','data'=>'success'];
		}
		else {
			//验证失败
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel, $clientip;
		if(empty($order))exit();

		require(PAY_ROOT."inc/LakalaClient.php");

		$params = [
			'merchant_no' => $channel['appmchid'],
			'term_no' => $channel['appkey'],
			'out_trade_no' => $order['refund_no'],
			'refund_amount' => strval($order['refundmoney']*100),
			'origin_out_trade_no' => $order['trade_no'],
			'origin_trade_no' => $order['api_trade_no'],
			'location_info' => [
				'request_ip' => $clientip
			],
		];
		//print_r($params);

		$client = new LakalaClient($channel['appid'], $channel['appswitch']==1);
		try{
			$result = $client->execute('/api/v3/labs/relation/refund', $params);
			return ['code'=>0, 'trade_no'=>$result['trade_no'], 'refund_fee'=>$result['refund_amount']/100];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}
}