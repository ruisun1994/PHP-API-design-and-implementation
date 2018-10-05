<?php
/**
 * @name WxpayModel
 * @desc 微信支付功能封装
 * @author pangee
 */
$wxpayLibPath = dirname(__FILE__).'/../library/ThirdParty/WxPay/';
include_once( $wxpayLibPath.'WxPay.Api.php' );
include_once( $wxpayLibPath.'WxPay.Notify.php' );
include_once( $wxpayLibPath.'WxPay.NativePay.php' );
include_once( $wxpayLibPath.'WxPay.Data.php' );

class WxpayModel extends WxPayNotify {
	public $errno = 0;
	public $errmsg = "";
	private $_db;

    public function __construct() {
		$this->_db = new PDO("mysql:host=127.0.0.1;dbname=test;", "root", "123456");
    }   

	public function createbill( $itemId, $uid ){
		$query = $this->_db->prepare("select * from `item` where `id`= ? ");
		$query->execute( array($itemId) );
		$ret = $query->fetchAll();
		if( !$ret || count($ret)!=1 ) {
			$this->errno = -6003;
			$this->errmsg = "找不到这件商品";
			return false;
		}
		$item = $ret[0];
		if( strtotime($item['etime']) <= time() ) {
			$this->errno = -6004;
			$this->errmsg = "商品已过期，不能购买";
			return false;
		}
		if( intval($item['stock'])<=0 ) {
			$this->errno = -6005;
			$this->errmsg = "商品库存不够，不能购买";
			return false;
		}
		/**
		 * 创建bill
		 */
		$query = $this->_db->prepare("insert into `bill` (`itemid`,`uid`,`price`,`status`) VALUES ( ?, ?, ?, 'unpaid') ");
		$ret = $query->execute( array( $itemId, $uid, intval($item['price']) ) );
		if ( !$ret ) {
			$this->errno = -6006;
			$this->errmsg = "创建账单失败";
			return false;
		}

		/**
		 * 成功创建账单后，需要扣去商品库存1件
		 * TODO 此处应用用事务
		 */
		$query = $this->_db->prepare("update `item` set `stock`=`stock`-1 where `id`= ? ");
		$ret = $query->execute( array( $itemId ) );
		if ( !$ret ) {
			$this->errno = -6007;
			$this->errmsg = "更新库存失败";
			return false;
		}
		
		return intval($this->_db->lastInsertId());
	}


	public function qrcode( $billId ){
		$query = $this->_db->prepare("select * from `bill` where `id`= ? ");
		$query->execute( array($billId) );
		$ret = $query->fetchAll();
		if( !$ret || count($ret)!=1 ) {
			$this->errno = -6009;
			$this->errmsg = "找不到账单信息";
			return false;
		}
		$bill = $ret[0];
		
		$query = $this->_db->prepare("select * from `item` where `id`= ? ");
		$query->execute( array($bill['itemid']) );
		$ret = $query->fetchAll();
		if( !$ret || count($ret)!=1 ) {
			$this->errno = -6010;
			$this->errmsg = "找不到商品信息";
			return false;
		}
		$item = $ret[0];

		/**
		 * 调用微信支付lib，生成账单二维码
		 */
		$input = new WxPayUnifiedOrder();
		$input->SetBody( $item['name'] );
		$input->SetAttach( $billId );
		$input->SetOut_trade_no(WxPayConfig::MCHID.date("YmdHis"));
		$input->SetTotal_fee( $bill['price'] );
		$input->SetTime_start(date("YmdHis"));
		$input->SetTime_expire(date("YmdHis", time()+86400*3));
		$input->SetGoods_tag( $item['name'] );
		$input->SetNotify_url("http://103.72.145.223:8889/?c=wxpay&a=callback");
		$input->SetTrade_type("NATIVE");
		$input->SetProduct_id( $billId );

		$notify = new NativePay();
		$result = $notify->GetPayUrl($input);
		$url = $result["code_url"];
		return $url;
	}

	public function callback(){
		/**
		 * 订单成功，更新账单
		 * TODO 因为SK没有，没法与微信支付的服务端做Response确认，只能单方面记账
		 */
		$xmlData = file_get_contents("php://input");
		if( substr_count( $xmlData, "<result_code><![CDATA[SUCCESS]]></result_code>" )==1 && 
			substr_count( $xmlData, "<return_code><![CDATA[SUCCESS]]></return_code>" )==1 )
		{
			preg_match( '/<attach>(.*)\[(\d+)\](.*)<\/attach>/i', $xmlData, $match );
			if( isset($match[2])&&is_numeric($match[2]) ) {
				$billId = intval( $match[2] );
			}
			preg_match( '/<transaction_id>(.*)\[(\d+)\](.*)<\/transaction_id>/i', $xmlData, $match );
			if( isset($match[2])&&is_numeric($match[2]) ) {
				$transactionId = intval( $match[2] );
			}
		}
		if( isset($billId) && isset($transactionId) ) {
			$query = $this->_db->prepare("update `bill` set `transaction`=? ,`ptime`=? ,`status`='paid' where `id`=? ");
			$query->execute( array( $transactionId, date("Y-m-d H:i:s"), $billId ) );
		}
	}
}
