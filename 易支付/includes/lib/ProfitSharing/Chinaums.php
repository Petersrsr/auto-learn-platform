<?php

namespace lib\ProfitSharing;

use Exception;

class Chinaums implements IProfitSharing
{

    static $paytype = 'chinaums';

    private $channel;
    private $service;

    function __construct($channel){
		$this->channel = $channel;
	}

    //请求分账
    public function submit($trade_no, $api_trade_no, $account, $name, $money){
    }

    //查询分账结果
    public function query($trade_no, $api_trade_no, $settle_no){
    }

    //解冻剩余资金
    public function unfreeeze($trade_no, $api_trade_no){
    }

    //分账回退
    public function return($trade_no, $api_trade_no, $account, $money){
    }

    //添加分账接收方
    public function addReceiver($account, $name = null){
        return ['code'=>0, 'msg'=>'添加分账接收方成功'];
    }

    //删除分账接收方
    public function deleteReceiver($account){
        return ['code'=>0, 'msg'=>'删除分账接收方成功'];
    }
}