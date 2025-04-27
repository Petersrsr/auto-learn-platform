<?php

namespace lib\ProfitSharing;

use Exception;

class Alipay implements IProfitSharing
{

    static $paytype = 'alipay';

    private $channel;
    private $service;

    function __construct($channel){
		$this->channel = $channel;
        $alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
        $this->service = new \Alipay\AlipaySettleService($alipay_config);
	}

    //请求分账
    public function submit($trade_no, $api_trade_no, $account, $name, $money){
        if(strpos($account, '|')){
            global $DB;
            $accounts = explode('|', $account);
            $psorder = CommUtil::getOrder($trade_no);
            $rates = explode('|', $psorder['rate']);
            $order_money = $DB->findColumn('order', 'realmoney', ['trade_no'=>$trade_no]);
            $receivers = [];
            foreach($accounts as $i=>$account){
                $rate = isset($rates[$i]) ? $rates[$i] : $rates[0];
                $money = round($order_money * $rate / 100, 2);
                $type = self::get_alipay_account_type($account);
                $receivers[] = [
                    'trans_in_type' => $type,
                    'trans_in' => $account,
                    'amount' => $money
                ];
            }
            $bizContent = array(
                'out_request_no' => date("YmdHis").rand(11111,99999),
                'trade_no' => $api_trade_no,
                'royalty_parameters' => $receivers,
                'extend_params' => [
                    'royalty_finish' => 'true'
                ]
            );
            try{
                $result = $this->service->aopExecute('alipay.trade.order.settle', $bizContent);
                return ['code'=>1, 'msg'=>'分账成功', 'settle_no'=>$result['settle_no']];
            } catch (Exception $e) {
                return ['code'=>-1, 'msg'=>$e->getMessage()];
            }
        }

        $type = self::get_alipay_account_type($account);

        try{
            $result = $this->service->order_settle($api_trade_no, $type, $account, $money);
            return ['code'=>1, 'msg'=>'分账成功', 'settle_no'=>$result['settle_no']];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //查询分账结果
    public function query($trade_no, $api_trade_no, $settle_no){
        try{
            $result = $this->service->order_settle_query($settle_no);
            $receiver = $result['royalty_detail_list'][0];
            if($receiver['state'] == 'SUCCESS'){
                return ['code'=>0, 'status'=>1];
            }elseif($receiver['state'] == 'FAIL'){
                return ['code'=>0, 'status'=>2, 'reason'=>'['.$receiver['error_code'].']'.$receiver['error_desc']];
            }else{
                return ['code'=>0, 'status'=>0];
            }
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //解冻剩余资金
    public function unfreeeze($trade_no, $api_trade_no){
        try{
            $this->service->order_settle_unfreeze($api_trade_no);
            return ['code'=>0, 'msg'=>'解冻剩余资金成功'];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //分账回退
    public function return($trade_no, $api_trade_no, $account, $money){
        $type = self::get_alipay_account_type($account);

        try{
            $this->service->order_settle_refund($api_trade_no, $type, $account, $money);
            return ['code'=>0, 'msg'=>'退分账成功'];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //添加分账接收方
    public function addReceiver($account, $name = null){
        if(strpos($account, '|')){
            $accounts = explode('|', $account);
            $names = explode('|', $name);
            foreach($accounts as $i => $account){
                $type = self::get_alipay_account_type($account);
                try{
                    $this->service->relation_bind($type, $account, $names[$i]);
                } catch (Exception $e) {
                    return ['code'=>-1, 'msg'=>$e->getMessage()];
                }
            }
            return ['code'=>0, 'msg'=>'添加分账接收方成功'];
        }
        $type = self::get_alipay_account_type($account);

        try{
            $this->service->relation_bind($type, $account, $name);
            return ['code'=>0, 'msg'=>'添加分账接收方成功'];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    //删除分账接收方
    public function deleteReceiver($account){
        if(strpos($account, '|')){
            $accounts = explode('|', $account);
            foreach($accounts as $account){
                $type = self::get_alipay_account_type($account);
                try{
                    $this->service->relation_unbind($type, $account);
                } catch (Exception $e) {
                    return ['code'=>-1, 'msg'=>$e->getMessage()];
                }
            }
            return ['code'=>0, 'msg'=>'删除分账接收方成功'];
        }
        $type = self::get_alipay_account_type($account);

        try{
            $this->service->relation_unbind($type, $account);
            return ['code'=>0, 'msg'=>'删除分账接收方成功'];
        } catch (Exception $e) {
            return ['code'=>-1, 'msg'=>$e->getMessage()];
        }
    }

    private static function get_alipay_account_type($account){
        if(is_numeric($account) && substr($account,0,4)=='2088' && strlen($account)==16)$type = 'userId';
	    else $type = 'loginName';
        return $type;
    }
}