<?php
/**
 * 支付模块调用工厂
 */
namespace Common\Pay;

class PayFactory
{

    public function __construct($adapterName = '', $adapterConfig = array())
    {
        $this->setAdapter($adapterName, $adapterConfig);
    }

    /**
     * [setAdapter 构造适配器]
     * @ckhero
     * @DateTime 2016-12-14
     * 
     * @param string $adapterName
     *            [description]
     * @param array $adapterConfig
     *            [description]
     */
    public function setAdapter($adapterName = '', $adapterConfig)
    {
        $className = ucwords($adapterName);
        $str = "\Common\Pay\\$className\\$className";
        $this->adapterInstance = new $str($adapterConfig);
        return $this->adapterInstance;
    }

    public function __call($methodName, $methodArgs)
    {
        if (method_exists($this, $methodName)) {
            
            return call_user_func_array(array(
                & $this,
                $methodName
            ), $methodArgs);
        } elseif (! empty($this->adapterInstance) && $this->adapterInstance instanceof PayAbstract && method_exists($this->adapterInstance, $methodName)) {
            
            return call_user_func_array(array(
                & $this->adapterInstance,
                $methodName
            ), $methodArgs);
        }
    }
}
?>