<?php
namespace Common\Pay\Cmbc;

use \Common\Pay\PayAbstract;

class Cmbc extends PayAbstract
{

    public function __construct($config = array())
    {
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
    public function getPrepareData()
    {
        $data = array(
            
            'version' => $this->config['version'], // 版本号 默认1.0.0
            'billNo' => $this->config['corpID'] . $this->orderInfo['out_trade_no'], // 订单号
            'txAmt' => number_format($this->orderInfo['total_fee'], 2, '.', ''), // 交易金额 DECIMAL(13,2)
            'PayerCurr' => 156, // 币种
            'txDate' => date('Ymd'), // 交易日期
            'txTime' => date('His'), // 交易时间
            'corpID' => $this->config['corpID'], // 商户代码
            'corpName' => $this->config['corpName'], // 商户名称
            'subCorpID' => $this->config['corpID'], // 二级商户号
            'NotifyUrl' => $this->config['NotifyUrl'], // 后台异步通知地址
                                                        // 'NotifyUrl' => 'http://uat.zwmedia.com.cn/wxzspfse/cmbc.php',
            'JumpUrl' => $this->config['JumpUrl'], // 前台跳转地址
            'Account' => '', // 银行卡号
            'TransInfo' => 'TransInfo', // 交易详细内容
            'Message' => 'Message', // 商户预留信息
            'Channel' => 3, // 支付通道
            'LoanFlag' => '', // 借贷标示
            'ProductType' => 1, // 商品类型
            'ProductName' => $this->orderInfo['title'], // 商品名称
            'Remark' => 'Remark'
        ) // 备注
        ;
        $str = implode('|', $data);
        $str = base64_encode($str);
        $strInitialize = $this->sadkSign($str);
        if (preg_match('/\$ERRCODE=0x70010001/', $strInitialize)) { // 加密程序没有初始化
            
            $this->sadkInitializeByParam(); // 初始化
            $strInitialize = $this->sadkSign($str);
        }
        
        return array(
            'status' => 1,
            'msg' => 'success',
            'data' => $strInitialize . "|" . $this->config['version']
        );
    }

    /**
     * [notify 异步请求]
     * @ckhero
     * @DateTime 2016-12-20
     * 
     * @return [type] [description]
     */
    public function notify( $type = 'NotifyUrl')
    {
        $str = I('get.payresult');
        $strUnInitialize = $this->sadkVerify($str); // 解密 为base64编码
        if (preg_match('/\$ERRCODE=0x70010001/', $strUnInitialize)) { // 解密程序没有初始化
            
            $this->sadkInitializeByParam(); // 初始化
            $strUnInitialize = $this->sadkVerify($str);
        }
        // file_put_contents('testdverify.txt', $strUnInitialize);
        $strUnInitialize = base64_decode($strUnInitialize);
        // file_put_contents('test64.txt', $strUnInitialize);
        $arr = explode('|', $strUnInitialize);
        // file_put_contents('test1.txt', json_encode($arr));
        $data['ordenum'] = substr($arr[0], strlen($this->config['corpID']));
        $data['xml'] = $type.$strUnInitialize;
        if (count($arr) == 9) {
            
            if ($arr['6'] == 0) {
                
                $res = array(
                    'status' => 1,
                    'msg' => 'success'
                );
            } else {
                
                $res = array(
                    'status' => 2,
                    'msg' => 'fail'
                ); // z支付失败
            }
        } else {
            
            $res = array(
                'status' => 4,
                'msg' => '异常'
            );
        }
        $res['data'] = $data;
        return $res;
    }
    
    /**
     * [payReturn jumpurl 回来的处理]
     * @ckhero
     * @DateTime 2017-01-23
     * @return   [type]     [description]
     */
    public function payReturn()
    {

        return $this->notify('jumpurl');
    }
    /**
     * [sadkVerify 支付完成的异步通知]
     * @ckhero
     * @DateTime 2016-12-14
     * 
     * @param [type] $payresult
     *            [description]
     * @return [type] [description]
     */
    public function sadkVerify($payresult)
    {
        $base64Encode = trim($payresult);
        
        try {
            
            // $ret = $this->lajp_call("cfca.sadk.cmbc.tools.php.PHPDecryptKit::DecryptAndVerifyMessage", $payresult);
            $ret = $this->lajp_call("cfca.sadk.cmbc.tools.php.PHPDecryptKit::DecryptAndVerifyMessage", $base64Encode);
            return $ret;
            // echo "{$base64Encode}<br>";
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * [sadkInitializeByParam 根据参数初始化解密程序]
     * @ckhero
     * @DateTime 2016-12-14
     * 
     * @return [type] [description]
     */
    public function sadkInitializeByParam()
    {
        try {
            
            $ret = $this->lajp_call("cfca.sadk.cmbc.tools.php.PHPDecryptKit::Initialize", $this->config['privatePath'], $this->config['privatePassword'], $this->config['publicPath']);
            return $ret;
        } 

        catch (Exception $e) {
            return $e;
        }
    }

    /**
     * [sadkSign 加密]
     * @ckhero
     * @DateTime 2016-12-14
     * 
     * @param [type] $base64Plain
     *            [base64的字符串]
     * @return [type] [description]
     */
    public function sadkSign($base64Plain)
    {
        try {
            
            $ret = $this->lajp_call("cfca.sadk.cmbc.tools.php.PHPDecryptKit::SignAndEncryptMessage", $base64Plain);
            return $ret; // 70010001的时候说明 没有初始化 需要调用sadkInitializeByParam 进行初始化
        } catch (Exception $ret) {
            return $e;
        }
    }

    /**
     * [lajp_call 调用java]
     * @ckhero
     * @DateTime 2016-12-14
     * 
     * @param [type] $ip
     *            [java进程ip]
     * @param [type] $port
     *            [java进程端口]
     * @param [type] $pError
     *            [description]
     * @param [type] $sError
     *            [description]
     * @param [type] $jE
     *            [description]
     * @return [type] [description]
     */
    private function lajp_call()
    {
        // 参数数量
        $args_len = func_num_args();
        // 参数数组
        $arg_array = func_get_args();
        
        // 参数数量不能小于1
        if ($args_len < 1) {
            throw new \Exception("[LAJP Error] lajp_call function's arguments length < 1", $this->config['pError']);
        }
        // 第一个参数是Java类、方法名称，必须是string类型
        if (! is_string($arg_array[0])) {
            throw new \Exception("[LAJP Error] lajp_call function's first argument must be string \"class_name::method_name\".", $this->config['pError']);
        }
        
        if (($socket = socket_create(AF_INET, SOCK_STREAM, 0)) === false) {
            throw new \Exception("[LAJP Error] socket create error.", $this->config['sError']);
        }
        
        if (socket_connect($socket, $this->config['ip'], $this->config['port']) === false) {
            throw new \Exception("[LAJP Error] socket connect error.", $this->config['sError']);
        }
        
        // 消息体序列化
        $request = serialize($arg_array);
        $req_len = strlen($request);
        
        $request = $req_len . "," . $request;
        
        // echo "{$request}<br>";
        
        $send_len = 0;
        do {
            // 发送
            if (($sends = socket_write($socket, $request, strlen($request))) === false) {
                throw $this->Exception = new \Exception("[LAJP Error] socket write error.", $this->config['sError']);
            }
            
            $send_len += $sends;
            $request = substr($request, $sends);
        } while ($send_len < $req_len);
        
        // 接收
        $response = "";
        while (true) {
            $recv = "";
            if (($recv = socket_read($socket, 1400)) === false) {
                throw new \Exception("[LAJP Error] socket read error.", $this->config['sError']);
            }
            
            if ($recv == "") {
                break;
            }
            
            $response .= $recv;
            
            // echo "{$response}<br>";
        }
        
        // 关闭
        socket_close($socket);
        
        $rsp_stat = substr($response, 0, 1); // 返回类型 "S":成功 "F":异常
        $rsp_msg = substr($response, 1); // 返回信息
                                         
        // echo "返回类型:{$rsp_stat},返回信息:{$rsp_msg}<br>";
        
        if ($rsp_stat == "F") {
            // 异常信息不用反序列化
            throw new \Exception("[LAJP Error] Receive Java exception: " . $rsp_msg, $this->config['jE']);
        } else {
            if ($rsp_msg != "N") // 返回非void
{
                // 反序列化
                return unserialize($rsp_msg);
            }
        }
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
        return true;
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
        return true;
    }
}

?>