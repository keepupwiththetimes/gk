<?php
/**
 * 支付抽象类  约束支付类的格式
 */
namespace Common\Pay;

abstract class PayAbstract
{

    protected $config = array();
 // 支付配置信息
    protected $orderInfo = array();
 // 订单信息
    public function __construct()
    {}

    public function setConfig($config)
    {
        foreach ($config as $key => $val) {
            
            $this->config[$key] = $val;
        }
        return $this;
    }

    public function setOrderInfo($orderInfo2)
    {
        if (empty($orderInfo2))
            return false;
        foreach ($orderInfo2 as $key => $val) {
            
            $this->orderInfo[$key] = $val;
        }
        return $this;
    }

    abstract public function getPrepareData();
 // 支付信息生成
    abstract public function notify();
 // 支付回调 接口
     abstract public function payReturn();
 // 支付回调 接口
    abstract public function setReturnParameter($key, $val);
 // 设置支付回调是返回的消息
    abstract public function sendReturnMsg(); // 返回消息
}

?>