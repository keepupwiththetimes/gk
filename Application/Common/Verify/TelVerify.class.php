<?php
namespace Common\Verify;

class TelVerify
{

    protected $config = array(
        
        'codeSet' => '01234567890',
        'expire' => '900',
        'length' => 4,
        'authKey' => 'authkey',
        'tel' => ''
    );

    function __construct($config = array())
    {
        $this->config = array_merge($this->config, $config);
    }

    public function entry()
    {
        $code = $this->getCode();
        $_SESSION['telcode'] = array(
            
            'code' => $this->authCode($code . $this->config['tel']),
            'time' => time()
        );
        return $code;
    }
    
    /**
     * [check description]
     * @ckhero
     * @DateTime 2017-03-01
     * @param    [type]     $code [description]
     * @return   [type]           [1验证码不正确 0 为验证码正确 2为验证码过期  3位验证码不存在]
     */
    public function check($code)
    {
        $session = $_SESSION['telcode'];
        
        if (empty($session['code'])) {

            return 3;
        }
        if ((time() - $session['time']) > $this->config['expire']) {
            
            unset($_SESSION['telcode']); // 释放session
            return 2;
        }
        
        if ($this->authCode($code . $this->config['tel']) == $session['code'] && $session['code'] != '') { // 验证码正确
            
            unset($_SESSION['telcode']); // 释放session
            return 0;
        } else {
            
            return 1;
        }
    }

    private function getCode()
    {
        $code = "";
        $codeSet = $this->config['codeSet'];
        $codeSetLength = strlen($codeSet) - 1;
        for ($i = 0; $i < $this->config['length']; $i ++) {
            
            $code .= $codeSet[mt_rand(0, $codeSetLength)];
        }
        
        return $code;
    }

    private function authCode($code)
    {
        $key = substr(md5($this->config['authKey']), 0, 10);
        $code = substr(md5($code), 0, 10);
        return md5($key . $code);
    }

    /**
     * [check description]
     * @ckhero
     * @DateTime 2017-03-01
     * @param    [type]     $code [description]
     * @return   [type]           [1验证码不正确 0 为验证码正确 2为验证码过期  3位验证码不存在]
     */
    public function checkNew($code)
    {
        $session = $_SESSION['telcode'];

        //没有验证码需要现获取
        if (empty($session['code'])) {
             
            $dataJson = array(
                        'state' => 0,
                        'msg' => L('_GET_CODE_FIRST_'),
                    );
            return $dataJson;
        }
        //验证码过期
        if ((time() - $session['time']) > $this->config['expire']) {
            
            unset($_SESSION['telcode']); // 释放session
            $dataJson = array(
                        'state' => 0,
                        'msg' => L('_TEL_CODE_TIMEOUT_'),
                    );
            return $dataJson;
        }
        
        //验证码正确
        if ($this->authCode($code . $this->config['tel']) == $session['code'] && $session['code'] != '') { // 验证码正确
            
            unset($_SESSION['telcode']); // 释放session
            $dataJson = array(
                        'state' => 1,
                        'msg' => L('_TEL_VERIFY_SUCC_'),
                    );
            return $dataJson;
        //验证码错误
        } else {
            
            $dataJson = array(
                        'state' => 0,
                        'msg' => L('_TEL_CODE_ILLEGAL_'),
                    );
            return $dataJson;
        }
    }
}
?>