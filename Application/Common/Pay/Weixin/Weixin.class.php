<?php 
namespace Common\Pay\Weixin;
use \Common\Pay\PayAbstract;
use \Common\Api\WxApi;
use Common\Api\Wxpay\UnifiedOrder_pub;

class Weixin extends PayAbstract{

    public function __construct($config = array()){

    	parent::__construct();
    	$this->setConfig($config);
    	$this->wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
    }
	public function getPrepareData(){
      
      //$wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
	  $unifiedOrder = new UnifiedOrder_pub();
	  $unifiedOrder->setParameter("openid", $_SESSION[$_SESSION['PLATFORM_CODE']]['zwcmopenid']); //当check_mode＝0或1时，原先没有去取openid或者openid不正确，所以在这里统一去取在掌握传媒公众号的openid
	  $unifiedOrder->setParameter("body", $this->orderInfo['title']); //商品描述
	  //自定义订单号，此处仅作举例
	  $timeStamp = time();
	  $out_trade_no = $this->orderInfo['out_trade_no'].'_'. time();
	  $unifiedOrder->setParameter("out_trade_no", "$out_trade_no"); //商户订单号
	  $unifiedOrder->setParameter("total_fee",$this->orderInfo['total_fee']*100); //总金额

	  if(preg_match("/".$_SERVER["HTTP_HOST"]."/", ONE_COIN_URL)){

	     $unifiedOrder->setParameter("notify_url", ONE_COIN_URL.'index.php/Home/Notify/wxPay'); //异步通知地址
	  }else{

	      $unifiedOrder->setParameter("notify_url", $this->config['NotifyUrl']); //异步通知地址
	  }

	  $unifiedOrder->setParameter("trade_type", $this->config['TradeType']); //交易类型
	  $prepay_id = $unifiedOrder->getPrepayId();
	  $paySign = $this->wxApi->getPaySign($prepay_id);
	  return array('status'=>1,'msg'=>'success','data'=>$paySign);
	}

	public function notify(){

		$xml = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        if(empty($xml)){
            $xml = file_get_contents('php://input', 'r');
        }

        //存储微信的回调
        $wxApi = new WxApi(C('APP_ID'), C('APP_SECRET'));
        $wxApi->saveData($xml);
        $out_trade_no_arr = explode('_',$wxApi->data['out_trade_no']);
        $out_trade_no = isset($out_trade_no_arr[0]) ? $out_trade_no_arr[0] : '';

        //验证签名，并回应微信。
        //对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
        //微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
        //尽可能提高通知的成功率，但微信不保证通知最终能成功。
        //
        $data['ordenum'] = isset($out_trade_no) ? $out_trade_no : '';
        $data['transaction_no'] = isset($wxApi->data['transaction_id']) ?$wxApi->data['transaction_id']:'' ;
        $data['pay_account'] = isset($wxApi->data['openid']) ?$wxApi->data['openid']:'' ;
        $data['xml'] = isset($xml) ?$xml:'' ;

        if ($wxApi->checkSign()==FALSE) {
            $this->setReturnParameter("return_code", "FAIL"); //返回状态码
            $this->setReturnParameter("return_msg", "签名失败"); //返回信息

            $res = array('status'=>4,'msg'=>'签名失败');
        } else {

        	if ($wxApi->data["return_code"]!="FAIL") {
                
        		$res = array('status'=> 1, 'msg'=>'success');
        	}
        }
        
        $res['data'] = $data;
        return $res;
	}

    public function payReturn() {}

/**
 * [setReturnParameter 设置返回信息]
 * @ckhero
 * @DateTime 2016-12-20
 * @param    [type]     $key [description]
 * @param    [type]     $val [description]
 */
	public function setReturnParameter($key,$val){  
         

         $this->wxApi->setReturnParameter($key, $val); //返回状态码
	}
/**
 * [sendReturnMsg 返回回调结果]
 * @ckhero
 * @DateTime 2016-12-20
 * @return   [type]     [description]
 */
	public function sendReturnMsg(){   

		$returnXml = $this->wxApi->returnXml();
        return $returnXml;
	}
    
}

 ?>