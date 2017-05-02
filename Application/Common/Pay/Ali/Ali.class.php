<?php 
namespace Common\Pay\Ali;

use \Common\Pay\PayAbstract;
class Ali extends PayAbstract{
    
    private $returnParam;
    public function __construct($config = array() ) {
 
    	parent::__construct();
        $this->setConfig($config);
    }

    /**
     * [getPrepareData 支付信息生成]
     * @ckhero
     * @DateTime 2016-12-20
     * 
     * @return [type] [description]
     */
    public function getPrepareData(){


    	//商户订单号，商户网站订单系统中唯一订单号，必填
	    $out_trade_no = $this->orderInfo['out_trade_no'];

	    //订单名称，必填
	    $subject = $this->orderInfo['title'];

	    //付款金额，必填
	    $total_amount = number_format($this->orderInfo['total_fee'], 2);

	    //商品描述，可空
	    $body = '';

	    //超时时间
	    $timeout_express="1m";

	    $payRequestBuilder = new AlipayTradeWapPayContentBuilder($this->config);
	    $payRequestBuilder->setBody($body);
	    $payRequestBuilder->setSubject($subject);
	    $payRequestBuilder->setOutTradeNo($out_trade_no);
	    $payRequestBuilder->setTotalAmount($total_amount);
	    $payRequestBuilder->setTimeExpress($timeout_express);

	    $payResponse = new AlipayTradeService($this->config);
	    $result=$payResponse->wapPay($payRequestBuilder,$this->config['return_url'],$this->config['notify_url']);
	    $res = array(
              'status' => '1',
              'msg' => 'success',
              'data' => $result
	    	);
	    return $res;
    }


    /**
     * [notify 异步请求]
     * @ckhero
     * @DateTime 2016-12-20
     * 
     * @return [type] [description]
     */
    public function notify(){

    	$arr=$_POST;
		$alipaySevice = new AlipayTradeService($this->config); 
		$result = $alipaySevice->check($arr); 
		$data['xml'] = json_encode($arr);
		$data['ordenum'] = $arr['out_trade_no'];
        $res = array(
                    'status'=>4,
                    'msg'=>'发生错误',
                    'data' => $data,
                );
		if($result) {

			if($arr['trade_status'] == 'TRADE_FINISHED' || $arr['trade_status'] == 'TRADE_SUCCESS') {

				//TRADE_FINISHED  交易结束不可退款    ；TRADE_SUCCESS交易支付成功
				$res['status'] = 1;
                $res['msg'] = 'success';
			}
		} else {

			$res['msg'] = '验证失败';
		}
		return $res;
    }
 public function payReturn(){

        $arr=$_GET;
        unset($arr['payType']);
        $alipaySevice = new AlipayTradeService($this->config); 
        $result = $alipaySevice->check($arr); 
        $data['xml'] = 'get'.json_encode($arr);
        $data['ordenum'] = $arr['out_trade_no'];
        $res = array(
                    'status'=>4,
                    'msg'=>'发生错误',
                    'data' => $data,
                );
        if($result) {

           // if($arr['trade_status'] == 'TRADE_FINISHED' || $arr['trade_status'] == 'TRADE_SUCCESS') {

                //TRADE_FINISHED  交易结束不可退款    ；TRADE_SUCCESS交易支付成功
                $res['status'] = 1;
                $res['msg'] = '验证成功';
           // }
        } else {

            $res['msg'] = '验证失败';
        }
        return $res;
    }

    /**
     * [setReturnParameter 设置返回信息]
     * @ckhero
     * @DateTime 2016-12-20
     * 
     * @param [type] $key
     *            [description]
     * @param [type] $val
     *            [description]
     */
    public function setReturnParameter($key, $val)
    {
        return $this->param[$key] = strtolower($val);
    }

    /**
     * [sendReturnMsg 返回回调结果]
     * @ckhero
     * @DateTime 2016-12-20
     * 
     * @return [type] [description]
     */
    public function sendReturnMsg()
    {
        return $this->param['return_code'];
    }
}
 ?>
